<?php

namespace App\Modules\Dental;

use App\Contracts\SpecialtyHandlerInterface;
use App\Models\DentalEncounterDetail;

/**
 * DentalEncounterHandler
 *
 * Handles dental-specific encounter logic:
 * - tooth_num and root_stuffing fields
 * - Tooth chart features
 * - X-ray analysis capability
 *
 * Reads dental fields from existing cases table columns (backward compat).
 * Also writes to dental_encounter_details table for normalization (Phase 2).
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
     * Falls back to detail table if columns are null.
     */
    public function resourceFields($encounter): array
    {
        return [
            'tooth_num'     => $encounter->tooth_num
                ?? $encounter->dentalDetails?->tooth_num,
            'root_stuffing' => $encounter->root_stuffing
                ?? $encounter->dentalDetails?->root_stuffing,
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
     * Post-save hook: also save to dental_encounter_details table.
     * Dual-write: data stays in cases columns AND detail table.
     */
    public function afterSave($encounter, array $data): void
    {
        $dentalData = array_filter([
            'tooth_num'     => $data['tooth_num'] ?? null,
            'root_stuffing' => $data['root_stuffing'] ?? null,
        ], fn($v) => $v !== null);

        if (!empty($dentalData)) {
            DentalEncounterDetail::updateOrCreate(
                ['case_id' => $encounter->id],
                $dentalData
            );
        }
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
