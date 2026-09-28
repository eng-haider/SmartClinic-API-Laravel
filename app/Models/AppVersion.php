<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'version',
        'build_number',
        'force_update',
        'apk_url',
        'message',
        'is_active',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'build_number' => 'integer',
            'force_update' => 'boolean',
            'is_active' => 'boolean',
            'released_at' => 'datetime',
        ];
    }
}
