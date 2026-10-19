<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\DocumentInstance;
use App\Models\OnboardingCase;
use App\Models\SignatureEvent;
use App\Models\Shift;
use App\Models\User;
use App\Services\OnboardingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @group Profil Karyawan & Dokumen
 *
 * Endpoint data profil karyawan, dokumen kepegawaian, e-sign, struktur tim, dan onboarding.
 */
class ProfileApiController extends Controller
{
    /**
     * Profil Lengkap
     *
     * Mengambil detail profil karyawan yang sedang login termasuk cabang, jabatan, dan atasan.
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load(['branch', 'position', 'manager.position']);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * Perbarui Profil
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update(array_filter($data, fn($v) => $v !== null));

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => new UserResource($user->fresh()->load(['branch', 'position', 'manager.position'])),
        ]);
    }

    /**
     * Status Hari Ini
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $now = now('Asia/Jakarta');
        $todayDate = $now->toDateString();

        if ($user->isOfficeSchedule()) {
            app(\App\Services\ShiftService::class)->ensureOfficeShiftToday($user, $now);
        }

        $shift = Shift::with(['branch', 'attendance', 'exceptions'])
            ->where('user_id', $user->id)
            ->whereDate('start_at', $todayDate)
            ->first();

        if (! $shift) {
            $shift = Shift::with(['branch', 'attendance', 'exceptions'])
                ->where('user_id', $user->id)
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now)
                ->first();
        }

        $attendance = $shift?->attendance;

        return response()->json([
            'success' => true,
            'data'    => [
                'server_time'       => $now->toIso8601String(),
                'server_timestamp'  => $now->timestamp,
                'has_shift'         => $shift !== null,
                'shift'             => $shift ? new ShiftResource($shift) : null,
                'attendance'        => $attendance ? new AttendanceResource($attendance) : null,
                'status'            => match (true) {
                    $shift === null                        => 'no_shift',
                    $shift->status !== 'approved'          => 'shift_not_approved',
                    $attendance?->checkout_at !== null     => 'completed',
                    $attendance?->checkin_at !== null      => 'checked_in',
                    default                                => 'not_checked_in',
                },
            ],
        ]);
    }

    /**
     * Dokumen Kepegawaian Saya
     *
     * Mengambil daftar Offering Letter, NDA, dan Kontrak Kerja PKWT/PKWTT.
     */
    public function documents(Request $request)
    {
        $user = $request->user();
        $docs = $user->documentInstances()
            ->with(['signingRequests'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $docs->map(function ($doc) use ($user) {
                return [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'type_name' => $doc->type_name,
                    'document_number' => $doc->document_number,
                    'version' => $doc->version,
                    'status' => $doc->status,
                    'file_hash' => $doc->file_hash,
                    'signed_at' => $doc->signed_at?->toIso8601String(),
                    'expires_at' => $doc->expires_at?->toIso8601String(),
                    'created_at' => $doc->created_at->toIso8601String(),
                    'can_sign' => $doc->status === 'menunggu_tanda_tangan',
                    'signers' => $doc->signingRequests->map(fn($sr) => [
                        'name' => $sr->signer_name,
                        'role' => $sr->signer_role,
                        'status' => $sr->status,
                        'signed_at' => $sr->signed_at?->toIso8601String(),
                    ]),
                ];
            }),
        ]);
    }

    /**
     * Detail Dokumen Kepegawaian
     */
    public function documentDetail(Request $request, $id)
    {
        $user = $request->user();
        $doc = $user->documentInstances()
            ->with(['signingRequests.events', 'hrApprovedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $doc->id,
                'type' => $doc->type,
                'type_name' => $doc->type_name,
                'document_number' => $doc->document_number,
                'version' => $doc->version,
                'status' => $doc->status,
                'rendered_content' => $doc->rendered_content,
                'file_hash' => $doc->file_hash,
                'data_snapshot' => $doc->data_snapshot,
                'signed_at' => $doc->signed_at?->toIso8601String(),
                'expires_at' => $doc->expires_at?->toIso8601String(),
                'hr_approved_at' => $doc->hr_approved_at?->toIso8601String(),
                'hr_approved_by' => $doc->hrApprovedBy?->name,
                'created_at' => $doc->created_at->toIso8601String(),
                'can_sign' => $doc->status === 'menunggu_tanda_tangan',
                'signers' => $doc->signingRequests->map(fn($sr) => [
                    'id' => $sr->id,
                    'name' => $sr->signer_name,
                    'role' => $sr->signer_role,
                    'email' => $sr->signer_email,
                    'status' => $sr->status,
                    'signed_at' => $sr->signed_at?->toIso8601String(),
                    'auth_method' => $sr->auth_method,
                    'ip_address' => $sr->ip_address,
                    'events' => $sr->events->map(fn($ev) => [
                        'event_type' => $ev->event_type,
                        'ip_address' => $ev->ip_address,
                        'created_at' => $ev->created_at->toIso8601String(),
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * Tanda Tangan Elektronik Dokumen
     */
    public function signDocument(Request $request, $id)
    {
        $user = $request->user();
        $doc = $user->documentInstances()->with('signingRequests')->findOrFail($id);

        abort_if($doc->status === 'ditandatangani', 422, 'Dokumen ini sudah selesai ditandatangani.');
        abort_if($doc->status !== 'menunggu_tanda_tangan', 422, 'Dokumen tidak dalam status siap ditandatangani.');

        $request->validate([
            'consent_agreed' => 'required|accepted',
            'signature_data' => 'nullable|string',
        ], [
            'consent_agreed.accepted' => 'Pernyataan persetujuan klausul hukum wajib disetujui.',
        ]);

        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $signedAt = now();

        $doc->update([
            'status' => 'ditandatangani',
            'signed_at' => $signedAt,
        ]);

        foreach ($doc->signingRequests as $sr) {
            if ($sr->signer_role === 'candidate' || $sr->signer_email === $user->email || count($doc->signingRequests) === 1) {
                $sr->update([
                    'status' => 'signed',
                    'signed_at' => $signedAt,
                    'signature_data' => $request->input('signature_data') ?: 'DIGITAL_CONSENT_ACKNOWLEDGED',
                    'auth_method' => 'mobile_app_sanctum_session',
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                ]);

                SignatureEvent::create([
                    'signing_request_id' => $sr->id,
                    'event_type' => 'signed_in_mobile_app',
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'payload' => [
                        'user_id' => $user->id,
                        'signed_at' => $signedAt->toIso8601String(),
                        'document_number' => $doc->document_number,
                        'file_hash' => $doc->file_hash,
                    ],
                ]);
            }
        }

        AuditLog::create([
            'entity_type' => 'document_instance',
            'entity_id' => $doc->id,
            'action' => 'employee_signed',
            'actor_id' => $user->id,
            'description' => "Dokumen {$doc->document_number} ditandatangani secara elektronik oleh karyawan {$user->name} via Mobile App.",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Dokumen {$doc->document_number} berhasil ditandatangani secara elektronik.",
            'data' => [
                'document_id' => $doc->id,
                'status' => 'ditandatangani',
                'signed_at' => $signedAt->toIso8601String(),
                'file_hash' => $doc->file_hash,
            ],
        ]);
    }

    /**
     * Struktur Tim & Atasan Karyawan
     */
    public function teamStructure(Request $request)
    {
        $user = $request->user()->load(['branch', 'position', 'manager.position', 'manager.branch']);

        $colleagues = User::where('branch_id', $user->branch_id)
            ->where('id', '!=', $user->id)
            ->where('active', true)
            ->with(['position', 'branch'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'my_node' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'position' => $user->position?->name ?? 'Staf',
                    'branch' => $user->branch?->name ?? 'Kantor Pusat',
                    'unit' => $user->unit ?? 'Operasional',
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role' => $user->role,
                ],
                'supervisor' => $user->manager ? [
                    'id' => $user->manager->id,
                    'name' => $user->manager->name,
                    'position' => $user->manager->position?->name ?? 'Manager Cabang',
                    'branch' => $user->manager->branch?->name ?? $user->branch?->name,
                    'email' => $user->manager->email,
                    'phone' => $user->manager->phone ?? '-',
                ] : null,
                'colleagues' => $colleagues->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'position' => $c->position?->name ?? 'Staf',
                    'branch' => $c->branch?->name ?? $user->branch?->name,
                    'unit' => $c->unit ?? 'Operasional',
                    'email' => $c->email,
                    'is_same_manager' => $c->manager_id === $user->manager_id,
                ]),
            ],
        ]);
    }

    /**
     * Status & Riwayat Onboarding Digital
     */
    public function onboardingStatus(Request $request)
    {
        $user = $request->user();
        $case = $user->activeOnboardingCase;

        if (!$case) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_onboarding' => false,
                    'is_completed' => true,
                    'status' => 'selesai',
                    'message' => 'Anda tidak memiliki proses onboarding aktif. Status kepegawaian Anda telah aktif.',
                ],
            ]);
        }

        $offerDoc = $case->offeringDocument;
        $ndaDoc = $case->ndaDocument;
        $latestKtp = $case->latestIdentitySubmission;
        $latestLiveness = $case->latestLivenessCheck;

        $steps = [
            [
                'step' => 1,
                'code' => 'offering_letter',
                'title' => 'Tanda Tangan Surat Penawaran (Offering Letter)',
                'status' => $offerDoc?->isSigned() ? 'completed' : ($case->status === 'menunggu_tanda_tangan_offer' ? 'current' : 'pending'),
                'is_signed' => (bool) $offerDoc?->isSigned(),
                'document_id' => $offerDoc?->id,
                'document_number' => $offerDoc?->document_number,
                'content' => $offerDoc?->rendered_content,
            ],
            [
                'step' => 2,
                'code' => 'nda',
                'title' => 'Tanda Tangan Perjanjian Kerahasiaan (NDA)',
                'status' => $ndaDoc?->isSigned() ? 'completed' : ($case->status === 'menunggu_tanda_tangan_nda' ? 'current' : ($offerDoc?->isSigned() ? 'ready' : 'locked')),
                'is_signed' => (bool) $ndaDoc?->isSigned(),
                'document_id' => $ndaDoc?->id,
                'document_number' => $ndaDoc?->document_number,
                'content' => $ndaDoc?->rendered_content,
            ],
            [
                'step' => 3,
                'code' => 'ktp',
                'title' => 'Unggah Identitas & Foto KTP',
                'status' => $latestKtp ? ($latestKtp->status === 'approved' ? 'completed' : 'submitted') : ($case->status === 'menunggu_ktp' ? 'current' : ($ndaDoc?->isSigned() ? 'ready' : 'locked')),
                'masked_nik' => $latestKtp?->id_number_masked ?? $user->getMaskedNik(),
            ],
            [
                'step' => 4,
                'code' => 'liveness',
                'title' => 'Pemeriksaan Wajah (Liveness Verification)',
                'status' => $latestLiveness ? ($latestLiveness->result === 'passed' ? 'completed' : 'submitted') : ($case->status === 'menunggu_liveness' ? 'current' : ($latestKtp ? 'ready' : 'locked')),
                'result' => $latestLiveness?->result,
            ],
            [
                'step' => 5,
                'code' => 'verification',
                'title' => 'Verifikasi Akhir oleh Tim HR',
                'status' => $case->status === 'selesai' ? 'completed' : ($case->status === 'perlu_verifikasi' ? 'current' : 'pending'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'has_onboarding' => true,
                'case_id' => $case->id,
                'status' => $case->status,
                'token' => $case->token,
                'salary_offered' => $case->salary_offered,
                'start_date' => $case->start_date?->toDateString(),
                'branch' => $case->branch?->name,
                'position' => $case->position?->name,
                'supervisor' => $case->supervisor?->name,
                'steps' => $steps,
            ],
        ]);
    }

    /**
     * Tanda Tangan Offering Letter Onboarding
     */
    public function onboardingSignOffer(Request $request)
    {
        $user = $request->user();
        $case = $user->activeOnboardingCase;
        abort_unless($case, 404, 'Kasus onboarding tidak ditemukan.');

        $request->validate([
            'consent_agreed' => 'required|accepted',
            'signature_data' => 'nullable|string',
        ]);

        app(OnboardingService::class)->signOfferingLetter($case, [
            'signature_data' => $request->input('signature_data') ?: 'MOBILE_APP_OFFER_CONSENT',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_metadata' => [
                'platform' => 'mobile',
                'signed_timestamp' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offering Letter berhasil ditandatangani. Lanjutkan ke tahap NDA.',
        ]);
    }

    /**
     * Tanda Tangan NDA Onboarding
     */
    public function onboardingSignNda(Request $request)
    {
        $user = $request->user();
        $case = $user->activeOnboardingCase;
        abort_unless($case, 404, 'Kasus onboarding tidak ditemukan.');

        $request->validate([
            'consent_agreed' => 'required|accepted',
            'signature_data' => 'nullable|string',
        ]);

        app(OnboardingService::class)->signNda($case, [
            'signature_data' => $request->input('signature_data') ?: 'MOBILE_APP_NDA_CONSENT',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_metadata' => [
                'platform' => 'mobile',
                'signed_timestamp' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'NDA berhasil ditandatangani. Silakan unggah berkas KTP.',
        ]);
    }

    /**
     * Unggah KTP Onboarding
     */
    public function onboardingUploadKtp(Request $request)
    {
        $user = $request->user();
        $case = $user->activeOnboardingCase;
        abort_unless($case, 404, 'Kasus onboarding tidak ditemukan.');

        $request->validate([
            'id_number' => 'required|string|min:16|max:20',
            'full_name' => 'required|string|max:255',
            'ktp_file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ]);

        $submission = app(OnboardingService::class)->submitKtp(
            $case,
            $request->file('ktp_file'),
            $request->input('id_number'),
            $request->input('full_name')
        );

        return response()->json([
            'success' => true,
            'message' => 'KTP berhasil diunggah dengan aman. Silakan lakukan pengambilan foto wajah liveness.',
            'data' => [
                'submission_id' => $submission->id,
                'id_number_masked' => $submission->id_number_masked,
            ],
        ]);
    }

    /**
     * Pengambilan Foto Wajah Liveness
     */
    public function onboardingSubmitLiveness(Request $request)
    {
        $user = $request->user();
        $case = $user->activeOnboardingCase;
        abort_unless($case, 404, 'Kasus onboarding tidak ditemukan.');

        $request->validate([
            'selfie_file' => 'required|file|mimes:jpeg,jpg,png,webp|max:10240',
            'manual_alternative' => 'nullable|boolean',
        ]);

        $check = app(OnboardingService::class)->submitLiveness(
            $case,
            $request->file('selfie_file'),
            [
                'manual_alternative_requested' => (bool) $request->input('manual_alternative'),
                'platform' => 'mobile',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pemeriksaan foto wajah berhasil diajukan. Berkas Anda sedang dalam verifikasi akhir oleh HR.',
            'data' => [
                'liveness_check_id' => $check->id,
                'result' => $check->result,
            ],
        ]);
    }
}
