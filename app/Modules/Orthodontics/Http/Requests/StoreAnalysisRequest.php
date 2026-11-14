<?php

namespace App\Modules\Orthodontics\Http\Requests;

use App\Modules\Orthodontics\Models\OrthodonticAnalysis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // OrthodonticsAccess authorises against the case
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::in(OrthodonticAnalysis::TYPES)],
            'title' => ['nullable', 'string', 'max:190'],
            'file_id' => ['nullable', 'integer'],
            'cbct_study_id' => ['nullable', 'integer'],
            'analysis_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'data' => [$creating ? 'present' : 'sometimes', 'array'],
            'expected_revision' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Keep stored snapshots bounded (landmark sets are a few KB; this is far
     * above any real tracing).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->has('data') && strlen((string) json_encode($this->input('data'))) > 2 * 1024 * 1024) {
                $v->errors()->add('data', 'The analysis data is too large.');
            }
        });
    }
}
