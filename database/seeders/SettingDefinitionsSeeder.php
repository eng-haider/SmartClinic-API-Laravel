<?php

namespace Database\Seeders;

use App\Models\SettingDefinition;
use Illuminate\Database\Seeder;

/**
 * SettingDefinitionsSeeder
 * 
 * Seeds the master list of setting definitions.
 * These define what settings every clinic will have.
 */
class SettingDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            // ========================
            // GENERAL CATEGORY
            // ========================
            [
                'setting_key' => 'clinic_name',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Official clinic name',
                'category' => 'general',
                'display_order' => 1,
                'is_required' => true,
            ],
            [
                'setting_key' => 'logo',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Clinic logo image path',
                'category' => 'general',
                'display_order' => 2,
                'is_required' => false,
            ],
            [
                'setting_key' => 'phone',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Primary contact phone number',
                'category' => 'general',
                'display_order' => 3,
                'is_required' => false,
            ],
            [
                'setting_key' => 'email',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Primary contact email address',
                'category' => 'general',
                'display_order' => 4,
                'is_required' => false,
            ],
            [
                'setting_key' => 'address',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Clinic full address',
                'category' => 'general',
                'display_order' => 5,
                'is_required' => false,
            ],
            [
                'setting_key' => 'website',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Clinic website URL',
                'category' => 'general',
                'display_order' => 6,
                'is_required' => false,
            ],

            // ========================
            // APPOINTMENT CATEGORY
            // ========================
            [
                'setting_key' => 'appointment_duration',
                'setting_type' => 'integer',
                'default_value' => '30',
                'description' => 'Default appointment duration in minutes',
                'category' => 'appointment',
                'display_order' => 1,
                'is_required' => false,
            ],
            [
                'setting_key' => 'enable_online_booking',
                'setting_type' => 'boolean',
                'default_value' => '1',
                'description' => 'Allow patients to book appointments online',
                'category' => 'appointment',
                'display_order' => 2,
                'is_required' => false,
            ],
            [
                'setting_key' => 'booking_buffer',
                'setting_type' => 'integer',
                'default_value' => '15',
                'description' => 'Buffer time between appointments in minutes',
                'category' => 'appointment',
                'display_order' => 3,
                'is_required' => false,
            ],
            [
                'setting_key' => 'max_daily_appointments',
                'setting_type' => 'integer',
                'default_value' => '20',
                'description' => 'Maximum number of appointments per day',
                'category' => 'appointment',
                'display_order' => 4,
                'is_required' => false,
            ],
            [
                'setting_key' => 'working_hours',
                'setting_type' => 'json',
                'default_value' => json_encode([
                    'monday' => ['start' => '09:00', 'end' => '17:00', 'enabled' => true],
                    'tuesday' => ['start' => '09:00', 'end' => '17:00', 'enabled' => true],
                    'wednesday' => ['start' => '09:00', 'end' => '17:00', 'enabled' => true],
                    'thursday' => ['start' => '09:00', 'end' => '17:00', 'enabled' => true],
                    'friday' => ['start' => '09:00', 'end' => '15:00', 'enabled' => true],
                    'saturday' => ['start' => '09:00', 'end' => '12:00', 'enabled' => false],
                    'sunday' => ['start' => '09:00', 'end' => '12:00', 'enabled' => false],
                ]),
                'description' => 'Clinic working hours by day',
                'category' => 'appointment',
                'display_order' => 5,
                'is_required' => false,
            ],

            // ========================
            // NOTIFICATION CATEGORY
            // ========================
            [
                'setting_key' => 'enable_sms',
                'setting_type' => 'boolean',
                'default_value' => '1',
                'description' => 'Enable SMS notifications',
                'category' => 'notification',
                'display_order' => 1,
                'is_required' => false,
            ],
            [
                'setting_key' => 'enable_email',
                'setting_type' => 'boolean',
                'default_value' => '1',
                'description' => 'Enable email notifications',
                'category' => 'notification',
                'display_order' => 2,
                'is_required' => false,
            ],
            [
                'setting_key' => 'enable_whatsapp',
                'setting_type' => 'boolean',
                'default_value' => '0',
                'description' => 'Enable WhatsApp notifications',
                'category' => 'notification',
                'display_order' => 3,
                'is_required' => false,
            ],
            [
                'setting_key' => 'reminder_hours',
                'setting_type' => 'integer',
                'default_value' => '24',
                'description' => 'Hours before appointment to send reminder',
                'category' => 'notification',
                'display_order' => 4,
                'is_required' => false,
            ],

            // ========================
            // FINANCIAL CATEGORY
            // ========================
            [
                'setting_key' => 'currency',
                'setting_type' => 'string',
                'default_value' => 'USD',
                'description' => 'Currency code (USD, EUR, IQD, etc.)',
                'category' => 'financial',
                'display_order' => 1,
                'is_required' => true,
            ],
            [
                'setting_key' => 'tax_rate',
                'setting_type' => 'integer',
                'default_value' => '0',
                'description' => 'Tax rate percentage',
                'category' => 'financial',
                'display_order' => 2,
                'is_required' => false,
            ],
            [
                'setting_key' => 'late_payment_fee',
                'setting_type' => 'integer',
                'default_value' => '0',
                'description' => 'Late payment fee amount',
                'category' => 'financial',
                'display_order' => 3,
                'is_required' => false,
            ],
            [
                'setting_key' => 'payment_terms',
                'setting_type' => 'string',
                'default_value' => 'Payment due upon service',
                'description' => 'Default payment terms text',
                'category' => 'financial',
                'display_order' => 4,
                'is_required' => false,
            ],

            // ========================
            // DISPLAY CATEGORY
            // ========================
            [
                'setting_key' => 'theme_color',
                'setting_type' => 'string',
                'default_value' => '#1e40af',
                'description' => 'Primary theme color (hex code)',
                'category' => 'display',
                'display_order' => 1,
                'is_required' => false,
            ],
            [
                'setting_key' => 'language',
                'setting_type' => 'string',
                'default_value' => 'en',
                'description' => 'Default language code (en, ar, etc.)',
                'category' => 'display',
                'display_order' => 2,
                'is_required' => false,
            ],
            [
                'setting_key' => 'date_format',
                'setting_type' => 'string',
                'default_value' => 'Y-m-d',
                'description' => 'Date format (Y-m-d, d/m/Y, etc.)',
                'category' => 'display',
                'display_order' => 3,
                'is_required' => false,
            ],
            [
                'setting_key' => 'time_format',
                'setting_type' => 'string',
                'default_value' => '24',
                'description' => 'Time format (12 or 24 hour)',
                'category' => 'display',
                'display_order' => 4,
                'is_required' => false,
            ],

            // ========================
            // SOCIAL CATEGORY
            // ========================
            [
                'setting_key' => 'facebook',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Facebook page URL',
                'category' => 'social',
                'display_order' => 1,
                'is_required' => false,
            ],
            [
                'setting_key' => 'instagram',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Instagram profile URL',
                'category' => 'social',
                'display_order' => 2,
                'is_required' => false,
            ],
            [
                'setting_key' => 'twitter',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'Twitter profile URL',
                'category' => 'social',
                'display_order' => 3,
                'is_required' => false,
            ],
            [
                'setting_key' => 'whatsapp',
                'setting_type' => 'string',
                'default_value' => '',
                'description' => 'WhatsApp contact number',
                'category' => 'social',
                'display_order' => 4,
                'is_required' => false,
            ],

            // ========================
            // DENTAL/MEDICAL CATEGORY
            // ========================
            [
                'setting_key' => 'tooth_condition_colors',
                'setting_type' => 'json',
                'default_value' => json_encode([
                    [
                        'id' => 1,
                        'name' => '',
                        'color' => '#FF5252',
                        'hex_code' => '#FF5252',
                    ],
                    [
                        'id' => 2,
                        'name' => '',
                        'color' => '#2196F3',
                        'hex_code' => '#2196F3',
                    ],
                    [
                        'id' => 3,
                        'name' => '',
                        'color' => '#4CAF50',
                        'hex_code' => '#4CAF50',
                    ],
                    [
                        'id' => 4,
                        'name' => '',
                        'color' => '#FFEB3B',
                        'hex_code' => '#FFEB3B',
                    ],
                    [
                        'id' => 5,
                        'name' => '',
                        'color' => '#FF9800',
                        'hex_code' => '#FF9800',
                    ],
                    [
                        'id' => 6,
                        'name' => '',
                        'color' => '#9C27B0',
                        'hex_code' => '#9C27B0',
                    ],
                ]),
                'description' => 'Tooth condition colors for dental charts (doctors can customize names)',
                'category' => 'general',
                'display_order' => 10,
                'is_required' => false,
            ],
            [
                'setting_key' => 'tooth_statuses',
                'setting_type' => 'json',
                'default_value' => json_encode([
                    [
                        'id' => 1,
                        'name' => 'Healthy',
                        'color' => '#22C55E',
                        'icon' => '✓',
                        'is_active' => true,
                    ],
                    [
                        'id' => 2,
                        'name' => 'Cavity',
                        'color' => '#EF4444',
                        'icon' => '⚠',
                        'is_active' => true,
                    ],
                    [
                        'id' => 3,
                        'name' => 'Filled',
                        'color' => '#3B82F6',
                        'icon' => '■',
                        'is_active' => true,
                    ],
                    [
                        'id' => 4,
                        'name' => 'Missing',
                        'color' => '#6B7280',
                        'icon' => '✗',
                        'is_active' => true,
                    ],
                    [
                        'id' => 5,
                        'name' => 'Crown',
                        'color' => '#F59E0B',
                        'icon' => '♔',
                        'is_active' => true,
                    ],
                    [
                        'id' => 6,
                        'name' => 'Root Canal',
                        'color' => '#8B5CF6',
                        'icon' => '⊕',
                        'is_active' => true,
                    ],
                    [
                        'id' => 7,
                        'name' => 'Implant',
                        'color' => '#14B8A6',
                        'icon' => '⊛',
                        'is_active' => true,
                    ],
                    [
                        'id' => 8,
                        'name' => 'Bridge',
                        'color' => '#EC4899',
                        'icon' => '⊞',
                        'is_active' => true,
                    ],
                ]),
                'description' => 'Available tooth status options for dental records',
                'category' => 'general',
                'display_order' => 11,
                'is_required' => false,
            ],
        ];

        foreach ($definitions as $definition) {
            SettingDefinition::updateOrCreate(
                ['setting_key' => $definition['setting_key']],
                array_merge($definition, ['is_active' => true])
            );
        }

        $this->command->info('✅ Setting definitions seeded successfully! (' . count($definitions) . ' definitions)');
    }
}
