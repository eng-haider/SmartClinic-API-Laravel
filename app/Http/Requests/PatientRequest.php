<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
        $patientId = $this->route('patient'); // This is the ID from the route parameter

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
            'doctor_id' => 'nullable|exists:users,id',
            'clinics_id' => 'nullable|exists:clinics,id',
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

        // Always override doctor_id and clinics_id from authenticated user
        // Remove any values sent in the request
        $data = $this->all();

        $data['clinics_id'] = $user->clinic_id;

        // If birth_date is just a year (e.g., "1990"), convert to "1990-01-01"
        if (!empty($data['birth_date']) && is_numeric($data['birth_date']) && strlen($data['birth_date']) == 4) {
            $data['birth_date'] = $data['birth_date'] . '-01-01';
        }

        // Always calculate age from birth_date if birth_date is provided
        // This ensures age is updated whenever birth_date changes
        if (!empty($data['birth_date'])) {
            try {
                $birthDate = new \DateTime($data['birth_date']);
                $today = new \DateTime('today');
                $age = $birthDate->diff($today)->y;
                $data['age'] = $age;
            } catch (\Exception $e) {
                // If date parsing fails, let validation handle it
            }
        }

        $this->replace($data);
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
