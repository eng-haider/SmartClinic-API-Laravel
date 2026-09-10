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
     * The tenant's MySQL database, used only when the tenants row does not exist
     * yet and has to be created. On Hostinger the database user always matches
     * the database name, and the password comes from TENANT_DB_PASSWORD in .env
     * (see DatabaseTenancyBootstrapper).
     */
    private string $tenantDbName = 'u876784197_tenant_27'; // <-- CHANGE THIS

    /**
     * Clinic display name, used only when creating the tenants row.
     */
    private string $clinicName = 'عياده تيتانيوم';

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

        // Create the tenants row when it is missing. Without it smartLogin cannot
        // find the tenant database at all, so this seeder is the single place that
        // registers the clinic instead of a hand-run tinker script.
        if (!$tenant) {
            $this->command->warn("⚠ Tenant '{$this->tenantId}' not found, creating it...");

            $dbPassword = env('TENANT_DB_PASSWORD');

            if (empty($dbPassword)) {
                $this->command->error('❌ TENANT_DB_PASSWORD is not set in .env');
                $this->command->error('   AuthService rejects tenants with an empty database password,');
                $this->command->error('   so add this line to .env and run again:');
                $this->command->error('');
                $this->command->error('   TENANT_DB_PASSWORD=your-tenant-db-password');
                return;
            }

            $tenant = new Tenant();
            $tenant->id = $this->tenantId;
            $tenant->name = $this->clinicName;
            $tenant->specialty = 'dental';
            $tenant->whatsapp_message_count = 0;
            $tenant->show_image_case = 0;
            $tenant->doctor_mony = 0;
            $tenant->teeth_v2 = 0;
            $tenant->send_msg = 0;
            $tenant->show_rx_id = 0;
            $tenant->api_whatsapp = 0;
            $tenant->has_ai_bot = 0;

            // On Hostinger the database user is the database name itself
            $tenant->setAttribute('db_name', $this->tenantDbName);
            $tenant->setAttribute('db_username', $this->tenantDbName);
            $tenant->setAttribute('db_password', $dbPassword);
            $tenant->save();

            $tenant = Tenant::find($this->tenantId);

            if (!$tenant) {
                $this->command->error('❌ Could not create the tenant record.');
                return;
            }

            $this->command->info("   ✓ Tenant created → database: {$tenant->db_name}");
        }

        $this->command->info("   Database: {$tenant->db_name}");

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

        $this->command->info('   Found ' . count($tenantUsers) . ' users in the tenant database');

        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($tenantUsers as $user) {
            $role = self::ROLE_MAP[$user['role_name'] ?? ''] ?? 'user';

            // users.email is unique centrally, and '' is not NULL - several blank
            // emails would collide with each other on the second insert.
            $email = ($user['email'] === '' || $user['email'] === null) ? null : $user['email'];

            $payload = [
                'name' => $user['name'],
                'email' => $email,
                'password' => $user['password'], // already hashed, copy as-is
                'clinic_id' => $this->tenantId,
                'role' => $role,
                'is_active' => (int) $user['is_active'],
                'updated_at' => now(),
            ];

            // One user must never abort the rest, so each write reports on its own.
            try {
                $existing = DB::table('users')->where('phone', $user['phone'])->first();

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
            } catch (\Throwable $e) {
                $failed++;
                $this->command->error("   ✗ {$user['phone']} ({$user['name']}) - " . $e->getMessage());
            }
        }

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('✅ DONE');
        $this->command->info("   Clinic: {$tenant->name}");
        $this->command->info("   Users created: {$created}");
        $this->command->info("   Users updated: {$updated}");

        if ($failed) {
            $this->command->error("   Users FAILED:  {$failed}");
        }
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
