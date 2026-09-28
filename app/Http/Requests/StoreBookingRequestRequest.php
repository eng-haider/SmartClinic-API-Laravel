<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a public booking submission from a clinic website.
 *
 * No authentication is required — the tenant is resolved by the
 * InitializeTenancyByPatientToken middleware (via ?clinic=ID or header).
 */
class StoreBookingRequestRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:33',
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'nullable|date_format:H:i',
            'note' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'phone.required' => 'Phone number is required',
            'preferred_date.required' => 'A preferred date is required',
            'preferred_date.after_or_equal' => 'The preferred date cannot be in the past',
            'preferred_time.date_format' => 'Preferred time must be in HH:MM format',
        ];
    }
}
