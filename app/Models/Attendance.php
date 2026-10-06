<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'user_id',
        'checkin_at',
        'checkout_at',
        'status',
        'late_minutes',
        'late_units',
        'overtime_minutes',
        'overtime_approved_by',
        'checkin_evidence',
        'checkout_evidence',
        'flags',
    ];

    protected function casts(): array
    {
        return [
            'checkin_at' => 'datetime',
            'checkout_at' => 'datetime',
            'late_minutes' => 'integer',
            'late_units' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? '';
    }

    public function getStartAtAttribute(): ?Carbon
    {
        return $this->shift?->start_at;
    }

    public function getEndAtAttribute(): ?Carbon
    {
        return $this->shift?->end_at;
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overtimeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overtime_approved_by');
    }
}
