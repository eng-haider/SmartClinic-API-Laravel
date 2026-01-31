<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'setting_key' => $this->setting_key,
            'setting_type' => $this->setting_type,
            'default_value' => $this->getTypedDefaultValue(),
            'default_value_raw' => $this->default_value,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'display_order' => $this->display_order,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get category display label.
     */
    private function getCategoryLabel(): string
    {
        $categories = \App\Models\SettingDefinition::categories();
        return $categories[$this->category] ?? $this->category;
    }
}
