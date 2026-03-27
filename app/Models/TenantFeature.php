<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TenantFeature - Per-tenant feature flags.
 *
 * Stored in the central database (same as tenants table).
 * Allows enabling/disabling specialty modules per tenant.
 *
 * Examples:
 *   tenant_id: 'haider', feature_key: 'tooth_chart', is_enabled: true
 *   tenant_id: 'haider', feature_key: 'xray_analysis', is_enabled: true
 */
class TenantFeature extends Model
{
    /**
     * Always use central database (same as tenants table).
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'feature_key',
        'is_enabled',
        'config',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns this feature flag.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope: only enabled features.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: filter by feature key.
     */
    public function scopeForFeature($query, string $featureKey)
    {
        return $query->where('feature_key', $featureKey);
    }
}
