<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Move the bill permissions off the secretary role and onto each secretary.
 *
 * As role permissions they could not be taken away: the secretary permissions
 * dialog only saves direct permissions, and 'view-clinic-bills' was not even
 * listed there. So a secretary whose bill boxes were all unticked still saw the
 * patient's bills and totals. Granting them per user keeps every existing
 * secretary exactly as they were, and lets the clinic untick them.
 *
 * Every secretary gets them, not only while the role still holds them, so the
 * result is the same if RoleAndPermissionSeeder already synced the role from the
 * new config. Same approach as make_view_clinic_cases_optional_for_secretaries.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view-clinic-bills', 'create-bill', 'mark-bill-paid'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $role = Role::where('name', 'secretary')->where('guard_name', 'web')->first();

        // A new tenant has no roles yet - its seeder builds the role without them.
        if (!$role) {
            return;
        }

        $permissions = Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get();

        if ($permissions->isEmpty()) {
            return;
        }

        foreach ($role->users as $user) {
            $user->givePermissionTo($permissions);
        }

        foreach ($permissions as $permission) {
            $role->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = Role::where('name', 'secretary')->where('guard_name', 'web')->first();

        if (!$role) {
            return;
        }

        // Direct grants are left in place - they are harmless once the role
        // has the permissions again, and some may have been set by the clinic.
        $role->givePermissionTo(
            Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
