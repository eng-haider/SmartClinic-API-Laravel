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

        // Define permissions
        $permissions = [
            // Patient permissions
            'view-patients',
            'create-patient',
            'edit-patient',
            'delete-patient',
            'search-patient',

            // User management permissions
            'view-users',
            'create-user',
            'edit-user',
            'delete-user',

            // Permission management
            'manage-permissions',
            'manage-roles',
        ];

        // Create permissions
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // Define roles with their permissions
        $roles = [
            'admin' => $permissions, // All permissions
            'doctor' => [
                'view-patients',
                'create-patient',
                'edit-patient',
                'search-patient',
                'view-users',
            ],
            'nurse' => [
                'view-patients',
                'create-patient',
                'edit-patient',
                'search-patient',
            ],
            'receptionist' => [
                'view-patients',
                'create-patient',
                'search-patient',
            ],
            'user' => [
                'view-patients',
                'search-patient',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            // Get permission models
            $permissionModels = Permission::whereIn('name', $permissions)->get();
            $role->syncPermissions($permissionModels);
        }
    }
}
