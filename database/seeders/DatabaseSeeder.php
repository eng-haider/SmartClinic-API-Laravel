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
        
        // Seed test users with all roles (includes clinic creation)
        $this->call(TestUsersSeeder::class);
        
        // Seed complete data for account 07700281899
        // Uncomment the line below to seed complete clinic data
        // $this->call(CompleteDataSeeder::class);
        
        // Seed additional data (optional)
        // $this->call(FromWhereComeSeeder::class);
        // $this->call(CaseDataSeeder::class);
    }
}
