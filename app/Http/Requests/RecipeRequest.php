<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'doctors_id' => ['required', 'integer', 'exists:users,id'],
        ];

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'patient',
            'doctors_id' => 'doctor',
            'notes' => 'notes',
        ];
    }
}
