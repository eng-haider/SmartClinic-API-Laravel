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
    public function getAllByClinic(): Collection
    {
        // Don't eager load 'definition' - it's in central DB, not tenant DB
        $query = $this->query();
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Get all settings grouped by category.
     * Groups settings by inferring category from setting key prefix.
     */
    public function getAllByClinicGrouped(): array
    {
        // Don't eager load 'definition' - it's in central DB, not tenant DB
        $query = $this->query();
        
        $settings = $query->get();

        // Group by category (inferred from setting key or default)
        $grouped = [];
        $categories = SettingDefinition::categories();

        foreach ($categories as $categoryKey => $categoryLabel) {
            $grouped[$categoryKey] = [
                'label' => $categoryLabel,
                'settings' => [],
            ];
        }

        foreach ($settings as $setting) {
            // Infer category from setting key
            $category = $this->inferCategory($setting->setting_key);
            
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
                'is_required' => false, // Default since we don't have definition
                'display_order' => $this->getDisplayOrder($setting->setting_key),
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
    public function getByKey( string $key): ?ClinicSetting
    {
        // Don't eager load 'definition' - it's in central DB, not tenant DB
        $query = $this->query()->where('setting_key', $key);
        
        return $query->first();
    }

    /**
     * Update only the value of a clinic setting.
     * Does not allow creating new settings (must exist from definition).
     */
    public function updateValue(string $key, $value): ?ClinicSetting
    {
        $setting = $this->getByKey($key);
        
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
    public function updateOrCreate(string $key, array $data): ClinicSetting
    {
        $settingData = [
            'setting_key' => $key,
            'setting_value' => $this->prepareValue($data['setting_value'] ?? '', $data['setting_type'] ?? 'string'),
            'setting_type' => $data['setting_type'] ?? 'string',
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        $whereConditions = ['setting_key' => $key];

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
    public function getActiveByClinic(): Collection
    {
        $query = $this->query()->where('is_active', true);
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Get settings by type.
     */
    public function getByType( string $type): Collection
    {
        $query = $this->query()->where('setting_type', $type);
        
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
            'json' => is_string($value) ? $value : json_encode($value),
            default => is_array($value) ? json_encode($value) : (string) $value,
        };
    }

    /**
     * Search settings by key pattern.
     */
    public function searchByKey( string $searchTerm): Collection
    {
        $query = $this->query()->where('setting_key', 'like', "%{$searchTerm}%");
        
        return $query->orderBy('setting_key')->get();
    }

    /**
     * Get multiple settings by keys.
     */
    public function getByKeys( array $keys): Collection
    {
        $query = $this->query()->whereIn('setting_key', $keys);
        
        return $query->get()->keyBy('setting_key');
    }

    /**
     * Bulk update clinic settings.
     */
    public function bulkUpdate(array $settings): Collection
    {
        $results = collect();

        foreach ($settings as $key => $value) {
            $setting = $this->updateOrCreate($key, [
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

    /**
     * Infer category from setting key.
     * Maps setting keys to their categories without relying on central DB.
     */
    private function inferCategory(string $key): string
    {
        // General category
        if (in_array($key, ['clinic_name', 'logo', 'phone', 'email', 'address', 'clinic_reg_num', 'timezone', 'language', 'currency'])) {
            return 'general';
        }

        // Appointment category
        if (in_array($key, ['appointment_duration', 'working_hours', 'enable_online_booking', 'max_appointments_per_day'])) {
            return 'appointment';
        }

        // Notification category
        if (in_array($key, ['enable_sms', 'enable_email', 'enable_whatsapp', 'whatsapp_number', 'reminder_before_hours'])) {
            return 'notification';
        }

        // Financial category
        if (in_array($key, ['tax_rate', 'enable_invoicing', 'default_payment_method'])) {
            return 'financial';
        }

        // Display category
        if (in_array($key, ['show_image_case', 'show_rx_id', 'teeth_v2', 'tooth_colors'])) {
            return 'display';
        }

        // Social category
        if (in_array($key, ['facebook_url', 'instagram_url', 'twitter_url'])) {
            return 'social';
        }

        // Medical category
        if (in_array($key, ['specializations'])) {
            return 'medical';
        }

        // Default to general
        return 'general';
    }

    /**
     * Get display order for a setting key.
     * Provides ordering without relying on central DB.
     */
    private function getDisplayOrder(string $key): int
    {
        $order = [
            // General (1-9)
            'clinic_name' => 1,
            'logo' => 2,
            'phone' => 3,
            'email' => 4,
            'address' => 5,
            'clinic_reg_num' => 6,
            'timezone' => 7,
            'language' => 8,
            'currency' => 9,

            // Appointment (10-13)
            'appointment_duration' => 10,
            'working_hours' => 11,
            'enable_online_booking' => 12,
            'max_appointments_per_day' => 13,

            // Notification (14-18)
            'enable_sms' => 14,
            'enable_email' => 15,
            'enable_whatsapp' => 16,
            'whatsapp_number' => 17,
            'reminder_before_hours' => 18,

            // Financial (19-21)
            'tax_rate' => 19,
            'enable_invoicing' => 20,
            'default_payment_method' => 21,

            // Display (22-25)
            'show_image_case' => 22,
            'show_rx_id' => 23,
            'teeth_v2' => 24,
            'tooth_colors' => 25,

            // Social (26-28)
            'facebook_url' => 26,
            'instagram_url' => 27,
            'twitter_url' => 28,

            // Medical (29)
            'specializations' => 29,
        ];

        return $order[$key] ?? 999; // Unknown settings go to the end
    }
}

