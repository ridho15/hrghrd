<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'position_id',
        'supervisor_id',
        'status',
        'salary_offered',
        'benefits',
        'start_date',
        'offer_valid_until',
        'token',
        'token_expires_at',
        'correction_notes',
        'rejection_reason',
        'hr_approver_id',
        'approved_by_hr_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'salary_offered' => 'integer',
            'benefits' => 'array',
            'start_date' => 'date',
            'offer_valid_until' => 'date',
            'token_expires_at' => 'datetime',
            'approved_by_hr_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approver_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentInstance::class);
    }

    public function identitySubmissions(): HasMany
    {
        return $this->hasMany(IdentitySubmission::class)->orderByDesc('id');
    }

    public function latestIdentitySubmission()
    {
        return $this->hasOne(IdentitySubmission::class)->latestOfMany();
    }

    public function livenessChecks(): HasMany
    {
        return $this->hasMany(LivenessCheck::class)->orderByDesc('id');
    }

    public function latestLivenessCheck()
    {
        return $this->hasOne(LivenessCheck::class)->latestOfMany();
    }

    public function offeringDocument()
    {
        return $this->hasOne(DocumentInstance::class)->ofMany([
            'id' => 'max',
        ], function ($query) {
            $query->where('type', 'offering_letter');
        });
    }

    public function ndaDocument()
    {
        return $this->hasOne(DocumentInstance::class)->ofMany([
            'id' => 'max',
        ], function ($query) {
            $query->where('type', 'nda');
        });
    }

    public function contractDocument()
    {
        return $this->hasOne(DocumentInstance::class)->ofMany([
            'id' => 'max',
        ], function ($query) {
            $query->where('type', 'employment_contract');
        });
    }

    public function isExpired(): bool
    {
        return ($this->offer_valid_until && $this->offer_valid_until->isPast() && !in_array($this->status, ['selesai', 'dibatalkan', 'ditolak']))
            || ($this->token_expires_at && $this->token_expires_at->isPast() && !in_array($this->status, ['selesai', 'dibatalkan', 'ditolak']));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draf Pengajuan',
            'menunggu_persetujuan_hr' => 'Menunggu Review HR',
            'menunggu_tanda_tangan_offer' => 'Menunggu Tanda Tangan Offer',
            'menunggu_tanda_tangan_nda' => 'Menunggu Tanda Tangan NDA',
            'menunggu_ktp' => 'Menunggu Unggah KTP',
            'menunggu_liveness' => 'Menunggu Liveness Foto Wajah',
            'perlu_verifikasi' => 'Perlu Verifikasi Berkas & Wajah',
            'selesai' => 'Onboarding Selesai (Aktif)',
            'ditolak' => 'Ditolak',
            'kedaluwarsa' => 'Kedaluwarsa',
            'dibatalkan' => 'Dibatalkan',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'selesai' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'perlu_verifikasi' => 'bg-amber-50 text-amber-800 border-amber-200',
            'menunggu_tanda_tangan_offer', 'menunggu_tanda_tangan_nda' => 'bg-blue-50 text-blue-800 border-blue-200',
            'menunggu_ktp', 'menunggu_liveness' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            'menunggu_persetujuan_hr' => 'bg-purple-50 text-purple-800 border-purple-200',
            'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
            'ditolak', 'kedaluwarsa', 'dibatalkan' => 'bg-rose-50 text-rose-800 border-rose-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }
}
