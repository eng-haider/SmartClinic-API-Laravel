<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     * This runs when a new clinic is created.
     */
    public function run(): void
    {
        $this->call([
            TenantRolesAndPermissionsSeeder::class,
            TenantStatusesSeeder::class,
            TenantCaseCategoriesSeeder::class,
        ]);
    }
}

class TenantRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Patient permissions
            'view patients',
            'create patients',
            'edit patients',
            'delete patients',
            
            // Case permissions
            'view cases',
            'create cases',
            'edit cases',
            'delete cases',
            
            // Reservation permissions
            'view reservations',
            'create reservations',
            'edit reservations',
            'delete reservations',
            
            // Bill permissions
            'view bills',
            'create bills',
            'edit bills',
            'delete bills',
            
            // Recipe permissions
            'view recipes',
            'create recipes',
            'edit recipes',
            'delete recipes',
            
            // Settings permissions
            'view settings',
            'edit settings',
            
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Reports
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $superDoctorRole = Role::firstOrCreate(['name' => 'clinic_super_doctor', 'guard_name' => 'web']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $secretaryRole = Role::firstOrCreate(['name' => 'secretary', 'guard_name' => 'web']);

        // Assign all permissions to clinic_super_doctor
        $superDoctorRole->givePermissionTo(Permission::all());

        // Assign doctor permissions
        $doctorRole->givePermissionTo([
            'view patients', 'create patients', 'edit patients',
            'view cases', 'create cases', 'edit cases',
            'view reservations', 'create reservations', 'edit reservations',
            'view bills', 'create bills', 'edit bills',
            'view recipes', 'create recipes', 'edit recipes',
            'view settings',
            'view reports',
        ]);

        // Assign secretary permissions
        $secretaryRole->givePermissionTo([
            'view patients', 'create patients', 'edit patients',
            'view reservations', 'create reservations', 'edit reservations',
            'view bills',
        ]);
    }
}

class TenantStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Pending', 'color' => '#FFA500', 'description' => 'Case is pending'],
            ['name' => 'In Progress', 'color' => '#2196F3', 'description' => 'Case is in progress'],
            ['name' => 'Completed', 'color' => '#4CAF50', 'description' => 'Case is completed'],
            ['name' => 'Cancelled', 'color' => '#F44336', 'description' => 'Case is cancelled'],
        ];

        foreach ($statuses as $status) {
            DB::table('statuses')->insertOrIgnore($status);
        }
    }
}

class TenantCaseCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'General Checkup', 'name_ar' => 'فحص عام', 'name_en' => 'General Checkup'],
            ['name' => 'Cleaning', 'name_ar' => 'تنظيف الأسنان', 'name_en' => 'Cleaning'],
            ['name' => 'Filling', 'name_ar' => 'حشوة', 'name_en' => 'Filling'],
            ['name' => 'Extraction', 'name_ar' => 'خلع', 'name_en' => 'Extraction'],
            ['name' => 'Root Canal', 'name_ar' => 'علاج عصب', 'name_en' => 'Root Canal'],
            ['name' => 'Crown', 'name_ar' => 'تاج', 'name_en' => 'Crown'],
            ['name' => 'Bridge', 'name_ar' => 'جسر', 'name_en' => 'Bridge'],
            ['name' => 'Implant', 'name_ar' => 'زراعة', 'name_en' => 'Implant'],
            ['name' => 'Orthodontics', 'name_ar' => 'تقويم الأسنان', 'name_en' => 'Orthodontics'],
            ['name' => 'Whitening', 'name_ar' => 'تبييض الأسنان', 'name_en' => 'Whitening'],
        ];

        foreach ($categories as $category) {
            DB::table('case_categories')->insertOrIgnore($category);
        }
    }
}
