<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DentalEncounterDetail - Dental-specific data per encounter.
 *
 * Linked to the `cases` table via case_id.
 * Stores tooth_num and root_stuffing in a normalized table
 * separate from the generic cases columns.
 */
class DentalEncounterDetail extends Model
{
    protected $table = 'dental_encounter_details';

    protected $fillable = [
        'case_id',
        'tooth_num',
        'root_stuffing',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'extra_data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the encounter (case) this detail belongs to.
     */
    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'case_id');
    }

    /**
     * Alias for backward compat.
     */
    public function caseModel()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
