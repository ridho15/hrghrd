<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivenessCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_case_id',
        'user_id',
        'provider',
        'selfie_path',
        'selfie_hash',
        'liveness_score',
        'result',
        'spoof_detected',
        'anti_spoofing_metrics',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'liveness_score' => 'float',
            'spoof_detected' => 'boolean',
            'anti_spoofing_metrics' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function onboardingCase(): BelongsTo
    {
        return $this->belongsTo(OnboardingCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPassed(): bool
    {
        return $this->result === 'lulus';
    }

    public function isManualReview(): bool
    {
        return $this->result === 'perlu_tinjau';
    }
}
