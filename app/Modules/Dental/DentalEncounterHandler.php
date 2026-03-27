<?php

namespace App\Modules\Dental;

use App\Contracts\SpecialtyHandlerInterface;

/**
 * DentalEncounterHandler
 *
 * Handles dental-specific encounter logic:
 * - tooth_num and root_stuffing fields
 * - Tooth chart features
 * - X-ray analysis capability
 *
 * For Phase 1 this reads dental fields from the existing cases table columns.
 * In Phase 2, it will also read/write from dental_encounter_details table.
 */
class DentalEncounterHandler implements SpecialtyHandlerInterface
{
    public function specialty(): string
    {
        return 'dental';
    }

    /**
     * Dental-specific validation rules.
     * Merged with base CaseRequest rules by SpecialtyManager.
     */
    public function validationRules(): array
    {
        return [
            'tooth_num' => 'nullable|string|max:500',
            'root_stuffing' => 'nullable|string|max:500',
        ];
    }

    /**
     * Include dental fields in API output.
     * Reads from existing cases table columns (backward compatible).
     */
    public function resourceFields($encounter): array
    {
        return [
            'tooth_num' => $encounter->tooth_num,
            'root_stuffing' => $encounter->root_stuffing,
        ];
    }

    /**
     * No data transformation needed — dental fields are stored
     * directly in the cases table for backward compatibility.
     */
    public function beforeSave(array $data): array
    {
        return $data;
    }

    /**
     * Post-save hook for dental encounters.
     * Phase 1: No-op (data is in cases table columns).
     * Phase 2: Will also save to dental_encounter_details table.
     */
    public function afterSave($encounter, array $data): void
    {
        // Phase 2: Will save to dental_encounter_details table
        // DentalEncounterDetail::updateOrCreate(
        //     ['case_id' => $encounter->id],
        //     array_filter([
        //         'tooth_num' => $data['tooth_num'] ?? null,
        //         'root_stuffing' => $data['root_stuffing'] ?? null,
        //     ])
        // );
    }

    /**
     * Dental-specific searchable fields for repository queries.
     */
    public function searchableFields(): array
    {
        return ['tooth_num'];
    }

    /**
     * Default features for dental clinics.
     */
    public function defaultFeatures(): array
    {
        return [
            'tooth_chart' => true,
            'xray_analysis' => true,
            'root_stuffing' => true,
        ];
    }
}
