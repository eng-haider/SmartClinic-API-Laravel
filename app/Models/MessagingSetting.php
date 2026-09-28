<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessagingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_business_account_id',
        'whatsapp_webhook_verify_token',
        'is_active',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
            'whatsapp_access_token' => 'encrypted',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
