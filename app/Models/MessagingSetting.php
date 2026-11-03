<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessagingSetting extends Model
{
    use HasFactory;

    // The token (Meta access token or Gupshup API key) is write-only: it is never
    // sent back to the browser, only whether one is saved and its last characters.
    protected $hidden = ['whatsapp_access_token'];

    protected $appends = ['has_access_token', 'access_token_hint'];

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

    protected function hasAccessToken(): Attribute
    {
        return Attribute::get(fn () => filled($this->whatsapp_access_token));
    }

    protected function accessTokenHint(): Attribute
    {
        return Attribute::get(fn () => filled($this->whatsapp_access_token)
            ? '••••' . substr($this->whatsapp_access_token, -4)
            : null);
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

    /**
     * The saved WhatsApp token, if it belongs to the given driver: Meta's token and
     * Gupshup's key share one column, so after switching driver the old one is useless.
     */
    public static function savedWhatsAppToken(?string $driver): ?string
    {
        $setting = static::where('provider', 'whatsapp')->first();

        if (! $setting || ($setting->meta['driver'] ?? 'meta') !== ($driver ?: 'meta')) {
            return null;
        }

        return $setting->whatsapp_access_token ?: null;
    }
}
