<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Move every permission but view-own-clinic off the secretary role and onto
 * each secretary.
 *
 * As role permissions they could not be taken away: the secretary permissions
 * dialog only saves direct permissions, and login returns the role's and the
 * user's together. So a secretary whose boxes were all unticked still saw and
 * changed the appointment book, added patients, read reports and so on.
 * Granting them per user keeps every existing secretary exactly as they were,
 * and lets the clinic untick them. Same approach as
 * make_bill_permissions_optional_for_secretaries.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'view-clinic-patients',
        'create-patient',
        'edit-patient',
        'search-patient',
        'view-clinic-reservations',
        'create-reservation',
        'edit-reservation',
        'delete-reservation',
        'view-notes',
        'create-note',
        'view-clinic-expenses',
        'view-doctors',
        'view-warehouse',
        'create-warehouse',
        'edit-warehouse',
        'restock-warehouse',
        'view-reports',
        'view-message-templates',
        'preview-message-template',
        'view-automation-rules',
        'trigger-automation-rule',
        'view-automation-targets',
        'view-conversations',
        'send-conversation-message',
    ];

    public function up(): void
    {
        $role = Role::where('name', 'secretary')->where('guard_name', 'web')->first();

        // A new tenant has no roles yet - its seeder builds the role without them.
        if (!$role) {
            return;
        }

        // Every secretary gets them, not only while the role still holds them:
        // if RoleAndPermissionSeeder already synced the role from the new
        // config, the role is empty and secretaries would otherwise lose all.
        $permissions = Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get();

        if ($permissions->isEmpty()) {
            return;
        }

        foreach ($role->users as $user) {
            $user->givePermissionTo($permissions);
        }

        foreach ($permissions as $permission) {
            if ($role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

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
