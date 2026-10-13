<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_leave_id',
        'created_by',
        'type',
        'start_date',
        'end_date',
        'reason',
        'certificate_path',
        'certificate_name',
        'status',
        'vacate_shifts',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'vacate_shifts' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function originalLeave(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_leave_id');
    }

    public function replacementRequests(): HasMany
    {
        return $this->hasMany(self::class, 'original_leave_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function days(): HasMany
    {
        return $this->hasMany(LeaveDay::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function getEmployeeNameAttribute(): string
    {
        return $this->user?->name ?? '';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'sick' => 'Izin Sakit',
            'day_off' => 'Pengajuan Hari Libur',
            'leave_exchange' => 'Pergantian Cuti',
            default => 'Cuti Tahunan / Izin',
        };
    }

    public function getIsDayOffAttribute(): bool
    {
        return $this->type === 'day_off';
    }

    public function getIsLeaveExchangeAttribute(): bool
    {
        return $this->type === 'leave_exchange';
    }

    public function getIsSickAttribute(): bool
    {
        return $this->type === 'sick';
    }
}

