<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Book extends Model
{
    protected $guarded = [];

    // never leak storage details or the wrapped key by accident
    protected $hidden = ['key_enc', 'postfile_id', 'postfile_url', 'nonce_prefix', 'cover_data'];

    protected $casts = [
        'is_published' => 'boolean',
        'plain_size' => 'integer',
        'enc_size' => 'integer',
        'chunk_size' => 'integer',
        'pages' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
}
