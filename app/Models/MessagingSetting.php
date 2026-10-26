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

    /**
     * WhatsApp goes through Meta's Cloud API unless meta.driver picks Gupshup.
     * For Gupshup, whatsapp_access_token holds the Gupshup API key and the app
     * details live in meta (gupshup_app_name, gupshup_app_id, gupshup_source_number).
     */
    public function usesGupshup(): bool
    {
        return $this->provider === 'whatsapp' && ($this->meta['driver'] ?? null) === 'gupshup';
    }
}
