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

        // Create test user with admin role
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => '201001111111',
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        // Create doctor user
        $doctor = User::factory()->create([
            'name' => 'Doctor User',
            'email' => 'doctor@example.com',
            'phone' => '201002222222',
            'role' => 'doctor',
        ]);
        $doctor->assignRole('doctor');

        // Create nurse user
        $nurse = User::factory()->create([
            'name' => 'Nurse User',
            'email' => 'nurse@example.com',
            'phone' => '201003333333',
            'role' => 'nurse',
        ]);
        $nurse->assignRole('nurse');

        // Create receptionist user
        $receptionist = User::factory()->create([
            'name' => 'Receptionist User',
            'email' => 'receptionist@example.com',
            'phone' => '201004444444',
            'role' => 'receptionist',
        ]);
        $receptionist->assignRole('receptionist');

        // Create regular user
        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'phone' => '201005555555',
            'role' => 'user',
        ]);
        $user->assignRole('user');
    }
}
