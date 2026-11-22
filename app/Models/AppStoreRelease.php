<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The phone app's live version on one store (central table). */
class AppStoreRelease extends Model
{
    protected $fillable = [
        'platform',
        'version',
        'build_number',
        'min_build_number',
        'store_url',
        'message_ar',
        'message_en',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'build_number' => 'integer',
            'min_build_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
