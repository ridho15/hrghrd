<?php

namespace App\Services\Liveness;

use Illuminate\Http\UploadedFile;

interface LivenessAdapterInterface
{
    /**
     * Evaluate face-only image for liveness and anti-spoofing.
     *
     * @return array [
     *   'score' => float, // 0.0 to 100.0
     *   'result' => string, // 'lulus', 'gagal', 'perlu_tinjau'
     *   'spoof_detected' => bool,
     *   'metrics' => array,
     *   'notes' => string,
     * ]
     */
    public function evaluate(UploadedFile $file, array $clientMetadata = []): array;
}
