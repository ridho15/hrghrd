<?php

namespace App\Http\Controllers;

use App\Models\OnboardingCase;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class CandidatePortalController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    public function portal(string $token)
    {
        $case = OnboardingCase::with([
            'user',
            'branch',
            'position',
            'supervisor',
            'documents.signingRequests',
            'latestIdentitySubmission',
            'latestLivenessCheck',
        ])->where('token', $token)->firstOrFail();

        $offeringDoc = $case->offeringDocument;
        $ndaDoc = $case->ndaDocument;

        return view('onboarding.portal', compact('case', 'offeringDoc', 'ndaDoc'));
    }

    public function signOffer(Request $request, string $token)
    {
        $case = OnboardingCase::where('token', $token)->firstOrFail();

        $request->validate([
            'consent_agreed' => 'required|accepted',
            'signature_data' => 'nullable|string',
        ], [
            'consent_agreed.accepted' => 'Anda wajib menyetujui pernyataan persetujuan tanda tangan elektronik.',
        ]);

        $this->onboardingService->signOfferingLetter($case, [
            'signature_data' => $request->input('signature_data') ?: 'DIGITAL_CONSENT_CONFIRMED',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_metadata' => [
                'viewport' => $request->header('Sec-Ch-Ua-Mobile') ? 'mobile' : 'desktop',
                'signed_timestamp' => now()->toIso8601String(),
            ],
        ]);

        return redirect()->route('onboarding.portal', $token)
            ->with('ok', 'Offering Letter berhasil ditandatangani secara elektronik. Silakan lanjutkan ke penandatanganan Perjanjian Kerahasiaan (NDA).');
    }

    public function signNda(Request $request, string $token)
    {
        $case = OnboardingCase::where('token', $token)->firstOrFail();

        $request->validate([
            'consent_agreed' => 'required|accepted',
            'signature_data' => 'nullable|string',
        ], [
            'consent_agreed.accepted' => 'Anda wajib menyetujui pernyataan persetujuan tanda tangan elektronik NDA.',
        ]);

        $this->onboardingService->signNda($case, [
            'signature_data' => $request->input('signature_data') ?: 'NDA_DIGITAL_CONSENT_CONFIRMED',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_metadata' => [
                'viewport' => $request->header('Sec-Ch-Ua-Mobile') ? 'mobile' : 'desktop',
                'signed_timestamp' => now()->toIso8601String(),
            ],
        ]);

        return redirect()->route('onboarding.portal', $token)
            ->with('ok', 'Perjanjian Kerahasiaan (NDA) berhasil ditandatangani. Silakan unggah dokumen KTP Anda.');
    }

    public function submitKtp(Request $request, string $token)
    {
        $case = OnboardingCase::where('token', $token)->firstOrFail();

        $request->validate([
            'id_number' => 'required|string|min:16|max:20',
            'full_name' => 'required|string|max:255',
            'ktp_file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ], [
            'id_number.required' => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'full_name.required' => 'Nama lengkap sesuai KTP wajib diisi.',
            'ktp_file.required' => 'Berkas foto KTP wajib diunggah.',
            'ktp_file.mimes' => 'Format berkas KTP harus JPG, PNG, atau PDF.',
            'ktp_file.max' => 'Ukuran berkas KTP maksimal 5 MB.',
        ]);

        $this->onboardingService->submitKtp(
            $case,
            $request->file('ktp_file'),
            $request->input('id_number'),
            $request->input('full_name')
        );

        return redirect()->route('onboarding.portal', $token)
            ->with('ok', 'KTP berhasil diunggah dengan aman. Silakan lakukan pengambilan foto wajah liveness.');
    }

    public function submitLiveness(Request $request, string $token)
    {
        $case = OnboardingCase::where('token', $token)->firstOrFail();

        $request->validate([
            'selfie_file' => 'required|file|mimes:jpeg,jpg,png,webp|max:10240',
            'manual_alternative' => 'nullable|boolean',
        ], [
            'selfie_file.required' => 'Foto wajah wajib diambil menggunakan kamera.',
            'selfie_file.mimes' => 'Format foto wajah harus JPG atau PNG.',
            'selfie_file.max' => 'Ukuran foto maksimal 10 MB.',
        ]);

        $this->onboardingService->submitLiveness(
            $case,
            $request->file('selfie_file'),
            ['manual_alternative_requested' => (bool) $request->input('manual_alternative')]
        );

        return redirect()->route('onboarding.portal', $token)
            ->with('ok', 'Pemeriksaan foto wajah liveness berhasil diajukan. Seluruh berkas Anda kini sedang dalam verifikasi akhir oleh tim HR.');
    }
}
