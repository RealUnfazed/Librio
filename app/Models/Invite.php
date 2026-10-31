<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];

    public function isUsable(): bool
    {
        return $this->uses < $this->max_uses && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
