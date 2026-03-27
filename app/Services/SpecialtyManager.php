<?php

namespace App\Services;

use App\Contracts\SpecialtyHandlerInterface;
use App\Models\TenantFeature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SpecialtyManager
 *
 * Central service that resolves the correct specialty handler
 * based on the current tenant's specialty setting.
 *
 * Usage:
 *   SpecialtyManager::handler()->validationRules()
 *   SpecialtyManager::handler()->resourceFields($encounter)
 *   SpecialtyManager::isDental()
 *   SpecialtyManager::hasFeature('tooth_chart')
 *
 * Resolution flow:
 *   1. Read tenant('specialty') from current tenant
 *   2. Look up handler class in HANDLERS registry
 *   3. Resolve via Laravel service container
 *   4. Fallback to GeneralEncounterHandler if not found
 */
class SpecialtyManager
{
    /**
     * Registry: specialty key => handler class.
     * Add new specialties here as they are implemented.
     */
    private const HANDLERS = [
        'dental'  => \App\Modules\Dental\DentalEncounterHandler::class,
        'general' => \App\Modules\General\GeneralEncounterHandler::class,
        // Future:
        // 'ophthalmology' => \App\Modules\Ophthalmology\OphthalmologyEncounterHandler::class,
        // 'dermatology'   => \App\Modules\Dermatology\DermatologyEncounterHandler::class,
    ];

    /**
     * Human-readable labels for each specialty.
     */
    private const LABELS = [
        'dental'        => 'Dental Clinic',
        'ophthalmology' => 'Ophthalmology Clinic',
        'dermatology'   => 'Dermatology Clinic',
        'general'       => 'General Medical Clinic',
    ];

    /**
     * Get the handler for the current tenant's specialty.
     *
     * @param string|null $specialty Override specialty (null = read from tenant)
     * @return SpecialtyHandlerInterface
     */
    public static function handler(?string $specialty = null): SpecialtyHandlerInterface
    {
        $specialty = $specialty ?? static::currentSpecialty();
        $handlerClass = self::HANDLERS[$specialty] ?? self::HANDLERS['general'];

        try {
            return app($handlerClass);
        } catch (\Throwable $e) {
            Log::warning("SpecialtyManager: Failed to resolve handler for '{$specialty}', using GeneralHandler", [
                'error' => $e->getMessage(),
            ]);
            return app(\App\Modules\General\GeneralEncounterHandler::class);
        }
    }

    /**
     * Get the current tenant's specialty.
     * Defaults to 'dental' for backward compatibility.
     */
    public static function currentSpecialty(): string
    {
        return tenant('specialty') ?? 'dental';
    }

    /**
     * Check if the current tenant is a dental clinic.
     */
    public static function isDental(): bool
    {
        return static::currentSpecialty() === 'dental';
    }

    /**
     * Check if the current tenant has a specific feature enabled.
     * Uses 1-minute cache per tenant to avoid repeated DB queries.
     *
     * @param string $feature Feature key (e.g. 'tooth_chart', 'xray_analysis')
     * @return bool
     */
    public static function hasFeature(string $feature): bool
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            return false;
        }

        $cacheKey = "tenant_features:{$tenantId}";

        $features = Cache::remember($cacheKey, 60, function () use ($tenantId) {
            return TenantFeature::where('tenant_id', $tenantId)
                ->pluck('is_enabled', 'feature_key')
                ->toArray();
        });

        // If feature exists in DB, use its value
        if (isset($features[$feature])) {
            return (bool) $features[$feature];
        }

        // If not in DB, check handler defaults (dental has tooth_chart by default)
        $defaults = static::handler()->defaultFeatures();
        return $defaults[$feature] ?? false;
    }

    /**
     * Get the human-readable label for a specialty.
     */
    public static function label(?string $specialty = null): string
    {
        $specialty = $specialty ?? static::currentSpecialty();
        return self::LABELS[$specialty] ?? 'Medical Clinic';
    }

    /**
     * Get all registered specialty keys.
     */
    public static function allSpecialties(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * Get all specialties as key => label pairs.
     */
    public static function specialtyOptions(): array
    {
        return self::LABELS;
    }

    /**
     * Check if a specialty key is valid/registered.
     */
    public static function isValidSpecialty(string $specialty): bool
    {
        return array_key_exists($specialty, self::LABELS);
    }

    /**
     * Clear the cached features for a tenant.
     * Call this after updating tenant_features.
     */
    public static function clearFeatureCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? tenant('id');
        if ($tenantId) {
            Cache::forget("tenant_features:{$tenantId}");
        }
    }
}
