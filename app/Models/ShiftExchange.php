<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftExchange extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'branch_id',
        'requester_id',
        'requester_shift_id',
        'replacement_id',
        'target_shift_id',
        'target_date',
        'reason',
        'replacement_note',
        'review_note',
        'status',
        'peer_responded_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date'       => 'date:Y-m-d',
            'peer_responded_at' => 'datetime',
            'reviewed_at'       => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_id');
    }

    public function requesterShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'requester_shift_id');
    }

    public function targetShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'target_shift_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending_peer', 'pending_manager']);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('requester_id', $userId)
                ->orWhere('replacement_id', $userId);
        });
    }

    public function scopeOpenSlot($query)
    {
        return $query->whereIn('type', ['change_shift', 'change_off_day']);
    }

    public function getIsOpenSlotAttribute(): bool
    {
        return in_array($this->type, ['change_shift', 'change_off_day'], true);
    }

    public function getTargetPositionNameAttribute(): ?string
    {
        return $this->targetShift?->position?->name
            ?? ($this->targetShift?->user?->position?->name ?? $this->requester?->position?->name ?? 'Staf');
    }
}
