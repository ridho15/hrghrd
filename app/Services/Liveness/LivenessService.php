<?php

namespace App\Services\Liveness;

use App\Models\LivenessCheck;
use App\Models\OnboardingCase;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LivenessService
{
    public function __construct(
        protected SystemBiometricAdapter $adapter
    ) {}

    public function processFaceLiveness(OnboardingCase $case, UploadedFile $file, array $clientMetadata = []): LivenessCheck
    {
        $evaluation = $this->adapter->evaluate($file, $clientMetadata);

        // Store file strictly in private storage
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs(
            'onboarding/liveness/' . date('Y/m'),
            'selfie_' . Str::random(32) . '.' . $file->getClientOriginalExtension(),
            'local'
        );

        $check = LivenessCheck::create([
            'onboarding_case_id' => $case->id,
            'user_id' => $case->user_id,
            'provider' => 'system_biometric_adapter',
            'selfie_path' => $path,
            'selfie_hash' => $hash,
            'liveness_score' => $evaluation['score'],
            'result' => $evaluation['result'],
            'spoof_detected' => $evaluation['spoof_detected'],
            'anti_spoofing_metrics' => $evaluation['metrics'],
            'status' => $evaluation['result'] === 'lulus' ? 'passed' : ($evaluation['result'] === 'gagal' ? 'failed' : 'manual_review'),
            'review_notes' => $evaluation['notes'],
        ]);

        Audit::record('liveness_check', $check->id, 'evaluate', "Pemeriksaan biometrik wajah selesai dengan hasil: {$check->result} (Skor: {$check->liveness_score})");

        return $check;
    }
}
