<?php

namespace App\Modules\Orthodontics\Models;

use App\Models\CaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrthodonticTreatmentPlan extends Model
{
    use SoftDeletes;

    public const STATUSES = ['planned', 'active', 'retention', 'completed', 'discontinued'];

    public const CLASSES = ['I', 'II', 'II_div1', 'II_div2', 'III'];

    protected $table = 'orthodontic_treatment_plans';

    protected $fillable = [
        'case_id', 'chief_complaint', 'clinical_examination', 'diagnosis', 'skeletal_class',
        'molar_class_right', 'molar_class_left', 'canine_class_right', 'canine_class_left',
        'crowding_upper_mm', 'crowding_lower_mm', 'overjet_mm', 'overbite_mm', 'crossbite',
        'midline_upper_mm', 'midline_lower_mm', 'observations', 'objectives', 'planned_treatment',
        'appliance_type', 'estimated_duration_months', 'stages', 'doctor_notes', 'start_date',
        'status', 'revision', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'case_id' => 'integer',
            'crowding_upper_mm' => 'float',
            'crowding_lower_mm' => 'float',
            'overjet_mm' => 'float',
            'overbite_mm' => 'float',
            'midline_upper_mm' => 'float',
            'midline_lower_mm' => 'float',
            'observations' => 'array',
            'stages' => 'array',
            'estimated_duration_months' => 'integer',
            'start_date' => 'date',
            'revision' => 'integer',
        ];
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
