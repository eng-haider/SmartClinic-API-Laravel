<?php

namespace App\Modules\Orthodontics\Http\Requests;

use App\Modules\Orthodontics\Models\OrthodonticTreatmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $class = ['nullable', Rule::in(OrthodonticTreatmentPlan::CLASSES)];
        $mm = ['nullable', 'numeric', 'between:-50,50'];

        return [
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'clinical_examination' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'skeletal_class' => ['nullable', Rule::in(['I', 'II', 'III'])],
            'molar_class_right' => $class,
            'molar_class_left' => $class,
            'canine_class_right' => $class,
            'canine_class_left' => $class,
            'crowding_upper_mm' => $mm,
            'crowding_lower_mm' => $mm,
            'overjet_mm' => $mm,
            'overbite_mm' => $mm,
            'crossbite' => ['nullable', 'string', 'max:190'],
            'midline_upper_mm' => $mm,
            'midline_lower_mm' => $mm,
            'observations' => ['nullable', 'array', 'max:50'],
            'observations.*.label' => ['required', 'string', 'max:190'],
            'observations.*.value' => ['nullable', 'string', 'max:2000'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'planned_treatment' => ['nullable', 'string', 'max:10000'],
            'appliance_type' => ['nullable', 'string', 'max:190'],
            'estimated_duration_months' => ['nullable', 'integer', 'between:0,240'],
            'stages' => ['nullable', 'array', 'max:30'],
            'stages.*.title' => ['required', 'string', 'max:190'],
            'stages.*.description' => ['nullable', 'string', 'max:5000'],
            'stages.*.status' => ['nullable', Rule::in(['pending', 'in_progress', 'done'])],
            'stages.*.target_date' => ['nullable', 'date'],
            'doctor_notes' => ['nullable', 'string', 'max:20000'],
            'start_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(OrthodonticTreatmentPlan::STATUSES)],
            'expected_revision' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function planData(): array
    {
        $data = collect($this->validated())->except('expected_revision')->all();
        if (array_key_exists('status', $data) && $data['status'] === null) {
            $data['status'] = 'planned';
        }

        return $data;
    }
}
