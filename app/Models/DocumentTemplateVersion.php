<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_template_id',
        'version_number',
        'status',
        'content',
        'clauses',
        'required_variables',
        'required_clauses',
        'change_summary',
        'created_by_id',
        'reviewed_by_id',
        'approved_by_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'clauses' => 'array',
            'required_variables' => 'array',
            'required_clauses' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(DocumentInstance::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInReview(): bool
    {
        return $this->status === 'in_review';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
