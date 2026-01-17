<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaseCategoryRequest extends FormRequest
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
        $caseCategoryId = $this->route('case_category');

        return [
            'name' => 'required|string|max:255',
            'order' => 'nullable|integer|min:0',
            'clinic_id' => 'required|exists:clinics,id',
            'item_cost' => 'nullable|integer|min:0',
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
            'name.required' => 'Category name is required',
            'name.string' => 'Category name must be a string',
            'name.max' => 'Category name must not exceed 255 characters',
            'clinic_id.required' => 'Clinic ID is required',
            'clinic_id.exists' => 'The selected clinic does not exist',
            'order.integer' => 'Order must be an integer',
            'order.min' => 'Order must be at least 0',
            'item_cost.integer' => 'Item cost must be an integer',
            'item_cost.min' => 'Item cost must be at least 0',
        ];
    }
}
