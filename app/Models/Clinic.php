<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'address',
        'rx_img',
        'whatsapp_template_sid',
        'whatsapp_message_count',
        'whatsapp_phone',
        'show_image_case',
        'doctor_mony',
        'teeth_v2',
        'send_msg',
        'show_rx_id',
        'logo',
        'api_whatsapp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_message_count' => 'integer',
            'doctor_mony' => 'integer',
            'show_image_case' => 'boolean',
            'teeth_v2' => 'boolean',
            'send_msg' => 'boolean',
            'show_rx_id' => 'boolean',
            'api_whatsapp' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the settings for the clinic.
     */
    public function settings()
    {
        return $this->hasMany(ClinicSetting::class);
    }

    /**
     * Get the patients for the clinic.
     */
    public function patients()
    {
        return $this->hasMany(Patient::class, 'clinics_id');
    }

    /**
     * Get the reservations for the clinic.
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'clinics_id');
    }

    /**
     * Get the recipe items for the clinic.
     */
    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class, 'clinics_id');
    }

    /**
     * Get the bills for the clinic.
     */
    public function bills()
    {
        return $this->hasMany(Bill::class, 'clinics_id');
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('setting_key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return $this->castSettingValue($setting->setting_value, $setting->setting_type);
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, $value, string $type = 'string', ?string $description = null): ClinicSetting
    {
        return $this->settings()->updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => is_array($value) ? json_encode($value) : $value,
                'setting_type' => $type,
                'description' => $description,
            ]
        );
    }

    /**
     * Cast setting value based on type.
     */
    private function castSettingValue($value, string $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Scope a query to only include active clinics.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
