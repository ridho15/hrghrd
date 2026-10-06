<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveDay extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'leave_request_id',
        'date',
        'status',
        'paid',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
