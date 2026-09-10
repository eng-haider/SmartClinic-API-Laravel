<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class CentralClinicUsersSeeder extends Seeder
{
    /**
     * ============================================================
     * CONFIGURATION - Set the tenant you want to publish centrally
     * ============================================================
     *
     * smartLogin() (AuthService::smartLogin) needs a user to exist in BOTH
     * databases: the central `users` row authenticates phone + password and
     * points at a clinic, then the tenant `users` row supplies roles and
     * permissions. OldDatabaseMigrationSeeder only fills the tenant side, so
     * this seeder mirrors those users into the central database.
     *
     * It also creates the central `clinics` row, because smartLogin resolves
     * the tenant through $centralUser->clinic and fails without it.
     */
    private string $tenantId = 'clinic_1'; // <-- CHANGE THIS to the tenant you want

    /**
     * Old-system staff role => the central users.role enum.
     * The enum is enum('admin','doctor','nurse','receptionist','user').
     */
    private const ROLE_MAP = [
        'clinic_super_doctor' => 'admin',
        'doctor' => 'doctor',
        'secretary' => 'receptionist',
    ];

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('👤 CENTRAL CLINIC + USERS SEEDER');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("   Tenant: {$this->tenantId}");

        $tenant = Tenant::find($this->tenantId);

        if (!$tenant) {
            $this->command->error("❌ Tenant '{$this->tenantId}' not found in the central database.");
            return;
        }

        // 1. The central clinics row that $centralUser->clinic resolves to.
        $this->command->info('🏥 Creating central clinic record...');

        DB::table('clinics')->updateOrInsert(
            ['id' => $tenant->id],
            [
                'name' => $tenant->name,
                'specialty' => $tenant->specialty ?: 'dental',
                'address' => $tenant->address,
                'rx_img' => $tenant->rx_img,
                'whatsapp_template_sid' => $tenant->whatsapp_template_sid,
                'whatsapp_message_count' => (int) $tenant->whatsapp_message_count,
                'whatsapp_phone' => $tenant->whatsapp_phone,
                'show_image_case' => (int) $tenant->show_image_case,
                'doctor_mony' => (int) $tenant->doctor_mony,
                'teeth_v2' => (int) $tenant->teeth_v2,
                'send_msg' => (int) $tenant->send_msg,
                'show_rx_id' => (int) $tenant->show_rx_id,
                'logo' => $tenant->logo,
                'api_whatsapp' => (int) $tenant->api_whatsapp,
                'has_ai_bot' => (int) $tenant->has_ai_bot,
                'created_at' => $tenant->created_at,
                'updated_at' => now(),
            ]
        );

        $this->command->info("   ✓ Clinic: {$tenant->name} (id: {$tenant->id})");

        // 2. Read the tenant's staff, with their Spatie role, straight from the
        //    tenant database. Password hashes are copied verbatim so the same
        //    credentials work against both databases.
        $this->command->info('👥 Mirroring tenant users into the central database...');

        $tenantUsers = [];

        $tenant->run(function () use (&$tenantUsers) {
            $rows = DB::table('users')
                ->leftJoin('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
                ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->select('users.*', 'roles.name as role_name')
                ->orderBy('users.id')
                ->get();

            // Detach from the tenant connection before tenancy ends
            $tenantUsers = $rows->map(fn($r) => (array) $r)->all();
        });

        if (empty($tenantUsers)) {
            $this->command->warn('   ⚠ No users found in the tenant database');
            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($tenantUsers as $user) {
            $role = self::ROLE_MAP[$user['role_name'] ?? ''] ?? 'user';

            $existing = DB::table('users')->where('phone', $user['phone'])->first();

            $payload = [
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'], // already hashed, copy as-is
                'clinic_id' => $this->tenantId,
                'role' => $role,
                'is_active' => (int) $user['is_active'],
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update($payload);
                $updated++;
                $this->command->warn("   ⚠ Updated existing: {$user['phone']} ({$user['name']})");
                continue;
            }

            DB::table('users')->insert($payload + [
                'phone' => $user['phone'],
                'created_at' => $user['created_at'],
            ]);

            $created++;
            $this->command->info("   ✓ {$user['phone']} | {$role} | {$user['name']}");
        }

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('✅ DONE');
        $this->command->info("   Clinic: {$tenant->name}");
        $this->command->info("   Users created: {$created}");
        $this->command->info("   Users updated: {$updated}");
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
