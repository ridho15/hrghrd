<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'signing_request_id',
        'event_type',
        'ip_address',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function signingRequest(): BelongsTo
    {
        return $this->belongsTo(SigningRequest::class);
    }
}
