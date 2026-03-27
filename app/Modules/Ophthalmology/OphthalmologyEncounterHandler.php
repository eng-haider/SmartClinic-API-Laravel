<?php

namespace App\Modules\Ophthalmology;

use App\Contracts\SpecialtyHandlerInterface;
use App\Models\OphthalmologyEncounterDetail;

/**
 * OphthalmologyEncounterHandler
 *
 * Handles ophthalmology-specific encounter logic:
 * - Eye side selection (left/right/both)
 * - Visual acuity measurements
 * - Intraocular pressure (IOP)
 * - Refraction data
 * - Anterior/posterior segment findings
 * - Diagnosis
 */
class OphthalmologyEncounterHandler implements SpecialtyHandlerInterface
{
    public function specialty(): string
    {
        return 'ophthalmology';
    }

    /**
     * Ophthalmology-specific validation rules.
     */
    public function validationRules(): array
    {
        return [
            'eye_side' => 'nullable|in:left,right,both',
            'visual_acuity_left' => 'nullable|string|max:20',
            'visual_acuity_right' => 'nullable|string|max:20',
            'iop_left' => 'nullable|numeric|min:0|max:80',
            'iop_right' => 'nullable|numeric|min:0|max:80',
            'refraction_left' => 'nullable|string|max:50',
            'refraction_right' => 'nullable|string|max:50',
            'anterior_segment' => 'nullable|string|max:2000',
            'posterior_segment' => 'nullable|string|max:2000',
            'diagnosis' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Include ophthalmology fields in API output.
     * Reads from ophthalmology_encounter_details table.
     */
    public function resourceFields($encounter): array
    {
        $details = $encounter->ophthalmologyDetails;

        if (!$details) {
            return [
                'eye_side' => null,
                'visual_acuity_left' => null,
                'visual_acuity_right' => null,
                'iop_left' => null,
                'iop_right' => null,
                'refraction_left' => null,
                'refraction_right' => null,
                'anterior_segment' => null,
                'posterior_segment' => null,
                'diagnosis' => null,
            ];
        }

        return [
            'eye_side' => $details->eye_side,
            'visual_acuity_left' => $details->visual_acuity_left,
            'visual_acuity_right' => $details->visual_acuity_right,
            'iop_left' => $details->iop_left,
            'iop_right' => $details->iop_right,
            'refraction_left' => $details->refraction_left,
            'refraction_right' => $details->refraction_right,
            'anterior_segment' => $details->anterior_segment,
            'posterior_segment' => $details->posterior_segment,
            'diagnosis' => $details->diagnosis,
        ];
    }

    /**
     * Remove ophthalmology fields from generic save data.
     * They will be saved separately in afterSave().
     */
    public function beforeSave(array $data): array
    {
        $ophthalmologyFields = [
            'eye_side', 'visual_acuity_left', 'visual_acuity_right',
            'iop_left', 'iop_right', 'refraction_left', 'refraction_right',
            'anterior_segment', 'posterior_segment', 'diagnosis',
        ];

        // Remove ophthalmology fields from cases table data
        foreach ($ophthalmologyFields as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Save ophthalmology-specific data to the detail table.
     */
    public function afterSave($encounter, array $data): void
    {
        $ophthalmologyData = array_filter([
            'eye_side' => $data['eye_side'] ?? null,
            'visual_acuity_left' => $data['visual_acuity_left'] ?? null,
            'visual_acuity_right' => $data['visual_acuity_right'] ?? null,
            'iop_left' => $data['iop_left'] ?? null,
            'iop_right' => $data['iop_right'] ?? null,
            'refraction_left' => $data['refraction_left'] ?? null,
            'refraction_right' => $data['refraction_right'] ?? null,
            'anterior_segment' => $data['anterior_segment'] ?? null,
            'posterior_segment' => $data['posterior_segment'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
        ], fn($v) => $v !== null);

        if (!empty($ophthalmologyData)) {
            OphthalmologyEncounterDetail::updateOrCreate(
                ['case_id' => $encounter->id],
                $ophthalmologyData
            );
        }
    }

    /**
     * Ophthalmology-specific searchable fields.
     */
    public function searchableFields(): array
    {
        return ['diagnosis'];
    }

    /**
     * Default features for ophthalmology clinics.
     */
    public function defaultFeatures(): array
    {
        return [
            'eye_examination' => true,
            'iop_measurement' => true,
            'refraction' => true,
        ];
    }
}
