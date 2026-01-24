<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class NoteRequest extends FormRequest
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
            'noteable_id' => 'required|integer',
            'noteable_type' => 'required|string|in:App\Models\Patient,App\Models\CaseModel',
            'content' => 'required|string',
            'created_by' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (!Auth::check()) {
            return;
        }

        // Always set created_by from authenticated user
        $this->merge([
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'noteable_id.required' => 'The related item ID is required',
            'noteable_id.integer' => 'The related item ID must be an integer',
            'noteable_type.required' => 'The related item type is required',
            'noteable_type.in' => 'The related item type must be either Patient or Case',
            'content.required' => 'Note content is required',
            'content.string' => 'Note content must be a string',
            'created_by.exists' => 'The selected user does not exist',
        ];
    }
}
