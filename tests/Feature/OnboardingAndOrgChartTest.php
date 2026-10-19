<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DocumentInstance;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\IdentitySubmission;
use App\Models\LivenessCheck;
use App\Models\OnboardingCase;
use App\Models\OrgAssignment;
use App\Models\Position;
use App\Models\SigningRequest;
use App\Models\User;
use App\Services\DocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingAndOrgChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure default document templates are seeded
        app(DocumentTemplateService::class)->ensureDefaultTemplates();
    }

    public function test_hr_can_create_candidate_and_draft_offering_letter_is_generated_automatically(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch(['name' => 'Cabang Jakarta Selatan']);
        $position = $this->createPosition(['name' => 'Fullstack Developer']);
        $supervisor = $this->createUser([
            'name' => 'Budi Santoso',
            'role' => 'manager',
            'branch_id' => $branch->id,
            'employment_status' => 'active',
        ]);

        $payload = [
            'name' => 'Rian Hidayat',
            'email' => 'rian.hidayat@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Kebon Jeruk No. 10, Jakarta Barat',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'supervisor_id' => $supervisor->id,
            'unit' => 'Divisi Teknologi',
            'salary_offered' => 12500000,
            'start_date' => now()->addDays(14)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(7)->format('Y-m-d'),
            'entity_name' => 'PT LRM HR Group',
            'benefits' => ['Asuransi Kesehatan Swasta', 'Tunjangan Transportasi'],
        ];

        $response = $this->post(route('onboarding.store'), $payload);

        $response->assertSessionHas('ok');

        $case = OnboardingCase::first();
        $this->assertNotNull($case);
        $this->assertEquals('menunggu_persetujuan_hr', $case->status);
        $this->assertEquals(12500000, $case->salary_offered);

        $response->assertRedirect(route('onboarding.show', $case->id));

        // Verify Candidate User is created with inactive status
        $candidateUser = User::where('email', 'rian.hidayat@example.com')->first();
        $this->assertNotNull($candidateUser);
        $this->assertEquals('candidate', $candidateUser->employment_status);
        $this->assertFalse((bool) $candidateUser->active);
        $this->assertEquals($supervisor->id, $candidateUser->manager_id);
        $this->assertEquals('Divisi Teknologi', $candidateUser->unit);

        // Verify Draft Offering Letter is created
        $offeringDoc = DocumentInstance::where('onboarding_case_id', $case->id)
            ->where('type', 'offering_letter')
            ->first();

        $this->assertNotNull($offeringDoc);
        $this->assertEquals('draft', $offeringDoc->status);
        $this->assertStringContainsString('Rian Hidayat', $offeringDoc->rendered_content);
        $this->assertStringContainsString('Fullstack Developer', $offeringDoc->rendered_content);
        $this->assertNotNull($offeringDoc->file_hash);
    }

    public function test_candidate_cannot_sign_offer_before_hr_approval(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();
        $supervisor = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Uji',
            'email' => 'uji@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'supervisor_id' => $supervisor->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->assertEquals('menunggu_persetujuan_hr', $case->status);

        // Candidate attempts to sign offer on portal before HR approves
        $response = $this->post(route('onboarding.portal.sign_offer', $case->token), [
            'consent_agreed' => 1,
            'signature_data' => 'DIGITAL_SIGNATURE_BASE64',
        ]);

        $response->assertStatus(422);
    }

    public function test_hr_approval_activates_offer_for_signing(): void
    {
        $admin = $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Disetujui',
            'email' => 'disetujui@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 8000000,
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();

        // HR approves offering letter
        $response = $this->post(route('onboarding.approve_offer', $case->id));
        $response->assertSessionHas('ok');

        $case->refresh();
        $this->assertEquals('menunggu_tanda_tangan_offer', $case->status);
        $this->assertEquals($admin->id, $case->hr_approver_id);

        $offerDoc = $case->offeringDocument;
        $this->assertEquals('menunggu_tanda_tangan', $offerDoc->status);
        $this->assertNotNull($offerDoc->hr_approved_at);

        // Signing request should now be initiated
        $signingRequest = $offerDoc->signingRequests()->first();
        $this->assertNotNull($signingRequest);
        $this->assertEquals('pending', $signingRequest->status);
    }

    public function test_sequential_gates_prevent_skipping_steps(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Gate',
            'email' => 'gate@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 8000000,
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');

        // 1. Cannot sign NDA while still in menunggu_tanda_tangan_offer
        $ndaFail = $this->post(route('onboarding.portal.sign_nda', $case->token), [
            'consent_agreed' => 1,
        ]);
        $ndaFail->assertStatus(422);

        // 2. Cannot upload KTP before signing NDA
        $fakeKtp = UploadedFile::fake()->create('ktp.jpg', 500, 'image/jpeg');
        $ktpFail = $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171012345670001',
            'full_name' => 'Kandidat Gate',
            'ktp_file' => $fakeKtp,
        ]);
        $ktpFail->assertStatus(422);

        // 3. Cannot submit liveness before KTP
        $fakeSelfie = UploadedFile::fake()->create('selfie.jpg', 600, 'image/jpeg');
        $livenessFail = $this->post(route('onboarding.portal.submit_liveness', $case->token), [
            'selfie_file' => $fakeSelfie,
        ]);
        $livenessFail->assertStatus(422);
    }

    public function test_complete_onboarding_lifecycle_from_offer_to_activation(): void
    {
        $admin = $this->actingAsAdmin();

        $branch = $this->createBranch(['name' => 'Kantor Cabang Bandung']);
        $position = $this->createPosition(['name' => 'HR Operations Specialist']);
        $supervisor = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $this->post(route('onboarding.store'), [
            'name' => 'Dewi Lestari',
            'email' => 'dewi.lestari@example.com',
            'phone' => '081987654321',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'supervisor_id' => $supervisor->id,
            'unit' => 'Human Resources',
            'salary_offered' => 9500000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');

        // STEP 1: Candidate signs Offering Letter
        $resSignOffer = $this->post(route('onboarding.portal.sign_offer', $case->token), [
            'consent_agreed' => 1,
            'signature_data' => 'DATA_TANDA_TANGAN_OFFER',
        ]);
        $resSignOffer->assertRedirect(route('onboarding.portal', $case->token));

        $case->refresh();
        $this->assertEquals('menunggu_tanda_tangan_nda', $case->status);

        $offerDoc = $case->offeringDocument;
        $this->assertEquals('ditandatangani', $offerDoc->status);
        $this->assertNotNull($offerDoc->signingRequests()->first()->evidence_pack_path);

        // NDA document was automatically instantiated
        $ndaDoc = $case->ndaDocument;
        $this->assertNotNull($ndaDoc);
        $this->assertEquals('menunggu_tanda_tangan', $ndaDoc->status);
        $this->assertStringContainsString('Dewi Lestari', $ndaDoc->rendered_content);

        // STEP 2: Candidate signs NDA
        $resSignNda = $this->post(route('onboarding.portal.sign_nda', $case->token), [
            'consent_agreed' => 1,
            'signature_data' => 'DATA_TANDA_TANGAN_NDA',
        ]);
        $resSignNda->assertRedirect(route('onboarding.portal', $case->token));

        $case->refresh();
        $this->assertEquals('menunggu_ktp', $case->status);

        $ndaDoc->refresh();
        $this->assertEquals('ditandatangani', $ndaDoc->status);

        // STEP 3: Candidate uploads KTP (16 digits valid NIK)
        $ktpFile = UploadedFile::fake()->create('ktp_dewi.jpg', 800, 'image/jpeg');
        $rawNik = '3273012345670002';

        $resKtp = $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => $rawNik,
            'full_name' => 'Dewi Lestari',
            'ktp_file' => $ktpFile,
        ]);
        $resKtp->assertRedirect(route('onboarding.portal', $case->token));

        $case->refresh();
        $this->assertEquals('menunggu_liveness', $case->status);

        $identity = IdentitySubmission::where('onboarding_case_id', $case->id)->first();
        $this->assertNotNull($identity);
        $this->assertEquals('3273********0002', $identity->id_number_masked);
        $this->assertEquals($rawNik, Crypt::decryptString($identity->id_number_encrypted));
        Storage::disk('local')->assertExists($identity->file_path);

        // Candidate user record has masked NIK
        $user = $case->user;
        $this->assertEquals('3273********0002', $user->nik_masked);
        $this->assertEquals($rawNik, Crypt::decryptString($user->nik_encrypted));

        // STEP 4: Candidate takes face-only photo with liveness check
        $selfieFile = UploadedFile::fake()->create('selfie_dewi.jpg', 1200, 'image/jpeg');
        $resLiveness = $this->post(route('onboarding.portal.submit_liveness', $case->token), [
            'selfie_file' => $selfieFile,
        ]);
        $resLiveness->assertRedirect(route('onboarding.portal', $case->token));

        $case->refresh();
        $this->assertEquals('perlu_verifikasi', $case->status);

        $liveness = LivenessCheck::where('onboarding_case_id', $case->id)->first();
        $this->assertNotNull($liveness);
        $this->assertContains($liveness->result, ['lulus', 'perlu_tinjau']);
        Storage::disk('local')->assertExists($liveness->selfie_path);

        // STEP 5: HR final verification & activation
        $this->actingAs($admin);
        $resVerify = $this->post(route('onboarding.verify_final', $case->id), [
            'notes' => 'Seluruh dokumen valid dan verifikasi biometrik sesuai.',
        ]);
        $resVerify->assertSessionHas('ok');

        $case->refresh();
        $this->assertEquals('selesai', $case->status);
        $this->assertNotNull($case->completed_at);

        // Employee user is now fully activated!
        $user->refresh();
        $this->assertTrue((bool) $user->active);
        $this->assertEquals('active', $user->employment_status);
        $this->assertEquals($supervisor->id, $user->manager_id);
        $this->assertEquals(9500000, $user->base_salary);

        // Initial org assignment is recorded
        $initialAssignment = OrgAssignment::where('user_id', $user->id)->first();
        $this->assertNotNull($initialAssignment);
        $this->assertEquals($supervisor->id, $initialAssignment->supervisor_id);
        $this->assertEquals('Penempatan Awal (Onboarding Selesai)', $initialAssignment->reason);
    }

    public function test_hr_revision_request_retains_prior_submission_history(): void
    {
        $admin = $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Bambang Sudiro',
            'email' => 'bambang@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 6000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');

        // Candidate signs Offer & NDA
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        // Upload first blurry KTP
        $ktp1 = UploadedFile::fake()->create('ktp_blurry.jpg', 400, 'image/jpeg');
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171019999990001',
            'full_name' => 'Bambang Sudiro',
            'ktp_file' => $ktp1,
        ]);

        $case->refresh();
        $firstSubmission = IdentitySubmission::where('onboarding_case_id', $case->id)->latest('id')->first();
        $this->assertEquals('pending', $firstSubmission->status);

        // HR requests revision
        $this->actingAs($admin);
        $resRev = $this->post(route('onboarding.request_revision', $case->id), [
            'target' => 'ktp',
            'notes' => 'Foto KTP buram dan silau pada bagian NIK. Mohon foto ulang dengan jelas.',
        ]);
        $resRev->assertSessionHas('ok');

        $case->refresh();
        $this->assertEquals('menunggu_ktp', $case->status);

        $firstSubmission->refresh();
        $this->assertEquals('revision_requested', $firstSubmission->status);
        $this->assertEquals('Foto KTP buram dan silau pada bagian NIK. Mohon foto ulang dengan jelas.', $firstSubmission->rejection_reason);

        // Candidate re-uploads clear KTP
        $ktp2 = UploadedFile::fake()->create('ktp_clear.jpg', 600, 'image/jpeg');
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171019999990001',
            'full_name' => 'Bambang Sudiro',
            'ktp_file' => $ktp2,
        ]);

        // Both submissions are preserved in history
        $submissionsCount = IdentitySubmission::where('onboarding_case_id', $case->id)->count();
        $this->assertEquals(2, $submissionsCount);
    }

    public function test_sensitive_documents_access_control(): void
    {
        $admin = $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        $ktpFile = UploadedFile::fake()->create('ktp_privat.jpg', 500, 'image/jpeg');
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171028888880003',
            'full_name' => 'Siti Nurhaliza',
            'ktp_file' => $ktpFile,
        ]);

        // 1. Other regular employee cannot access private KTP stream
        $otherEmployee = $this->createUser(['role' => 'employee']);
        $this->actingAs($otherEmployee);

        $unauthorizedRes = $this->get(route('onboarding.stream_file', [$case->id, 'ktp']));
        $unauthorizedRes->assertStatus(403);

        // 2. HR / Admin can access
        $this->actingAs($admin);
        $authorizedRes = $this->get(route('onboarding.stream_file', [$case->id, 'ktp']));
        $authorizedRes->assertStatus(200);

        // 3. Auditor can access
        $auditor = $this->createUser(['role' => 'auditor']);
        $this->actingAs($auditor);
        $auditorRes = $this->get(route('onboarding.stream_file', [$case->id, 'ktp']));
        $auditorRes->assertStatus(200);

        // 4. Candidate owner can access their own file once activated and password set
        $case->user->update([
            'active' => true,
            'must_change_password' => false,
        ]);
        $this->actingAs($case->user);
        $ownerRes = $this->get(route('onboarding.stream_file', [$case->id, 'ktp']));
        $ownerRes->assertStatus(200);
    }

    public function test_document_immutability_upon_template_updates(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Teguh Prakoso',
            'email' => 'teguh@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 10000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        $this->post(route('onboarding.portal.sign_offer', $case->token), [
            'consent_agreed' => 1,
            'signature_data' => 'TEGUH_SIGNED',
        ]);

        $offerDoc = $case->offeringDocument;
        $originalRendered = $offerDoc->rendered_content;
        $originalHash = $offerDoc->file_hash;
        $this->assertNotEmpty($originalRendered);
        $this->assertNotEmpty($originalHash);

        // Now modify template and publish a new version
        $template = DocumentTemplate::where('type', 'offering_letter')->first();
        $newVersion = DocumentTemplateVersion::create([
            'document_template_id' => $template->id,
            'version_number' => 2,
            'status' => 'published',
            'content' => 'VERSI BARU YANG JAUH BERBEDA: {{candidate_name}} telah menerima tawaran.',
            'published_at' => now(),
        ]);
        $template->update(['active_version_id' => $newVersion->id]);

        // Verify the existing signed document remains completely unchanged (immutable snapshot)
        $offerDoc->refresh();
        $this->assertEquals($originalRendered, $offerDoc->rendered_content);
        $this->assertEquals($originalHash, $offerDoc->file_hash);
        $this->assertStringNotContainsString('VERSI BARU YANG JAUH BERBEDA', $offerDoc->rendered_content);
    }

    public function test_org_chart_hierarchy_and_cycle_prevention(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();

        // Structure:
        // CEO (null manager)
        //   -> Director (manager = CEO)
        //        -> Manager (manager = Director)
        //             -> Staff (manager = Manager)

        $ceo = $this->createUser(['name' => 'CEO Bambang', 'role' => 'admin', 'branch_id' => $branch->id, 'manager_id' => null, 'employment_status' => 'active']);
        $director = $this->createUser(['name' => 'Direktur Hendra', 'role' => 'manager', 'branch_id' => $branch->id, 'manager_id' => $ceo->id, 'employment_status' => 'active']);
        $manager = $this->createUser(['name' => 'Manajer Arif', 'role' => 'manager', 'branch_id' => $branch->id, 'manager_id' => $director->id, 'employment_status' => 'active']);
        $staff = $this->createUser(['name' => 'Staf Eko', 'role' => 'employee', 'branch_id' => $branch->id, 'manager_id' => $manager->id, 'employment_status' => 'active']);

        // 1. Build Tree API
        $resTree = $this->get(route('org-chart.data'));
        $resTree->assertStatus(200);
        $tree = $resTree->json();
        $this->assertIsArray($tree);

        // 2. Self-parenting attempt is rejected (HTTP 422)
        $selfParentRes = $this->post(route('org-chart.reassign'), [
            'user_id' => $director->id,
            'supervisor_id' => $director->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Uji coba self-parenting',
        ]);
        $selfParentRes->assertStatus(422);

        // 3. Cycle attempt: Making CEO report to Staff (CEO -> Staff, but Staff is descendant of CEO)
        $cycleRes = $this->post(route('org-chart.reassign'), [
            'user_id' => $ceo->id,
            'supervisor_id' => $staff->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Uji coba circular reference',
        ]);
        $cycleRes->assertStatus(422);

        // 4. Legitimate reassignment: Move Staff to report directly to Director
        $legitRes = $this->post(route('org-chart.reassign'), [
            'user_id' => $staff->id,
            'supervisor_id' => $director->id,
            'unit' => 'Operasional Khusus',
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Rotasi Divisi dan Efisiensi Pelaporan',
        ]);
        $legitRes->assertSessionHas('ok');

        $staff->refresh();
        $this->assertEquals($director->id, $staff->manager_id);
        $this->assertEquals('Operasional Khusus', $staff->unit);

        // Assignment history is recorded
        $historyRes = $this->get(route('org-chart.history', $staff->id));
        $historyRes->assertStatus(200);
        $historyData = $historyRes->json('history');
        $this->assertNotEmpty($historyData);
        $this->assertEquals('Rotasi Divisi dan Efisiensi Pelaporan', $historyData[0]['reason']);
    }

    public function test_master_settings_template_lifecycle_and_rollback(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::where('type', 'offering_letter')->first();
        $originalActiveId = $template->active_version_id;

        // 1. Save new draft version
        $saveDraftRes = $this->post(route('master-settings.documents.draft', 'offering_letter'), [
            'content' => "## SURAT PENAWARAN KERJA RESMI\nNama: {{candidate_name}}\nJabatan: {{position_title}}\nCabang: {{branch_name}}\nGaji: {{base_salary}}",
            'change_summary' => 'Penyesuaian tata letak format offering letter v2',
            'required_variables' => ['candidate_name', 'position_title', 'branch_name', 'base_salary'],
        ]);
        $saveDraftRes->assertSessionHas('ok');

        $draft = $template->versions()->where('status', 'draft')->first();
        $this->assertNotNull($draft);
        $this->assertEquals('draft', $draft->status);

        // 2. Submit for review
        $reviewRes = $this->post(route('master-settings.documents.submit_review', $draft->id));
        $reviewRes->assertSessionHas('ok');
        $draft->refresh();
        $this->assertEquals('in_review', $draft->status);

        // 3. Approve
        $approveRes = $this->post(route('master-settings.documents.approve', $draft->id));
        $approveRes->assertSessionHas('ok');
        $draft->refresh();
        $this->assertEquals('approved', $draft->status);

        // 4. Publish
        $publishRes = $this->post(route('master-settings.documents.publish', $draft->id));
        $publishRes->assertSessionHas('ok');
        $draft->refresh();
        $this->assertEquals('published', $draft->status);

        $template->refresh();
        $this->assertEquals($draft->id, $template->active_version_id);

        // 5. Rollback to original version
        $rollbackRes = $this->post(route('master-settings.documents.rollback', $originalActiveId));
        $rollbackRes->assertSessionHas('ok');

        $template->refresh();
        $this->assertEquals($originalActiveId, $template->active_version_id);
    }

    public function test_employment_contract_creation_and_viewing(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition(['name' => 'Backend Engineer']);
        $employee = $this->createUser([
            'name' => 'Gunawan Wibisono',
            'role' => 'employee',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'base_salary' => 11000000,
            'employment_status' => 'active',
        ]);

        // Create PKWT Contract
        $docNumber = 'PKWT/2026/10/' . $employee->id;
        $resStore = $this->post(route('contracts.store'), [
            'user_id' => $employee->id,
            'document_number' => $docNumber,
            'contract_type' => 'pkwt',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(12)->format('Y-m-d'),
            'probation_months' => 3,
            'work_location' => 'Kantor Pusat Jakarta',
            'salary' => 11000000,
            'job_duties' => 'Pengembangan microservices dan arsitektur backend.',
        ]);

        $resStore->assertSessionHas('ok');

        $contractDoc = DocumentInstance::where('document_number', $docNumber)->first();
        $this->assertNotNull($contractDoc);
        $this->assertEquals('employment_contract', $contractDoc->type);
        $this->assertEquals('menunggu_tanda_tangan', $contractDoc->status);
        $this->assertStringContainsString('Gunawan Wibisono', $contractDoc->rendered_content);
        $this->assertStringContainsString('Sebelas juta Rupiah', $contractDoc->rendered_content);

        // View contract detail page
        $resShow = $this->get(route('contracts.show', $contractDoc->id));
        $resShow->assertStatus(200);
        $resShow->assertSee('Gunawan Wibisono');
        $resShow->assertSee($docNumber);
    }
}
