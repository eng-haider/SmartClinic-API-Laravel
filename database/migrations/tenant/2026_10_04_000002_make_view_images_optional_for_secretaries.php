<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Move 'view-images' off the secretary role and onto each secretary.
 *
 * As a role permission it was locked in the secretary permissions dialog, so a
 * clinic could not hide patient photos from a secretary. Granting it per user
 * keeps every existing secretary exactly as they were, and lets the clinic
 * untick it for the ones who should not see photos. Same approach as
 * 2026_09_30_000001_make_view_clinic_cases_optional_for_secretaries.
 *
 * Safe to run multiple times - once the role no longer holds the permission
 * there is nothing left to move.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $role = Role::where('name', 'secretary')->where('guard_name', 'web')->first();
        $permission = Permission::where('name', 'view-images')->where('guard_name', 'web')->first();

        // A new tenant has no roles yet - its seeder builds the role without it.
        if (!$role || !$permission || !$role->hasPermissionTo($permission)) {
            return;
        }

        foreach ($role->users as $user) {
            $user->givePermissionTo($permission);
        }

        $role->revokePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = Role::where('name', 'secretary')->where('guard_name', 'web')->first();
        $permission = Permission::where('name', 'view-images')->where('guard_name', 'web')->first();

        if (!$role || !$permission) {
            return;
        }

        // Direct grants are left in place - they are harmless once the role
        // has the permission again, and some may have been set by the clinic.
        $role->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
