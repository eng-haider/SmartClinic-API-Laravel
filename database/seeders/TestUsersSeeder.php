<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates test users with all available roles:
     * - super_admin
     * - clinic_super_doctor
     * - doctor
     * - secretary
     */
    public function run(): void
    {
        // Create test clinics
        $clinic1 = Clinic::create([
            'name' => 'Smart Dental Clinic',
            'address' => '123 Main Street, Cairo, Egypt',
            'whatsapp_phone' => '201001234567',
            'whatsapp_message_count' => 0,
            'doctor_mony' => 50,
            'show_image_case' => true,
            'teeth_v2' => true,
            'send_msg' => false,
            'show_rx_id' => true,
            'api_whatsapp' => false,
        ]);

        // Create clinic settings for clinic1
        ClinicSetting::create([
            'clinic_id' => $clinic1->id,
            'setting_key' => 'working_hours',
            'setting_value' => '9:00 AM - 5:00 PM',
            'setting_type' => 'string',
            'description' => 'Clinic working hours',
            'is_active' => true,
        ]);

        $clinic2 = Clinic::create([
            'name' => 'Advanced Medical Center',
            'address' => '456 Healthcare Avenue, Alexandria, Egypt',
            'whatsapp_phone' => '201009876543',
            'whatsapp_message_count' => 0,
            'doctor_mony' => 60,
            'show_image_case' => true,
            'teeth_v2' => false,
            'send_msg' => true,
            'show_rx_id' => false,
            'api_whatsapp' => true,
        ]);

        // Create clinic settings for clinic2
        ClinicSetting::create([
            'clinic_id' => $clinic2->id,
            'setting_key' => 'working_hours',
            'setting_value' => '10:00 AM - 8:00 PM',
            'setting_type' => 'string',
            'description' => 'Clinic working hours',
            'is_active' => true,
        ]);

        // 1. Super Admin - Full system access
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'phone' => '201111111111',
            'email' => 'superadmin@smartclinic.com',
            'password' => Hash::make('password123'),
            'clinic_id' => null, // Super admin doesn't belong to specific clinic
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $this->command->info('✅ Created Super Admin');
        $this->command->info('   Phone: 201111111111');
        $this->command->info('   Email: superadmin@smartclinic.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: super_admin');
        $this->command->info('');

        // 2. Clinic Super Doctor (Clinic 1) - Clinic Owner
        $clinicSuperDoctor1 = User::create([
            'name' => 'Dr. Ahmed Hassan',
            'phone' => '201222222222',
            'email' => 'ahmed@smartdental.com',
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic1->id,
            'is_active' => true,
        ]);
        $clinicSuperDoctor1->assignRole('clinic_super_doctor');

        $this->command->info('✅ Created Clinic Super Doctor (Clinic 1)');
        $this->command->info('   Phone: 201222222222');
        $this->command->info('   Email: ahmed@smartdental.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: clinic_super_doctor');
        $this->command->info('   Clinic: Smart Dental Clinic');
        $this->command->info('');

        // 3. Clinic Super Doctor (Clinic 2) - Clinic Owner
        $clinicSuperDoctor2 = User::create([
            'name' => 'Dr. Sarah Mohamed',
            'phone' => '201333333333',
            'email' => 'sarah@advancedmedical.com',
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic2->id,
            'is_active' => true,
        ]);
        $clinicSuperDoctor2->assignRole('clinic_super_doctor');

        $this->command->info('✅ Created Clinic Super Doctor (Clinic 2)');
        $this->command->info('   Phone: 201333333333');
        $this->command->info('   Email: sarah@advancedmedical.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: clinic_super_doctor');
        $this->command->info('   Clinic: Advanced Medical Center');
        $this->command->info('');

        // 4. Doctor (Clinic 1) - Regular Doctor
        $doctor1 = User::create([
            'name' => 'Dr. Omar Khalil',
            'phone' => '201444444444',
            'email' => 'omar@smartdental.com',
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic1->id,
            'is_active' => true,
        ]);
        $doctor1->assignRole('doctor');

        $this->command->info('✅ Created Doctor (Clinic 1)');
        $this->command->info('   Phone: 201444444444');
        $this->command->info('   Email: omar@smartdental.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: doctor');
        $this->command->info('   Clinic: Smart Dental Clinic');
        $this->command->info('');

        // 5. Doctor (Clinic 2) - Regular Doctor
        $doctor2 = User::create([
            'name' => 'Dr. Fatima Ali',
            'phone' => '201555555555',
            'email' => 'fatima@advancedmedical.com',
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic2->id,
            'is_active' => true,
        ]);
        $doctor2->assignRole('doctor');

        $this->command->info('✅ Created Doctor (Clinic 2)');
        $this->command->info('   Phone: 201555555555');
        $this->command->info('   Email: fatima@advancedmedical.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: doctor');
        $this->command->info('   Clinic: Advanced Medical Center');
        $this->command->info('');

        // 6. Secretary (Clinic 1)
        $secretary1 = User::create([
            'name' => 'Nadia Ibrahim',
            'phone' => '201666666666',
            'email' => 'nadia@smartdental.com',
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic1->id,
            'is_active' => true,
        ]);
        $secretary1->assignRole('secretary');

        $this->command->info('✅ Created Secretary (Clinic 1)');
        $this->command->info('   Phone: 201666666666');
        $this->command->info('   Email: nadia@smartdental.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: secretary');
        $this->command->info('   Clinic: Smart Dental Clinic');
        $this->command->info('');

        // 7. Secretary (Clinic 2)
        $secretary2 = User::create([
            'name' => 'Mona Saleh',
            'phone' => '201777777777',
            'email' => 'mona@advancedmedical.com',
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic2->id,
            'is_active' => true,
        ]);
        $secretary2->assignRole('secretary');

        $this->command->info('✅ Created Secretary (Clinic 2)');
        $this->command->info('   Phone: 201777777777');
        $this->command->info('   Email: mona@advancedmedical.com');
        $this->command->info('   Password: password123');
        $this->command->info('   Role: secretary');
        $this->command->info('   Clinic: Advanced Medical Center');
        $this->command->info('');

        // Summary
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📊 SUMMARY - Test Users Created Successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');
        $this->command->info('🏥 Clinics Created: 2');
        $this->command->info('   1. Smart Dental Clinic (ID: ' . $clinic1->id . ')');
        $this->command->info('   2. Advanced Medical Center (ID: ' . $clinic2->id . ')');
        $this->command->info('');
        $this->command->info('👥 Users Created: 7');
        $this->command->info('   - 1 Super Admin (system-wide access)');
        $this->command->info('   - 2 Clinic Super Doctors (clinic owners)');
        $this->command->info('   - 2 Doctors (regular doctors)');
        $this->command->info('   - 2 Secretaries (front desk staff)');
        $this->command->info('');
        $this->command->info('🔑 All passwords: password123');
        $this->command->info('');
        $this->command->info('📝 Quick Login Reference:');
        $this->command->info('   Super Admin:              201111111111');
        $this->command->info('   Clinic Owner (Clinic 1):  201222222222');
        $this->command->info('   Clinic Owner (Clinic 2):  201333333333');
        $this->command->info('   Doctor (Clinic 1):        201444444444');
        $this->command->info('   Doctor (Clinic 2):        201555555555');
        $this->command->info('   Secretary (Clinic 1):     201666666666');
        $this->command->info('   Secretary (Clinic 2):     201777777777');
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
