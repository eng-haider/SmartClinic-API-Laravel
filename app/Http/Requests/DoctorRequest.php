<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class DoctorRequest extends FormRequest
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
        $doctorId = $this->route('doctor');
        $isUpdating = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($doctorId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:33',
                Rule::unique('users', 'phone')->ignore($doctorId),
            ],
            'password' => $isUpdating ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => 'nullable|string|in:doctor,clinic_super_doctor',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default is_active to true if not provided
        if (!$this->has('is_active')) {
            $this->merge([
                'is_active' => true,
            ]);
        }

        // Set default role to 'doctor' if not provided
        if (!$this->has('role')) {
            $this->merge([
                'role' => 'doctor',
            ]);
        }
    }

    /**
     * Get the validated data from the request.
     * Override to add clinic_id from authenticated user.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Add clinic_id from authenticated user (cannot be overridden from request)
        if (Auth::check()) {
            $validated['clinic_id'] = Auth::user()->clinic_id;
        }
        
        return $validated;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Doctor name is required',
            'name.string' => 'Doctor name must be a string',
            'name.max' => 'Doctor name must not exceed 255 characters',
            
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'This email is already registered',
            'email.max' => 'Email must not exceed 255 characters',
            
            'phone.unique' => 'This phone number is already registered',
            'phone.max' => 'Phone number must not exceed 33 characters',
            
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            
            'role.in' => 'Role must be either doctor or clinic_super_doctor',
            
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }
}
