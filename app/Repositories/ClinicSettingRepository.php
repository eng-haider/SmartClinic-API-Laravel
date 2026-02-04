<?php

namespace App\Repositories;

use App\Models\ClinicSetting;
use App\Models\SettingDefinition;
use Illuminate\Support\Collection;

class ClinicSettingRepository extends BaseRepository
{
    public function __construct(ClinicSetting $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all settings for a specific clinic.
     */
    public function getAllByClinic(?string|int $clinicId = null): Collection
    {
        $query = $this->query()->with('definition');
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Get all settings grouped by category.
     */
    public function getAllByClinicGrouped(?string|int $clinicId = null): array
    {
        $query = $this->query()->with('definition');
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        $settings = $query->get();

        // Group by category from definition
        $grouped = [];
        $categories = SettingDefinition::categories();

        foreach ($categories as $categoryKey => $categoryLabel) {
            $grouped[$categoryKey] = [
                'label' => $categoryLabel,
                'settings' => [],
            ];
        }

        foreach ($settings as $setting) {
            $category = $setting->definition?->category ?? 'general';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'label' => $category,
                    'settings' => [],
                ];
            }

            $grouped[$category]['settings'][] = [
                'id' => $setting->id,
                'setting_key' => $setting->setting_key,
                'setting_value' => $setting->getValue(),
                'setting_value_raw' => $setting->setting_value,
                'setting_type' => $setting->setting_type,
                'description' => $setting->description,
                'is_required' => $setting->definition?->is_required ?? false,
                'display_order' => $setting->definition?->display_order ?? 0,
                'is_active' => $setting->is_active,
                'updated_at' => $setting->updated_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Sort settings within each category by display_order
        foreach ($grouped as $category => &$data) {
            usort($data['settings'], fn($a, $b) => $a['display_order'] <=> $b['display_order']);
        }

        // Remove empty categories
        $grouped = array_filter($grouped, fn($data) => !empty($data['settings']));

        return $grouped;
    }

    /**
     * Get a specific setting by key for a clinic.
     */
    public function getByKey(?string|int $clinicId, string $key): ?ClinicSetting
    {
        $query = $this->query()->where('setting_key', $key)->with('definition');
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->first();
    }

    /**
     * Update only the value of a clinic setting.
     * Does not allow creating new settings (must exist from definition).
     */
    public function updateValue(?string|int $clinicId, string $key, $value): ?ClinicSetting
    {
        $setting = $this->getByKey($clinicId, $key);
        
        if (!$setting) {
            return null;
        }

        $setting->setting_value = $this->prepareValue($value, $setting->setting_type);
        $setting->save();

        return $setting->fresh();
    }

    /**
     * Update or create a clinic setting.
     */
    public function updateOrCreate(?string|int $clinicId, string $key, array $data): ClinicSetting
    {
        $settingData = [
            'setting_key' => $key,
            'setting_value' => $this->prepareValue($data['setting_value'] ?? '', $data['setting_type'] ?? 'string'),
            'setting_type' => $data['setting_type'] ?? 'string',
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
        
        if ($clinicId !== null) {
            $settingData['clinic_id'] = $clinicId;
        }

        $whereConditions = ['setting_key' => $key];
        if ($clinicId !== null) {
            $whereConditions['clinic_id'] = $clinicId;
        }

        return $this->model->updateOrCreate($whereConditions, $settingData);
    }

    /**
     * Delete a setting by ID.
     */
    public function delete(int $id): bool
    {
        return $this->query()->where('id', $id)->delete();
    }

    /**
     * Get active settings for a clinic.
     */
    public function getActiveByClinic(?string|int $clinicId = null): Collection
    {
        $query = $this->query()->where('is_active', true);
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Get settings by type.
     */
    public function getByType(?string|int $clinicId, string $type): Collection
    {
        $query = $this->query()->where('setting_type', $type);
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Prepare value based on type for storage.
     */
    private function prepareValue($value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => is_array($value) ? json_encode($value) : $value,
            default => (string) $value,
        };
    }

    /**
     * Search settings by key pattern.
     */
    public function searchByKey(?string|int $clinicId, string $searchTerm): Collection
    {
        $query = $this->query()->where('setting_key', 'like', "%{$searchTerm}%");
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Get multiple settings by keys.
     */
    public function getByKeys(?string|int $clinicId, array $keys): Collection
    {
        $query = $this->query()->whereIn('setting_key', $keys);
        
        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->get()->keyBy('setting_key');
    }

    /**
     * Bulk update settings.
     */
    public function bulkUpdate(?string|int $clinicId, array $settings): Collection
    {
        $results = collect();

        foreach ($settings as $key => $value) {
            $setting = $this->updateOrCreate($clinicId, $key, [
                'setting_value' => $value,
                'setting_type' => $this->inferType($value),
            ]);
            
            $results->push($setting);
        }

        return $results;
    }

    /**
     * Infer setting type from value.
     */
    private function inferType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value)) {
            return 'integer';
        }
        
        if (is_array($value)) {
            return 'json';
        }
        
        return 'string';
    }
}
