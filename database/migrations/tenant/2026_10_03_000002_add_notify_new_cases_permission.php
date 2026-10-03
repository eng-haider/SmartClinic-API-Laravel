<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Add 'notify-new-cases': a secretary holding it is notified whenever a doctor
 * adds a case (see NotifySecretariesOfNewCase).
 *
 * It is granted per secretary from the permissions dialog, never through the
 * secretary role, so no existing secretary starts receiving these until the
 * clinic ticks it for them. The clinic_super_doctor role gets it only to match
 * config/rolesAndPermissions.php, which is what lists it in that dialog.
 *
 * Safe to run multiple times.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'notify-new-cases',
            'guard_name' => 'web',
        ]);

        // A new tenant has no roles yet - its seeder builds them from the config.
        $role = Role::where('name', 'clinic_super_doctor')->where('guard_name', 'web')->first();
        if ($role && !$role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'notify-new-cases')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
