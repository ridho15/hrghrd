<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrGroupLink extends Model
{
    protected $fillable = ['user_id', 'hrg_sub'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
