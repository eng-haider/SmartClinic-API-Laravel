<?php

namespace App\Http\Requests;

use App\Models\SettingDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $definitionId = $this->route('setting_definition') ?? $this->route('id');

        return [
            'setting_key' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:100',
                'regex:/^[a-z_]+$/', // Only lowercase and underscores
                Rule::unique('setting_definitions', 'setting_key')->ignore($definitionId),
            ],
            'setting_type' => [
                'sometimes',
                'string',
                Rule::in(SettingDefinition::types()),
            ],
            'default_value' => 'nullable|string',
            'description' => 'nullable|string|max:500',
            'category' => [
                'sometimes',
                'string',
                Rule::in(array_keys(SettingDefinition::categories())),
            ],
            'display_order' => 'sometimes|integer|min:0',
            'is_required' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'setting_key.required' => 'The setting key is required.',
            'setting_key.unique' => 'This setting key already exists.',
            'setting_key.regex' => 'Setting key must contain only lowercase letters and underscores.',
            'setting_type.in' => 'Setting type must be one of: string, boolean, integer, json.',
            'category.in' => 'Invalid category selected.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set defaults for new definitions
        if ($this->isMethod('POST')) {
            $this->merge([
                'setting_type' => $this->input('setting_type', 'string'),
                'category' => $this->input('category', 'general'),
                'display_order' => $this->input('display_order', 0),
                'is_required' => $this->input('is_required', false),
                'is_active' => $this->input('is_active', true),
            ]);
        }
    }
}
