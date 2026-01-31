<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\SettingDefinition;
use Illuminate\Support\Facades\DB;

/**
 * ClinicSettingService
 * 
 * Handles automatic creation of clinic settings and synchronization
 * between setting definitions and clinic settings.
 */
class ClinicSettingService
{
    /**
     * Create default settings for a new clinic.
     * Called when a new clinic is registered.
     */
    public function createDefaultSettingsForClinic(Clinic $clinic): void
    {
        $definitions = SettingDefinition::active()->get();

        foreach ($definitions as $definition) {
            ClinicSetting::firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'setting_key' => $definition->setting_key,
                ],
                [
                    'setting_value' => $definition->default_value,
                    'setting_type' => $definition->setting_type,
                    'description' => $definition->description,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Sync a new setting definition to all existing clinics.
     * Called when Super Admin adds a new setting key.
     */
    public function syncDefinitionToAllClinics(SettingDefinition $definition): int
    {
        $clinics = Clinic::all();
        $count = 0;

        foreach ($clinics as $clinic) {
            $created = ClinicSetting::firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'setting_key' => $definition->setting_key,
                ],
                [
                    'setting_value' => $definition->default_value,
                    'setting_type' => $definition->setting_type,
                    'description' => $definition->description,
                    'is_active' => true,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync all active definitions to a specific clinic.
     * Useful for ensuring a clinic has all settings.
     */
    public function syncAllDefinitionsToClinic(Clinic $clinic): int
    {
        $definitions = SettingDefinition::active()->get();
        $count = 0;

        foreach ($definitions as $definition) {
            $created = ClinicSetting::firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'setting_key' => $definition->setting_key,
                ],
                [
                    'setting_value' => $definition->default_value,
                    'setting_type' => $definition->setting_type,
                    'description' => $definition->description,
                    'is_active' => true,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove a setting from all clinics when definition is deleted.
     */
    public function removeSettingFromAllClinics(string $settingKey): int
    {
        return ClinicSetting::where('setting_key', $settingKey)->delete();
    }

    /**
     * Update setting metadata when definition is updated.
     * Only updates type and description, not the value.
     */
    public function updateSettingMetadataFromDefinition(SettingDefinition $definition): int
    {
        return ClinicSetting::where('setting_key', $definition->setting_key)
            ->update([
                'setting_type' => $definition->setting_type,
                'description' => $definition->description,
            ]);
    }

    /**
     * Get all settings for a clinic with missing definitions filled.
     */
    public function getClinicSettingsWithDefaults(int $clinicId): array
    {
        // First sync any missing definitions
        $clinic = Clinic::find($clinicId);
        if ($clinic) {
            $this->syncAllDefinitionsToClinic($clinic);
        }

        // Get all settings grouped by category
        return ClinicSetting::where('clinic_id', $clinicId)
            ->with('definition')
            ->get()
            ->groupBy(function ($setting) {
                return $setting->definition?->category ?? 'general';
            })
            ->toArray();
    }
}
