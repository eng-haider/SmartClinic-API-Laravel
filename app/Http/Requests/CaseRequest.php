<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     */
    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'case_categores_id' => 'required|exists:case_categories,id',
            'status_id' => 'required|exists:statuses,id',
            'notes' => 'nullable|string|max:5000',
            'price' => 'nullable|integer|min:0',
            'tooth_num' => 'nullable|string|max:500',
            'root_stuffing' => 'nullable|string|max:500',
            'is_paid' => 'nullable|boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'doctor_id' => auth()->id(),
            'clinic_id' => auth()->user()->clinic_id ?? auth()->user()->clinics_id ?? null,
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
            'case_categores_id.required' => 'Case category is required',
            'case_categores_id.exists' => 'Selected case category does not exist',
            'status_id.required' => 'Status is required',
            'status_id.exists' => 'Selected status does not exist',
            'price.integer' => 'Price must be a number',
            'price.min' => 'Price must be at least 0',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'patient',
            'case_categores_id' => 'case category',
            'status_id' => 'status',
        ];
    }
}
