<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'shift_id',
        'action',
        'result',
        'reason',
        'evidence',
        'server_at',
    ];

    protected function casts(): array
    {
        return [
            'server_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? '';
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
