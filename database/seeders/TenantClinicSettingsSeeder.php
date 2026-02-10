<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * TenantClinicSettingsSeeder
 * 
 * Seeds default clinic settings for a newly created tenant.
 * This runs in the tenant database context.
 * 
 * Note: This seeder creates settings directly without relying on 
 * setting_definitions table (which is in central database).
 */
class TenantClinicSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSettings = $this->getDefaultSettings();

        foreach ($defaultSettings as $setting) {
            DB::table('clinic_settings')->updateOrInsert(
                ['setting_key' => $setting['setting_key']],
                [
                    'setting_value' => $setting['setting_value'],
                    'setting_type' => $setting['setting_type'],
                    'description' => $setting['description'],
                    'is_active' => $setting['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✓ Clinic settings initialized with ' . count($defaultSettings) . ' default settings');
    }

    /**
     * Get default clinic settings.
     * These are the same settings defined in SettingDefinitionsSeeder.
     */
    private function getDefaultSettings(): array
    {
        return [
            // ========================
            // GENERAL CATEGORY
            // ========================
            [
                'setting_key' => 'clinic_name',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Official clinic name',
                'is_active' => true,
            ],
            [
                'setting_key' => 'logo',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Clinic logo image path',
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
                'description' => 'Primary contact email',
                'is_active' => true,
            ],
            [
                'setting_key' => 'address',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Clinic physical address',
                'is_active' => true,
            ],
            [
                'setting_key' => 'clinic_reg_num',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Clinic registration or license number',
                'is_active' => true,
            ],
            [
                'setting_key' => 'timezone',
                'setting_value' => 'Asia/Baghdad',
                'setting_type' => 'string',
                'description' => 'Clinic timezone',
                'is_active' => true,
            ],
            [
                'setting_key' => 'language',
                'setting_value' => 'ar',
                'setting_type' => 'string',
                'description' => 'Default language (ar, en)',
                'is_active' => true,
            ],
            [
                'setting_key' => 'currency',
                'setting_value' => 'IQD',
                'setting_type' => 'string',
                'description' => 'Currency code (IQD, USD, etc.)',
                'is_active' => true,
            ],

            // ========================
            // APPOINTMENT CATEGORY
            // ========================
            [
                'setting_key' => 'appointment_duration',
                'setting_value' => '30',
                'setting_type' => 'integer',
                'description' => 'Default appointment duration in minutes',
                'is_active' => true,
            ],
            [
                'setting_key' => 'working_hours',
                'setting_value' => json_encode([
                    'sunday' => '9:00 AM - 5:00 PM',
                    'monday' => '9:00 AM - 5:00 PM',
                    'tuesday' => '9:00 AM - 5:00 PM',
                    'wednesday' => '9:00 AM - 5:00 PM',
                    'thursday' => '9:00 AM - 5:00 PM',
                    'friday' => 'Closed',
                    'saturday' => 'Closed',
                ]),
                'setting_type' => 'json',
                'description' => 'Clinic working hours',
                'is_active' => true,
            ],
            [
                'setting_key' => 'enable_online_booking',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable online appointment booking',
                'is_active' => true,
            ],
            [
                'setting_key' => 'max_appointments_per_day',
                'setting_value' => '20',
                'setting_type' => 'integer',
                'description' => 'Maximum appointments per day',
                'is_active' => true,
            ],

            // ========================
            // NOTIFICATION CATEGORY
            // ========================
            [
                'setting_key' => 'enable_sms',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable SMS notifications',
                'is_active' => true,
            ],
            [
                'setting_key' => 'enable_email',
                'setting_value' => '0',
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
                'setting_key' => 'whatsapp_number',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp business number',
                'is_active' => true,
            ],
            [
                'setting_key' => 'reminder_before_hours',
                'setting_value' => '24',
                'setting_type' => 'integer',
                'description' => 'Send appointment reminder hours before',
                'is_active' => true,
            ],

            // ========================
            // FINANCIAL CATEGORY
            // ========================
            [
                'setting_key' => 'tax_rate',
                'setting_value' => '0',
                'setting_type' => 'integer',
                'description' => 'Tax rate percentage',
                'is_active' => true,
            ],
            [
                'setting_key' => 'enable_invoicing',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable invoice generation',
                'is_active' => true,
            ],
            [
                'setting_key' => 'default_payment_method',
                'setting_value' => 'cash',
                'setting_type' => 'string',
                'description' => 'Default payment method (cash, card, transfer)',
                'is_active' => true,
            ],

            // ========================
            // DISPLAY CATEGORY
            // ========================
            [
                'setting_key' => 'show_image_case',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Show case images in UI',
                'is_active' => true,
            ],
            [
                'setting_key' => 'show_rx_id',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Show prescription ID',
                'is_active' => true,
            ],
            [
                'setting_key' => 'teeth_v2',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Use version 2 of teeth diagram',
                'is_active' => true,
            ],
            [
                'setting_key' => 'tooth_colors',
                'setting_value' => json_encode([
                    [
                        'id' => 'healthy',
                        'name' => 'Healthy',
                        'color' => '#FFFFFF',
                    ],
                    [
                        'id' => 'cavity',
                        'name' => 'Cavity',
                        'color' => '#FF6B6B',
                    ],
                    [
                        'id' => 'filling',
                        'name' => 'Filling',
                        'color' => '#4ECDC4',
                    ],
                    [
                        'id' => 'crown',
                        'name' => 'Crown',
                        'color' => '#FFD93D',
                    ],
                    [
                        'id' => 'missing',
                        'name' => 'Missing',
                        'color' => '#95A5A6',
                    ],
                    [
                        'id' => 'implant',
                        'name' => 'Implant',
                        'color' => '#3498DB',
                    ],
                    [
                        'id' => 'root_canal',
                        'name' => 'Root Canal',
                        'color' => '#9B59B6',
                    ],
                ]),
                'setting_type' => 'json',
                'description' => 'Tooth status colors for dental chart',
                'is_active' => true,
            ],

            // ========================
            // SOCIAL MEDIA CATEGORY
            // ========================
            [
                'setting_key' => 'facebook_url',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Facebook page URL',
                'is_active' => true,
            ],
            [
                'setting_key' => 'instagram_url',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Instagram profile URL',
                'is_active' => true,
            ],
            [
                'setting_key' => 'twitter_url',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Twitter/X profile URL',
                'is_active' => true,
            ],

            // ========================
            // MEDICAL/DENTAL CATEGORY
            // ========================
            [
                'setting_key' => 'specializations',
                'setting_value' => json_encode([
                    'general_dentistry',
                    'orthodontics',
                    'endodontics',
                    'periodontics',
                    'prosthodontics',
                ]),
                'setting_type' => 'json',
                'description' => 'Available specializations',
                'is_active' => true,
            ],
        ];
    }
}
