<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ClinicSetting;

class ClinicSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $value = $this->setting_value;

        // Convert value based on type
        if ($this->setting_type === 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif ($this->setting_type === 'integer') {
            $value = (int) $value;
        } elseif ($this->setting_type === 'json') {
            $value = json_decode($value, true);
        }

        // Add full URL for logo images, and expose the value itself as a usable URL
        // so the dashboard can render it straight from `setting_value`.
        $logoUrl = null;
        if ($this->setting_key === 'logo' && $this->setting_value) {
            $logoUrl = ClinicSetting::fileUrl($this->setting_value);
            $value = $logoUrl;
        }

        return [
            'id' => $this->id,
            'setting_key' => $this->setting_key,
            'setting_value' => $value,
            'setting_value_raw' => $this->setting_value, // Original value
            'setting_type' => $this->setting_type,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'logo_url' => $logoUrl, // Only populated for logo settings
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
