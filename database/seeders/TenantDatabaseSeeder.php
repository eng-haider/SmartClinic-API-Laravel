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

        // Get permissions from config file
        $rolesConfig = config('rolesAndPermissions.roles', []);
        
        // Collect all unique permissions from all roles
        $allPermissions = [];
        foreach ($rolesConfig as $roleConfig) {
            if (isset($roleConfig['permissions'])) {
                $allPermissions = array_merge($allPermissions, $roleConfig['permissions']);
            }
        }
        $allPermissions = array_unique($allPermissions);

        // Create all permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign their permissions
        foreach ($rolesConfig as $roleName => $roleConfig) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            
            if (isset($roleConfig['permissions'])) {
                // Sync permissions (remove old, add new)
                $role->syncPermissions($roleConfig['permissions']);
            }
        }

        $this->command->info('✅ Roles and permissions seeded successfully');
    }
}

class TenantStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name_ar' => 'قيد الانتظار', 'name_en' => 'Pending', 'color' => '#FFA500', 'order' => 1],
            ['name_ar' => 'قيد التنفيذ', 'name_en' => 'In Progress', 'color' => '#2196F3', 'order' => 2],
            ['name_ar' => 'مكتمل', 'name_en' => 'Completed', 'color' => '#4CAF50', 'order' => 3],
            ['name_ar' => 'ملغي', 'name_en' => 'Cancelled', 'color' => '#F44336', 'order' => 4],
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
