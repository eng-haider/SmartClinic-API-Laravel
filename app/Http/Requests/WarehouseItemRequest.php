<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseItemRequest extends FormRequest
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
            'name'                       => 'required|string|max:255',
            'unit'                       => 'nullable|string|max:50',
            // Opening balance only — later changes go through restock/adjust so the ledger stays correct.
            'quantity'                   => 'nullable|integer|min:0',
            'min_quantity'               => 'nullable|integer|min:0',
            'cost_price'                 => 'nullable|numeric|min:0',
            'clinic_expense_category_id' => 'nullable|exists:clinic_expense_categories,id',
            'notes'                      => 'nullable|string|max:1000',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|required|string|max:255';
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
            'name.required'                       => 'Item name is required',
            'name.max'                            => 'Item name must not exceed 255 characters',
            'quantity.integer'                    => 'Quantity must be an integer',
            'quantity.min'                        => 'Quantity cannot be negative',
            'min_quantity.integer'                => 'Minimum quantity must be an integer',
            'min_quantity.min'                    => 'Minimum quantity cannot be negative',
            'cost_price.numeric'                  => 'Cost price must be a number',
            'cost_price.min'                      => 'Cost price cannot be negative',
            'clinic_expense_category_id.exists'   => 'The selected expense category does not exist',
        ];
    }
}
