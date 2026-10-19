<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'code',
        'entity_name',
        'branch_id',
        'position_id',
        'active_version_id',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class)->orderByDesc('version_number');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'active_version_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(DocumentInstance::class);
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
}
