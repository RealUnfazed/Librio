<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    protected $table = 'upload_sessions';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
}
