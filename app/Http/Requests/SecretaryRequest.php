<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SecretaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only clinic_super_doctor can manage secretaries
        return $this->user()->hasRole('clinic_super_doctor');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $secretaryId = $this->route('secretary');
        $isUpdating = $this->isMethod('PUT') || $this->isMethod('PATCH');

        // Get available permissions from config (clinic_super_doctor permissions)
        $availablePermissions = config('rolesAndPermissions.roles.clinic_super_doctor.permissions', []);

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($secretaryId),
            ],
            'phone' => [
                'required',
                'string',
                'max:33',
                Rule::unique('users', 'phone')->ignore($secretaryId),
            ],
            'password' => $isUpdating ? 'nullable|string|min:8' : 'required|string|min:8',
            'is_active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => [
                'string',
                Rule::in($availablePermissions),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Secretary name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already registered',
            'phone.required' => 'Phone number is required',
            'phone.unique' => 'This phone number is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'permissions.*.in' => 'Invalid permission selected',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'secretary name',
            'email' => 'email address',
            'phone' => 'phone number',
            'password' => 'password',
            'is_active' => 'active status',
            'permissions' => 'permissions',
        ];
    }
}
