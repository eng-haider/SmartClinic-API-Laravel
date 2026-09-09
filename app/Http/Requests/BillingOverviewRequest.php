<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // BillController applies the existing view-all-bills permission.
        return true;
    }

    public function rules(): array
    {
        $isBalances = $this->route()->getActionMethod() === 'patientBalances';
        $columns = $isBalances
            ? ['name', 'case_count', 'total_price', 'paid_amount', 'unpaid_amount', 'last_payment_at', 'period_paid_amount']
            : ['created_at', 'price'];
        $sorts = array_merge($columns, array_map(fn ($column) => '-'.$column, $columns));

        $rules = [
            'search' => ['nullable', 'string', 'max:255'],
            'doctor_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in($sorts)],
        ];

        $rules['payment_status'] = ['nullable', Rule::in(['paid', 'unpaid'])];
        $rules['date_from'] = ['nullable', 'date_format:Y-m-d'];
        $rules['date_to'] = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('date_from')) {
            $rules['date_to'][] = 'after_or_equal:date_from';
        }
        if (! $isBalances) {
            $rules['patient_id'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }
}
