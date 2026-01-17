<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicExpenseRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'clinic_expense_category_id' => 'nullable|exists:clinic_expense_categories,id',
            'clinic_id' => 'required|exists:clinics,id',
            'date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'is_paid' => 'nullable|boolean',
            'doctor_id' => 'nullable|exists:users,id',
        ];

        // For updates, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|required|string|max:255';
            $rules['clinic_id'] = 'sometimes|required|exists:clinics,id';
            $rules['date'] = 'sometimes|required|date';
            $rules['price'] = 'sometimes|required|numeric|min:0';
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
            'name.required' => 'Expense name is required',
            'name.string' => 'Expense name must be a string',
            'name.max' => 'Expense name must not exceed 255 characters',
            'quantity.integer' => 'Quantity must be an integer',
            'quantity.min' => 'Quantity must be at least 1',
            'clinic_expense_category_id.exists' => 'The selected expense category does not exist',
            'clinic_id.required' => 'Clinic ID is required',
            'clinic_id.exists' => 'The selected clinic does not exist',
            'date.required' => 'Date is required',
            'date.date' => 'Date must be a valid date',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price must be at least 0',
            'is_paid.boolean' => 'Is paid must be a boolean',
            'doctor_id.exists' => 'The selected doctor does not exist',
        ];
    }
}
