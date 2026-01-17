<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleAndPermissionSeeder::class);
        
        // Seed clinics before creating users
        $this->call(ClinicSeeder::class);

        // Create test user with super_admin role
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'phone' => '201001111111',
        ]);
        $superAdmin->assignRole('super_admin');

        // Create clinic super doctor user
        $clinicSuperDoctor = User::factory()->create([
            'name' => 'Clinic Super Doctor',
            'email' => 'clinicsuperdoctor@example.com',
            'phone' => '201002222222',
            'clinic_id' => 1,
        ]);
        $clinicSuperDoctor->assignRole('clinic_super_doctor');

        // Create doctor user
        $doctor = User::factory()->create([
            'name' => 'Doctor User',
            'email' => 'doctor@example.com',
            'phone' => '201003333333',
            'clinic_id' => 1,
        ]);
        $doctor->assignRole('doctor');

        // Create secretary user
        $secretary = User::factory()->create([
            'name' => 'Secretary User',
            'email' => 'secretary@example.com',
            'phone' => '201004444444',
            'clinic_id' => 1,
        ]);
        $secretary->assignRole('secretary');
    }
}
