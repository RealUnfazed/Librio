<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $guarded = [];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }

    /**
     * Ticked category ids + brand-new names typed in a comma separated box.
     * New names are created on the spot. Returns the final list of ids.
     */
    public static function resolve(array $ids, ?string $new): array
    {
        $out = array_map('intval', array_filter($ids));

        foreach (preg_split('/[,،;\n]+/u', (string) $new) as $name) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $name));
            if ($name === '') {
                continue;
            }
            $out[] = static::firstOrCreate(['name' => mb_substr($name, 0, 60)])->id;
        }

        return array_values(array_unique($out));
    }
}
