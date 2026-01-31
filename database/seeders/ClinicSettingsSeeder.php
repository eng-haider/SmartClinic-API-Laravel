<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use Illuminate\Database\Seeder;

class ClinicSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all clinics
        $clinics = Clinic::all();

        foreach ($clinics as $clinic) {
            // Create default settings for each clinic
            $this->createDefaultSettings($clinic->id);
        }

        $this->command->info('✅ Default clinic settings created successfully!');
    }

    /**
     * Create default settings for a clinic
     */
    private function createDefaultSettings(int $clinicId): void
    {
        $defaultSettings = [
            // Basic Information
            [
                'setting_key' => 'clinic_name',
                'setting_value' => 'SmartClinic',
                'setting_type' => 'string',
                'description' => 'Official clinic name',
                'is_active' => true,
            ],
            [
                'setting_key' => 'phone',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Primary contact phone number',
                'is_active' => true,
            ],
            [
                'setting_key' => 'email',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Primary contact email address',
                'is_active' => true,
            ],
            [
                'setting_key' => 'address',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Clinic full address',
                'is_active' => true,
            ],
            [
                'setting_key' => 'website',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Clinic website URL',
                'is_active' => true,
            ],

            // Appointment Settings
            [
                'setting_key' => 'appointment_duration',
                'setting_value' => '30',
                'setting_type' => 'integer',
                'description' => 'Default appointment duration in minutes',
                'is_active' => true,
            ],
            [
                'setting_key' => 'enable_online_booking',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Allow patients to book appointments online',
                'is_active' => true,
            ],
            [
                'setting_key' => 'booking_buffer',
                'setting_value' => '15',
                'setting_type' => 'integer',
                'description' => 'Buffer time between appointments in minutes',
                'is_active' => true,
            ],
            [
                'setting_key' => 'max_daily_appointments',
                'setting_value' => '20',
                'setting_type' => 'integer',
                'description' => 'Maximum number of appointments per day',
                'is_active' => true,
            ],

            // Notification Settings
            [
                'setting_key' => 'enable_sms',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable SMS notifications',
                'is_active' => true,
            ],
            [
                'setting_key' => 'enable_email',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable email notifications',
                'is_active' => true,
            ],
            [
                'setting_key' => 'enable_whatsapp',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable WhatsApp notifications',
                'is_active' => true,
            ],
            [
                'setting_key' => 'reminder_hours',
                'setting_value' => '24',
                'setting_type' => 'integer',
                'description' => 'Hours before appointment to send reminder',
                'is_active' => true,
            ],

            // Financial Settings
            [
                'setting_key' => 'currency',
                'setting_value' => 'USD',
                'setting_type' => 'string',
                'description' => 'Currency code (USD, EUR, etc.)',
                'is_active' => true,
            ],
            [
                'setting_key' => 'tax_rate',
                'setting_value' => '0',
                'setting_type' => 'integer',
                'description' => 'Tax rate percentage',
                'is_active' => true,
            ],
            [
                'setting_key' => 'late_payment_fee',
                'setting_value' => '0',
                'setting_type' => 'integer',
                'description' => 'Late payment fee amount',
                'is_active' => true,
            ],
            [
                'setting_key' => 'payment_terms',
                'setting_value' => 'Payment due upon service',
                'setting_type' => 'string',
                'description' => 'Default payment terms',
                'is_active' => true,
            ],

            // Display Settings
            [
                'setting_key' => 'theme_color',
                'setting_value' => '#1e40af',
                'setting_type' => 'string',
                'description' => 'Primary theme color (hex code)',
                'is_active' => true,
            ],
            [
                'setting_key' => 'language',
                'setting_value' => 'en',
                'setting_type' => 'string',
                'description' => 'Default language code',
                'is_active' => true,
            ],
            [
                'setting_key' => 'date_format',
                'setting_value' => 'Y-m-d',
                'setting_type' => 'string',
                'description' => 'Preferred date format',
                'is_active' => true,
            ],
            [
                'setting_key' => 'time_format',
                'setting_value' => '24',
                'setting_type' => 'string',
                'description' => '12-hour or 24-hour time format',
                'is_active' => true,
            ],

            // Working Hours (JSON example)
            [
                'setting_key' => 'working_hours',
                'setting_value' => json_encode([
                    'monday' => '9:00 AM - 5:00 PM',
                    'tuesday' => '9:00 AM - 5:00 PM',
                    'wednesday' => '9:00 AM - 5:00 PM',
                    'thursday' => '9:00 AM - 5:00 PM',
                    'friday' => '9:00 AM - 3:00 PM',
                    'saturday' => 'Closed',
                    'sunday' => 'Closed',
                ]),
                'setting_type' => 'json',
                'description' => 'Clinic working hours by day',
                'is_active' => true,
            ],

            // Social Media
            [
                'setting_key' => 'facebook',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Facebook page URL',
                'is_active' => true,
            ],
            [
                'setting_key' => 'instagram',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Instagram profile handle',
                'is_active' => true,
            ],
            [
                'setting_key' => 'twitter',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Twitter handle',
                'is_active' => true,
            ],
        ];

        foreach ($defaultSettings as $setting) {
            ClinicSetting::updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'setting_key' => $setting['setting_key'],
                ],
                [
                    'setting_value' => $setting['setting_value'],
                    'setting_type' => $setting['setting_type'],
                    'description' => $setting['description'],
                    'is_active' => $setting['is_active'],
                ]
            );
        }
    }
}
