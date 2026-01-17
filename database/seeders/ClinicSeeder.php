<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default clinic
        $clinic = Clinic::create([
            'name' => 'SmartClinic Dental Center',
            'address' => '123 Medical Street, Health District',
            'whatsapp_phone' => '+9647700000000',
            'show_image_case' => true,
            'teeth_v2' => true,
            'send_msg' => true,
            'show_rx_id' => true,
            'api_whatsapp' => true,
        ]);

        // Create default settings for the clinic
        $defaultSettings = [
            // Clinic Registration & Configuration
            [
                'setting_key' => 'clinic_reg_num',
                'setting_value' => 'CLINIC-2025-001',
                'setting_type' => 'string',
                'description' => 'Clinic registration number',
            ],
            [
                'setting_key' => 'paid_at_secretary',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable payment processing at secretary desk',
            ],
            [
                'setting_key' => 'doctor_show_all_patient',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Allow doctors to view all patients',
            ],
            [
                'setting_key' => 'paid_to_doctor',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable paid to doctor feature',
            ],
            [
                'setting_key' => 'doctor_can_pay',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Allow doctors to process payments',
            ],
            [
                'setting_key' => 'add_from_where_come',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Track patient source/referral',
            ],
            [
                'setting_key' => 'use_credit',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable credit system for payments',
            ],
            // General Settings
            [
                'setting_key' => 'appointment_duration',
                'setting_value' => '30',
                'setting_type' => 'integer',
                'description' => 'Default appointment duration in minutes',
            ],
            [
                'setting_key' => 'enable_sms_notifications',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable SMS notifications for appointments',
            ],
            [
                'setting_key' => 'enable_email_notifications',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable email notifications',
            ],
            [
                'setting_key' => 'working_hours',
                'setting_value' => json_encode([
                    'start' => '09:00',
                    'end' => '17:00',
                    'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'],
                ]),
                'setting_type' => 'json',
                'description' => 'Clinic working hours',
            ],
            [
                'setting_key' => 'max_appointments_per_day',
                'setting_value' => '20',
                'setting_type' => 'integer',
                'description' => 'Maximum number of appointments per day',
            ],
            [
                'setting_key' => 'currency',
                'setting_value' => 'IQD',
                'setting_type' => 'string',
                'description' => 'Clinic currency',
            ],
            [
                'setting_key' => 'timezone',
                'setting_value' => 'Asia/Baghdad',
                'setting_type' => 'string',
                'description' => 'Clinic timezone',
            ],
            [
                'setting_key' => 'enable_online_booking',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Allow patients to book appointments online',
            ],
            [
                'setting_key' => 'reminder_before_hours',
                'setting_value' => '24',
                'setting_type' => 'integer',
                'description' => 'Send appointment reminder hours before',
            ],
            [
                'setting_key' => 'enable_patient_portal',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable patient portal access',
            ],
        ];

        foreach ($defaultSettings as $setting) {
            ClinicSetting::create(array_merge($setting, ['clinic_id' => $clinic->id]));
        }

        $this->command->info('✓ Default clinic and settings created successfully!');

        // Optionally create additional test clinics
        if (app()->environment('local')) {
            Clinic::factory(3)->create()->each(function ($clinic) {
                // Create some random settings for each clinic
                ClinicSetting::factory(5)->create(['clinic_id' => $clinic->id]);
            });
            
            $this->command->info('✓ Additional test clinics created!');
        }
    }
}
