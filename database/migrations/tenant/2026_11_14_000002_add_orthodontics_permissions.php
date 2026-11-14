<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Orthodontics module permissions:
 *  - view-orthodontics   open the orthodontics workspace of a case
 *  - manage-orthodontics upload files, save analyses and treatment plans
 *
 * Doctors and clinic super doctors get both, matching
 * config/rolesAndPermissions.php. Secretaries get neither through their role;
 * a clinic can grant them per secretary from the permissions dialog.
 *
 * Safe to run multiple times.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view-orthodontics', 'manage-orthodontics'];

    private const ROLES = ['super_admin', 'clinic_super_doctor', 'doctor'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            // A new tenant has no roles yet - its seeder builds them from the config.
            foreach (self::ROLES as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && !$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
