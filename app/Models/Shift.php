<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'start_at',
        'end_at',
        'status',
        'version',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'version' => 'integer',
            'approved_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AttendanceException::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function getBranchNameAttribute(): string
    {
        return $this->branch?->name ?? '';
    }

    public function getAttendanceStatusAttribute(): ?string
    {
        return $this->attendance?->status;
    }

    public function getCheckinAtAttribute(): mixed
    {
        return $this->attendance?->checkin_at;
    }

    public function getCheckoutAtAttribute(): mixed
    {
        return $this->attendance?->checkout_at;
    }

    public function getEmployeeNameAttribute(): string
    {
        return $this->user?->name ?? '';
    }
}

