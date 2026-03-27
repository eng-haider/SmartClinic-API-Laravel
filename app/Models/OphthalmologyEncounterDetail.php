<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OphthalmologyEncounterDetail - Eye-specific data per encounter.
 *
 * Linked to the `cases` table via case_id.
 * Stores visual acuity, IOP, refraction, and clinical findings.
 */
class OphthalmologyEncounterDetail extends Model
{
    protected $table = 'ophthalmology_encounter_details';

    protected $fillable = [
        'case_id',
        'eye_side',
        'visual_acuity_left',
        'visual_acuity_right',
        'iop_left',
        'iop_right',
        'refraction_left',
        'refraction_right',
        'anterior_segment',
        'posterior_segment',
        'diagnosis',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'iop_left' => 'decimal:1',
            'iop_right' => 'decimal:1',
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
