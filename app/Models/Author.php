<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    protected $guarded = [];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }

    /**
     * "Ann Lee, Bo Chen" -> [id, id] in the order typed. Names that do not exist yet are created
     * (matched case-insensitively, so "ann lee" reuses "Ann Lee").
     */
    public static function resolve(?string $names): array
    {
        $ids = [];
        foreach (preg_split('/[,،;\n]+/u', (string) $names) as $name) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $name));
            if ($name === '') {
                continue;
            }
            $name = mb_substr($name, 0, 120);
            $author = static::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                ?? static::create(['name' => $name]);
            $ids[$author->id] = true;
        }

        return array_keys($ids);
    }
}
