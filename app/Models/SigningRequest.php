<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SigningRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_instance_id',
        'provider',
        'signing_order',
        'signer_role',
        'signer_name',
        'signer_email',
        'signer_phone',
        'token',
        'status',
        'signed_at',
        'signature_data',
        'auth_method',
        'ip_address',
        'user_agent',
        'device_metadata',
        'evidence_pack_path',
    ];

    protected function casts(): array
    {
        return [
            'signing_order' => 'integer',
            'signed_at' => 'datetime',
            'device_metadata' => 'array',
        ];
    }

    public function documentInstance(): BelongsTo
    {
        return $this->belongsTo(DocumentInstance::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SignatureEvent::class)->orderBy('created_at');
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }
}
