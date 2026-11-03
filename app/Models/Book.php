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

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)->withPivot('position');
    }

    /** Replaces the writers with a comma separated list, keeping the typed order. */
    public function setAuthors(?string $names): void
    {
        $ids = Author::resolve($names);
        $sync = $ids ? array_combine($ids, array_map(fn ($i) => ['position' => $i], array_keys($ids))) : [];
        $this->authors()->sync($sync);
        $this->forceFill(['author' => null])->save(); // the old single-text column is no longer used
    }

    /** @return string[] writers in order (falls back to the old single "author" text if there is no list yet) */
    public function authorNames(): array
    {
        if ($this->relationLoaded('authors') && $this->authors->isNotEmpty()) {
            return $this->authors->sortBy('pivot.position')->pluck('name')->values()->all();
        }
        $legacy = trim((string) ($this->attributes['author'] ?? ''));

        return $legacy === '' ? [] : [$legacy];
    }

    public function authorLine(): string
    {
        return implode(', ', $this->authorNames());
    }
}
