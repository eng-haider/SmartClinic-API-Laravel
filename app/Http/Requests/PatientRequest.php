<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
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
        $patientId = $this->route('patient')?->id;

        return [
            'name' => 'required|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'phone' => 'nullable|string|max:33',
            'systemic_conditions' => 'nullable|string|max:255',
            'sex' => 'nullable|integer|in:1,2', // 1=Male, 2=Female
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'birth_date' => 'nullable|date|before:today',
            'rx_id' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'from_where_come_id' => 'nullable|exists:from_where_comes,id',
            'identifier' => 'nullable|string|max:255',
            'credit_balance' => 'nullable|integer',
            'credit_balance_add_at' => 'nullable|date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'doctor_id' => auth()->id(),
            'clinics_id' => auth()->user()->clinic_id ?? auth()->user()->clinics_id ?? null,
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Patient name is required',
            'from_where_come_id.exists' => 'Selected referral source does not exist',
            'age.integer' => 'Age must be a number',
            'age.min' => 'Age cannot be negative',
            'age.max' => 'Age must be less than 150',
            'sex.in' => 'Sex must be 1 (Male) or 2 (Female)',
            'birth_date.before' => 'Birth date must be in the past',
        ];
    }
}
