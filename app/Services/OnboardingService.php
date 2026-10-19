<?php

namespace App\Services;

use App\Models\DocumentInstance;
use App\Models\DocumentTemplate;
use App\Models\IdentitySubmission;
use App\Models\LivenessCheck;
use App\Models\OnboardingCase;
use App\Models\OrgAssignment;
use App\Models\User;
use App\Services\ESign\ESignService;
use App\Services\Liveness\LivenessService;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OnboardingService
{
    public function __construct(
        protected DocumentTemplateService $templateService,
        protected ESignService $esignService,
        protected LivenessService $livenessService
    ) {}

    /**
     * Create a new candidate and initialize onboarding workflow.
     */
    public function createCandidate(array $data, int $hrUserId): OnboardingCase
    {
        $this->templateService->ensureDefaultTemplates();

        // 1. Create candidate user record
        $rawPassword = Str::random(12);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $this->generateUniqueUsername($data['name']),
            'password' => Hash::make($rawPassword),
            'role' => 'employee',
            'employment_status' => 'candidate',
            'entity_name' => $data['entity_name'] ?? 'PT HR Group Indonesia',
            'branch_id' => $data['branch_id'],
            'position_id' => $data['position_id'],
            'manager_id' => $data['supervisor_id'] ?? null,
            'unit' => $data['unit'] ?? 'Operasional',
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'base_salary' => $data['salary_offered'],
            'hired_at' => $data['start_date'],
            'active' => false,
            'must_change_password' => true,
        ]);

        // 2. Create Onboarding Case
        $token = Str::random(64);
        $validUntil = Carbon::parse($data['offer_valid_until'] ?? now()->addDays(7));

        $case = OnboardingCase::create([
            'user_id' => $user->id,
            'branch_id' => $data['branch_id'],
            'position_id' => $data['position_id'],
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'status' => 'menunggu_persetujuan_hr',
            'salary_offered' => $data['salary_offered'],
            'benefits' => $data['benefits'] ?? [
                'BPJS Ketenagakerjaan & BPJS Kesehatan',
                'Tunjangan Hari Raya (THR) proporsional',
                'Hak Cuti Tahunan 12 hari kerja',
            ],
            'start_date' => $data['start_date'],
            'offer_valid_until' => $validUntil,
            'token' => $token,
            'token_expires_at' => $validUntil->copy()->endOfDay(),
        ]);

        // 3. Auto-generate draft Offering Letter from active template
        $template = DocumentTemplate::where('type', 'offering_letter')->first();
        $version = $template?->activeVersion;

        if ($version) {
            $facts = $this->templateService->buildFactsSnapshot($case, [
                'document_number' => 'OL/' . date('Y/m/') . str_pad((string) $case->id, 4, '0', STR_PAD_LEFT),
            ]);

            $rendered = $this->templateService->renderContent($version->content, $facts);
            $hash = hash('sha256', $rendered);

            DocumentInstance::create([
                'onboarding_case_id' => $case->id,
                'user_id' => $user->id,
                'type' => 'offering_letter',
                'document_template_version_id' => $version->id,
                'document_number' => $facts['document_number'],
                'version' => 1,
                'status' => 'draft',
                'rendered_content' => $rendered,
                'file_hash' => $hash,
                'data_snapshot' => $facts,
                'ready_for_review' => true,
            ]);
        }

        Audit::record('onboarding_case', $case->id, 'create_candidate', "Kandidat {$user->name} berhasil didaftarkan dan draf offering letter dibuat.");

        return $case;
    }

    /**
     * HR approves offering letter and opens signing flow for candidate.
     */
    public function hrApproveOffer(OnboardingCase $case, int $hrUserId): DocumentInstance
    {
        abort_if(in_array($case->status, ['selesai', 'dibatalkan', 'ditolak']), 422, 'Kasus onboarding ini sudah ditutup.');

        $offerDoc = $case->offeringDocument;
        abort_unless($offerDoc, 404, 'Dokumen penawaran kerja (Offering Letter) tidak ditemukan.');

        $facts = $offerDoc->data_snapshot;
        $facts['hr_signatory_name'] = auth()->user()?->name ?: 'HR Management';
        $facts['hr_signatory_title'] = 'Head of HR / Authorized Signatory';
        $facts['company_signed_at'] = now()->format('d/m/Y H:i:s T');

        $rendered = $this->templateService->renderContent($offerDoc->templateVersion->content, $facts);
        $hash = hash('sha256', $rendered);

        $offerDoc->update([
            'status' => 'menunggu_tanda_tangan',
            'rendered_content' => $rendered,
            'file_hash' => $hash,
            'data_snapshot' => $facts,
            'hr_approved_at' => now(),
            'hr_approved_by' => $hrUserId,
            'expires_at' => $case->offer_valid_until?->copy()->endOfDay(),
        ]);

        // Initiate candidate signing request
        $this->esignService->initiateDocumentSigning($offerDoc, [
            [
                'signer_name' => $case->user->name,
                'signer_email' => $case->user->email,
                'signer_phone' => $case->user->phone,
                'signer_role' => 'candidate',
            ],
        ]);

        $case->update([
            'status' => 'menunggu_tanda_tangan_offer',
            'hr_approver_id' => $hrUserId,
            'approved_by_hr_at' => now(),
        ]);

        Audit::record('onboarding_case', $case->id, 'approve_offer', "HR menyetujui draf offering letter untuk kandidat {$case->user->name}.");

        return $offerDoc;
    }

    /**
     * Candidate signs offering letter.
     */
    public function signOfferingLetter(OnboardingCase $case, array $signatureData): void
    {
        abort_if($case->status !== 'menunggu_tanda_tangan_offer', 422, 'Offering letter tidak sedang dalam status menunggu penandatanganan.');
        abort_if($case->isExpired(), 422, 'Masa berlaku penawaran kerja (Offering Letter) telah kedaluwarsa.');

        $offerDoc = $case->offeringDocument;
        abort_unless($offerDoc, 404, 'Dokumen offering letter tidak ditemukan.');

        $signingRequest = $offerDoc->signingRequests()->where('signer_role', 'candidate')->firstOrFail();
        $this->esignService->processSignature($signingRequest, $signatureData);

        // Prepare and generate NDA document automatically
        $this->prepareNdaDocument($case);

        $case->update([
            'status' => 'menunggu_tanda_tangan_nda',
        ]);

        Audit::record('onboarding_case', $case->id, 'sign_offer', 'Kandidat telah menandatangani Offering Letter. Tahap penandatanganan NDA dibuka.');
    }

    /**
     * Prepare NDA document instance from active template.
     */
    protected function prepareNdaDocument(OnboardingCase $case): DocumentInstance
    {
        $template = DocumentTemplate::where('type', 'nda')->first();
        $version = $template?->activeVersion;

        $docNumber = 'NDA/' . date('Y/m/') . str_pad((string) $case->id, 4, '0', STR_PAD_LEFT);
        $facts = $this->templateService->buildFactsSnapshot($case, [
            'document_number' => $docNumber,
        ]);

        $content = $version ? $version->content : $this->templateService->getDefaultNdaContent();
        $rendered = $this->templateService->renderContent($content, $facts);
        $hash = hash('sha256', $rendered);

        $ndaDoc = DocumentInstance::create([
            'onboarding_case_id' => $case->id,
            'user_id' => $case->user_id,
            'type' => 'nda',
            'document_template_version_id' => $version?->id,
            'document_number' => $docNumber,
            'version' => 1,
            'status' => 'menunggu_tanda_tangan',
            'rendered_content' => $rendered,
            'file_hash' => $hash,
            'data_snapshot' => $facts,
            'ready_for_review' => true,
        ]);

        // Create signing request for NDA
        $this->esignService->initiateDocumentSigning($ndaDoc, [
            [
                'signer_name' => $case->user->name,
                'signer_email' => $case->user->email,
                'signer_phone' => $case->user->phone,
                'signer_role' => 'candidate',
            ],
        ]);

        return $ndaDoc;
    }

    /**
     * Candidate signs NDA.
     */
    public function signNda(OnboardingCase $case, array $signatureData): void
    {
        abort_if($case->status !== 'menunggu_tanda_tangan_nda', 422, 'NDA tidak dapat ditandatangani pada tahap ini.');
        
        $offerDoc = $case->offeringDocument;
        abort_unless($offerDoc && $offerDoc->isSigned(), 422, 'Offering letter wajib selesai ditandatangani sebelum NDA dapat disahkan.');

        $ndaDoc = $case->ndaDocument;
        abort_unless($ndaDoc, 404, 'Dokumen NDA tidak ditemukan.');

        $signingRequest = $ndaDoc->signingRequests()->where('signer_role', 'candidate')->firstOrFail();
        $this->esignService->processSignature($signingRequest, $signatureData);

        $case->update([
            'status' => 'menunggu_ktp',
        ]);

        Audit::record('onboarding_case', $case->id, 'sign_nda', 'Kandidat telah menandatangani NDA. Tahap unggah KTP dibuka.');
    }

    /**
     * Candidate uploads KTP document.
     */
    public function submitKtp(OnboardingCase $case, UploadedFile $file, string $idNumber, string $fullName): IdentitySubmission
    {
        abort_if($case->status !== 'menunggu_ktp' && $case->status !== 'perlu_verifikasi', 422, 'Tahap pengunggahan KTP belum dibuka.');

        $ndaDoc = $case->ndaDocument;
        abort_unless($ndaDoc && $ndaDoc->isSigned(), 422, 'NDA wajib diselesaikan sebelum mengunggah KTP.');

        $cleanNik = trim(preg_replace('/[^0-9]/', '', $idNumber));
        abort_if(strlen($cleanNik) !== 16, 422, 'Nomor Induk Kependudukan (NIK) wajib berjumlah 16 digit angka valid.');

        $maskedNik = substr($cleanNik, 0, 4) . '********' . substr($cleanNik, -4);
        $hash = hash_file('sha256', $file->getRealPath());

        // Store file safely in private storage
        $path = $file->storeAs(
            'onboarding/ktp/' . date('Y/m'),
            'ktp_' . Str::random(32) . '.' . $file->getClientOriginalExtension(),
            'local'
        );

        $submission = IdentitySubmission::create([
            'onboarding_case_id' => $case->id,
            'user_id' => $case->user_id,
            'id_number_encrypted' => Crypt::encryptString($cleanNik),
            'id_number_masked' => $maskedNik,
            'full_name' => $fullName,
            'file_path' => $path,
            'file_hash' => $hash,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        // Update user masked NIK
        $case->user->update([
            'nik_encrypted' => Crypt::encryptString($cleanNik),
            'nik_masked' => $maskedNik,
        ]);

        $case->update([
            'status' => 'menunggu_liveness',
        ]);

        Audit::record('identity_submission', $submission->id, 'upload_ktp', "KTP berhasil diunggah dengan NIK bertopeng {$maskedNik}. Berkas tersimpan di penyimpanan privat.");

        return $submission;
    }

    /**
     * Candidate submits face-only photo with liveness evaluation.
     */
    public function submitLiveness(OnboardingCase $case, UploadedFile $file, array $clientMetadata = []): LivenessCheck
    {
        abort_if($case->status !== 'menunggu_liveness' && $case->status !== 'perlu_verifikasi', 422, 'Tahap pemeriksaan liveness wajah belum dibuka.');

        abort_unless($case->identitySubmissions()->exists(), 422, 'Unggah KTP wajib dilakukan sebelum verifikasi biometrik wajah.');

        $check = $this->livenessService->processFaceLiveness($case, $file, $clientMetadata);

        $case->update([
            'status' => 'perlu_verifikasi',
        ]);

        Audit::record('onboarding_case', $case->id, 'submit_liveness', "Pemeriksaan liveness wajah diajukan (Hasil: {$check->result}, Skor: {$check->liveness_score}). Menunggu verifikasi akhir HR.");

        return $check;
    }

    /**
     * HR requests revision of KTP or Selfie photo.
     */
    public function requestRevision(OnboardingCase $case, string $target, string $notes, int $hrUserId): void
    {
        abort_if(!in_array($case->status, ['perlu_verifikasi', 'menunggu_ktp', 'menunggu_liveness']), 422, 'Status saat ini tidak mendukung permintaan perbaikan.');

        if ($target === 'ktp') {
            $latestKtp = $case->latestIdentitySubmission;
            if ($latestKtp) {
                $latestKtp->update([
                    'status' => 'revision_requested',
                    'rejection_reason' => $notes,
                    'reviewed_by' => $hrUserId,
                    'reviewed_at' => now(),
                ]);
            }
            $case->update([
                'status' => 'menunggu_ktp',
                'correction_notes' => $notes,
            ]);
        } else {
            $latestLiveness = $case->latestLivenessCheck;
            if ($latestLiveness) {
                $latestLiveness->update([
                    'status' => 'manual_review',
                    'review_notes' => $notes,
                    'reviewed_by' => $hrUserId,
                    'reviewed_at' => now(),
                ]);
            }
            $case->update([
                'status' => 'menunggu_liveness',
                'correction_notes' => $notes,
            ]);
        }

        Audit::record('onboarding_case', $case->id, 'request_revision', "HR meminta perbaikan berkas {$target} dengan catatan: {$notes}");
    }

    /**
     * HR verifies all submissions and completes onboarding, activating the employee.
     */
    public function hrVerifyAndComplete(OnboardingCase $case, int $hrUserId, ?string $notes = null): void
    {
        abort_if($case->status !== 'perlu_verifikasi', 422, 'Hanya kasus dengan status Perlu Verifikasi yang dapat disetujui akhir.');

        $offerDoc = $case->offeringDocument;
        $ndaDoc = $case->ndaDocument;
        $ktp = $case->latestIdentitySubmission;
        $liveness = $case->latestLivenessCheck;

        abort_unless($offerDoc && $offerDoc->isSigned(), 422, 'Offering letter belum ditandatangani.');
        abort_unless($ndaDoc && $ndaDoc->isSigned(), 422, 'NDA belum ditandatangani.');
        abort_unless($ktp, 422, 'Berkas KTP belum diserahkan.');
        abort_unless($liveness, 422, 'Hasil liveness wajah belum tersedia.');

        // Update verification statuses
        $ktp->update([
            'status' => 'approved',
            'reviewed_by' => $hrUserId,
            'reviewed_at' => now(),
        ]);

        $liveness->update([
            'status' => 'passed',
            'reviewed_by' => $hrUserId,
            'reviewed_at' => now(),
        ]);

        // Complete case
        $case->update([
            'status' => 'selesai',
            'completed_at' => now(),
        ]);

        // Activate User into active employee
        $user = $case->user;
        $user->update([
            'active' => true,
            'employment_status' => 'active',
            'hired_at' => $case->start_date,
            'base_salary' => $case->salary_offered,
            'branch_id' => $case->branch_id,
            'position_id' => $case->position_id,
            'manager_id' => $case->supervisor_id,
        ]);

        // Record initial org assignment
        OrgAssignment::create([
            'user_id' => $user->id,
            'supervisor_id' => $case->supervisor_id,
            'branch_id' => $case->branch_id,
            'position_id' => $case->position_id,
            'unit' => $user->unit ?: 'Operasional',
            'effective_from' => $case->start_date,
            'reason' => 'Penempatan Awal (Onboarding Selesai)',
            'changed_by_id' => $hrUserId,
        ]);

        Audit::record('onboarding_case', $case->id, 'complete_onboarding', "Onboarding {$user->name} resmi disetujui HR dan akun karyawan diaktifkan.");
    }

    /**
     * Cancel an onboarding case.
     */
    public function cancelCase(OnboardingCase $case, string $reason, int $hrUserId): void
    {
        $case->update([
            'status' => 'dibatalkan',
            'rejection_reason' => $reason,
        ]);

        $case->documents()->where('status', '!=', 'ditandatangani')->update([
            'status' => 'dibatalkan',
        ]);

        Audit::record('onboarding_case', $case->id, 'cancel_case', "Onboarding dibatalkan oleh HR. Alasan: {$reason}");
    }

    /**
     * Helper to generate unique username.
     */
    protected function generateUniqueUsername(string $name): string
    {
        $base = Str::slug(Str::words($name, 2, ''));
        if (empty($base)) {
            $base = 'karyawan';
        }

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }
}
