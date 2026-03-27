<?php

namespace App\Models;

use App\Services\SpecialtyManager;

/**
 * Encounter - Generic wrapper around the `cases` table.
 *
 * This model extends CaseModel and adds specialty-aware relationships.
 * Use this for new specialty-agnostic code going forward.
 * CaseModel continues to work unchanged for backward compatibility.
 *
 * Usage:
 *   Encounter::find(1)->getSpecialtyFields()
 *   Encounter::find(1)->dentalDetails
 *   Encounter::find(1)->ophthalmologyDetails
 */
class Encounter extends CaseModel
{
    /**
     * Get the dental-specific details (if any).
     */
    public function dentalDetails()
    {
        return $this->hasOne(DentalEncounterDetail::class, 'case_id');
    }

    /**
     * Get the ophthalmology-specific details (if any).
     */
    public function ophthalmologyDetails()
    {
        return $this->hasOne(OphthalmologyEncounterDetail::class, 'case_id');
    }

    /**
     * Get specialty-specific fields via the current tenant's handler.
     *
     * @return array<string, mixed>
     */
    public function getSpecialtyFields(): array
    {
        return SpecialtyManager::handler()->resourceFields($this);
    }

    /**
     * Save specialty-specific data after the encounter is saved.
     *
     * @param array $data Original request data
     */
    public function saveSpecialtyData(array $data): void
    {
        SpecialtyManager::handler()->afterSave($this, $data);
    }
}
