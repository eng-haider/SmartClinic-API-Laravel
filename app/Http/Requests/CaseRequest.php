<?php

namespace App\Http\Requests;

use App\Services\SpecialtyManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * Dynamically merges specialty-specific rules via SpecialtyManager.
     */
    public function rules(): array
    {
        $rules = [
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'nullable|exists:users,id',
            'case_categores_id' => 'nullable|exists:case_categories,id',
            'status_id' => 'nullable|exists:statuses,id',
            'notes' => 'nullable|string|max:5000',
            'price' => 'nullable|integer|min:0',
            'is_paid' => 'nullable|boolean',
            'case_date' => 'nullable|date',
        ];

        // Merge specialty-specific rules (dental: tooth_num, root_stuffing)
        // (ophthalmology: eye_side, visual_acuity, iop, etc.)
        return array_merge($rules, SpecialtyManager::handler()->validationRules());
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Always set doctor_id from authenticated user
        // Set default status_id to 2 if not provided
        // Set default case_date to current datetime if not provided
        $this->merge([
            'doctor_id' => $user->id,
            'status_id' => $this->status_id ?? 2,
            'case_date' => $this->case_date ?? now()->toDateTimeString(),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required',
            'patient_id.exists' => 'Selected patient does not exist',
            'doctor_id.required' => 'Doctor is required',
            'doctor_id.exists' => 'Selected doctor does not exist',
            'case_categores_id.required' => 'Case category is required',
            'case_categores_id.exists' => 'Selected case category does not exist',
            'status_id.exists' => 'Selected status does not exist',
            'price.integer' => 'Price must be a number',
            'price.min' => 'Price must be at least 0',
            'case_date.date' => 'Case date must be a valid date',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'patient',
            'doctor_id' => 'doctor',
            'case_categores_id' => 'case category',
            'status_id' => 'status',
            'case_date' => 'case date',
        ];
    }
}
