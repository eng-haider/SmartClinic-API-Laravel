<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Get roles from config
        $config = config('rolesAndPermissions');
        $roles = $config['roles'];

        // Extract permissions from roles
        $allPermissions = [];
        foreach ($roles as $roleData) {
            if (isset($roleData['permissions'])) {
                $allPermissions = array_merge($allPermissions, $roleData['permissions']);
            }
        }
        $allPermissions = array_unique($allPermissions);

        // Create all permissions
        $this->command->info('Creating permissions...');
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $this->command->info("  ✓ {$permission}");
        }

        // Create roles and assign permissions
        $this->command->info("\nCreating roles and assigning permissions...");
        foreach ($roles as $roleName => $roleData) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
            ]);

            if (isset($roleData['permissions'])) {
                $role->syncPermissions($roleData['permissions']);
                $this->command->info("  ✓ {$roleData['display_name']} ({$roleName}) - " . count($roleData['permissions']) . " permissions");
            } else {
                $this->command->info("  ✓ {$roleData['display_name']} ({$roleName})");
            }
        }

        $this->command->info("\n✓ Roles and permissions created successfully!");
        $this->command->info("\nRoles created:");
        $this->command->info("  1. Super Admin - Full access to all clinics");
        $this->command->info("  2. Clinic Super Doctor - Manages their clinic (all patients, cases, bills)");
        $this->command->info("  3. Doctor - Can see clinic patients but only their own cases and bills");
        $this->command->info("  4. Secretary - Manages patients and reservations");
    }
}

