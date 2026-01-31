<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SettingDefinition Model
 * 
 * This is the MASTER table of all available settings.
 * Super Admin manages this table.
 * When a new setting is added, it's automatically available for all clinics.
 */
class SettingDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'setting_key',
        'setting_type',
        'default_value',
        'description',
        'category',
        'display_order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get all clinic settings for this definition.
     */
    public function clinicSettings()
    {
        return $this->hasMany(ClinicSetting::class, 'setting_key', 'setting_key');
    }

    /**
     * Scope for active definitions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope ordered by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('category')->orderBy('display_order');
    }

    /**
     * Get typed default value.
     */
    public function getTypedDefaultValue()
    {
        return match ($this->setting_type) {
            'boolean' => filter_var($this->default_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->default_value,
            'json' => json_decode($this->default_value, true),
            default => $this->default_value,
        };
    }

    /**
     * Available categories.
     */
    public static function categories(): array
    {
        return [
            'general' => 'General Information',
            'appointment' => 'Appointment Settings',
            'notification' => 'Notification Settings',
            'financial' => 'Financial Settings',
            'display' => 'Display Settings',
            'social' => 'Social Media',
        ];
    }

    /**
     * Available types.
     */
    public static function types(): array
    {
        return ['string', 'boolean', 'integer', 'json'];
    }
}
