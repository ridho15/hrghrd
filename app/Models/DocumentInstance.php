<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_case_id',
        'user_id',
        'type',
        'document_template_version_id',
        'document_number',
        'version',
        'status',
        'rendered_content',
        'file_path',
        'file_hash',
        'data_snapshot',
        'missing_fields',
        'review_flags',
        'ready_for_review',
        'hr_approved_at',
        'hr_approved_by',
        'signed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'data_snapshot' => 'array',
            'missing_fields' => 'array',
            'review_flags' => 'array',
            'ready_for_review' => 'boolean',
            'hr_approved_at' => 'datetime',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'document_template_version_id');
    }

    public function hrApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function signingRequests(): HasMany
    {
        return $this->hasMany(SigningRequest::class)->orderBy('signing_order');
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'offering_letter' => 'Offering Letter',
            'nda' => 'Non-Disclosure Agreement (NDA)',
            'employment_contract' => 'Kontrak Kerja (PKWT/PKWTT)',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function isSigned(): bool
    {
        return $this->status === 'ditandatangani';
    }

    public function isPendingSignature(): bool
    {
        return $this->status === 'menunggu_tanda_tangan';
    }
}
