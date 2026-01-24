<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicExpenseCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Force clinic_id from authenticated user only.
     */
    protected function prepareForValidation(): void
    {
        $user = auth()->user();
        
        // Remove clinic_id from request if sent (security measure)
        $this->request->remove('clinic_id');
        
        // Force clinic_id from authenticated user only
        if ($user && $user->clinic_id) {
            $this->merge([
                'clinic_id' => $user->clinic_id,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'clinic_id' => 'nullable|exists:clinics,id',
            'is_active' => 'nullable|boolean',
        ];

        // For updates, make name optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|required|string|max:255';
            $rules['clinic_id'] = 'sometimes|nullable|exists:clinics,id';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required',
            'name.string' => 'Category name must be a string',
            'name.max' => 'Category name must not exceed 255 characters',
            'description.max' => 'Description must not exceed 1000 characters',
            'clinic_id.exists' => 'The selected clinic does not exist',
            'is_active.boolean' => 'Is active must be a boolean',
        ];
    }
}
