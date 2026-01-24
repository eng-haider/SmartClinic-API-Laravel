<?php

namespace App\Http\Requests;

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
     */
    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'nullable|exists:users,id',
            'clinic_id' => 'nullable|exists:clinics,id',
            'case_categores_id' => 'required|exists:case_categories,id',
            'status_id' => 'nullable|exists:statuses,id',
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
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Always set doctor_id and clinic_id from authenticated user
        // Set default status_id to 1 (New) if not provided
        $this->merge([
            'doctor_id' => $user->id,
            'clinic_id' => $user->clinic_id,
            'status_id' => $this->status_id ?? 1,
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
            'clinic_id.required' => 'Clinic is required. Please make sure your user account has a clinic assigned.',
            'clinic_id.exists' => 'Selected clinic does not exist',
            'case_categores_id.required' => 'Case category is required',
            'case_categores_id.exists' => 'Selected case category does not exist',
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
            'doctor_id' => 'doctor',
            'clinic_id' => 'clinic',
            'case_categores_id' => 'case category',
            'status_id' => 'status',
        ];
    }
}
