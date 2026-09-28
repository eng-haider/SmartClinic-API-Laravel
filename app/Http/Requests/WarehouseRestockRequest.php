<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseRestockRequest extends FormRequest
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
            'quantity'                   => 'required|integer|min:1',
            // Defaults to the item's current cost_price when omitted.
            'unit_cost'                  => 'nullable|numeric|min:0',
            'clinic_expense_category_id' => 'nullable|exists:clinic_expense_categories,id',
            'is_paid'                    => 'nullable|boolean',
            'date'                       => 'nullable|date',
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
            'quantity.required'                 => 'Quantity is required',
            'quantity.integer'                  => 'Quantity must be an integer',
            'quantity.min'                      => 'Quantity must be at least 1',
            'unit_cost.numeric'                 => 'Unit cost must be a number',
            'unit_cost.min'                     => 'Unit cost cannot be negative',
            'clinic_expense_category_id.exists' => 'The selected expense category does not exist',
            'is_paid.boolean'                   => 'Is paid must be a boolean',
            'date.date'                         => 'Date must be a valid date',
        ];
    }
}
