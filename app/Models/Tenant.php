<?php

namespace App\Models;

use App\Services\SpecialtyManager;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Support\Facades\Log;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'specialty',
        'address',
        'rx_img',
        'whatsapp_template_sid',
        'whatsapp_message_count',
        'whatsapp_phone',
        'show_image_case',
        'doctor_mony',
        'teeth_v2',
        'send_msg',
        'show_rx_id',
        'logo',
        'api_whatsapp',
        'has_ai_bot',
        'data',
        // Hostinger database credentials (one user per database)
        'db_name',
        'db_username',
        'db_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'db_password',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure ID is set before creating database
        static::creating(function ($tenant) {
            Log::info('BOOT Creating - ID BEFORE: ' . var_export($tenant->id, true));
            Log::info('BOOT Creating - Attributes: ', $tenant->getAttributes());
            
            if (empty($tenant->id)) {
                // Generate a random ID if not provided
                $generatedId = '_' . \Illuminate\Support\Str::random(8);
                $tenant->id = $generatedId;
                $tenant->setAttribute('id', $generatedId);
                Log::info('BOOT Creating - Generated ID: ' . $tenant->id);
            }
            
            // Force set the ID attribute multiple ways to ensure it's there
            $currentId = $tenant->id;
            $tenant->setAttribute('id', $currentId);
            $tenant->attributes['id'] = $currentId;
            
            Log::info('BOOT Creating - FINAL ID: ' . var_export($tenant->id, true));
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_message_count' => 'integer',
            'doctor_mony' => 'integer',
            'show_image_case' => 'boolean',
            'teeth_v2' => 'boolean',
            'send_msg' => 'boolean',
            'show_rx_id' => 'boolean',
            'api_whatsapp' => 'boolean',
            'has_ai_bot' => 'boolean',
            'data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get custom columns for the tenant.
     * These columns will be stored directly in the tenants table.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'specialty',
            'address',
            'rx_img',
            'whatsapp_template_sid',
            'whatsapp_message_count',
            'whatsapp_phone',
            'show_image_case',
            'doctor_mony',
            'teeth_v2',
            'send_msg',
            'show_rx_id',
            'logo',
            'api_whatsapp',
            'has_ai_bot',
        ];
    }

    // ========================================
    // Specialty & Feature Methods
    // ========================================

    /**
     * Check if this tenant is a dental clinic.
     */
    public function isDental(): bool
    {
        return ($this->specialty ?? 'dental') === 'dental';
    }

    /**
     * Get the tenant's specialty (defaults to 'dental').
     */
    public function getSpecialty(): string
    {
        return $this->specialty ?? 'dental';
    }

    /**
     * Get the tenant's feature flags.
     */
    public function features()
    {
        return $this->hasMany(TenantFeature::class, 'tenant_id');
    }

    /**
     * Check if a specific feature is enabled for this tenant.
     * Falls back to specialty handler defaults if not set in DB.
     */
    public function hasFeature(string $featureKey): bool
    {
        $feature = $this->features()
            ->where('feature_key', $featureKey)
            ->first();

        if ($feature) {
            return $feature->is_enabled;
        }

        // Fall back to specialty handler defaults
        $defaults = SpecialtyManager::handler($this->getSpecialty())->defaultFeatures();
        return $defaults[$featureKey] ?? false;
    }
}
