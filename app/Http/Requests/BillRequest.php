<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillRequest extends FormRequest
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
        $rules = [
            'patient_id' => 'required|exists:patients,id',
            'billable_id' => 'nullable|integer',
            'billable_type' => 'nullable|string|max:255',
            'price' => 'required|integer|min:0',
            // 'is_paid' => 'nullable|boolean',
            // 'use_credit' => 'nullable|boolean',
            'doctor_id' => 'nullable|exists:users,id',
        ];

        // Make patient_id optional on update
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['patient_id'] = 'sometimes|exists:patients,id';
            $rules['price'] = 'sometimes|integer|min:0';
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Set clinic_id from authenticated user
        if (auth()->check()) {
          
            // Set doctor_id if not provided
             $data['doctor_id'] = auth()->id();
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Get the validated data with additional fields.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Add clinics_id and doctor_id to validated data
        if (auth()->check()) {
            
            
            if (!isset($validated['doctor_id'])) {
                $validated['doctor_id'] = auth()->id();
            }
        }

        return $validated;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required',
            'patient_id.exists' => 'Selected patient does not exist',
            'price.required' => 'Price is required',
            'price.integer' => 'Price must be a number',
            'price.min' => 'Price must be at least 0',
            'doctor_id.exists' => 'Selected doctor does not exist',
            'billable_type.max' => 'Billable type must not exceed 255 characters',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'patient',
            'billable_id' => 'billable item',
            'billable_type' => 'billable type',
            // 'is_paid' => 'payment status',
            'use_credit' => 'credit usage',
            'doctor_id' => 'doctor',
            'clinics_id' => 'clinic',
        ];
    }
}
