<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Patient;
use App\Models\CaseModel;
use App\Models\CaseCategory;
use App\Models\Status;
use Spatie\Permission\Models\Role;

class MinaNewMigrationSeeder extends Seeder
{
    /**
     * ============================================================
     * CONFIGURATION
     * ============================================================
     *
     * Old database: min_new
     * New tenant database: mina_last  (connects directly, bypasses tenancy prefix)
     *
     * Migration scope: users, doctors→users, patients, cases, case_categories
     *
     * Role mapping:
     *   - Doctor ID 1 → clinic_super_doctor
     *   - All other doctors → doctor
     *   - Old role_id 3/6 users → secretary
     *
     * Cases have doctor_id referencing old doctors.id (NOT users.id)
     * Many doctors share user_id=1, each becomes a separate user in new DB
     * Doctors without user rows → new users created from doctor name
     */

    private string $tenantId  = 'mina_last';
    private string $newDbName = 'mina_last';   // actual MySQL DB to write into
    private string $oldDbName = 'min_new';
    private string $connName  = 'mina_direct'; // temp connection name

    /** ID mappings: old_id => new_id */
    private array $doctorIdMap       = [];
    private array $patientIdMap      = [];
    private array $caseCategoryIdMap = [];
    private array $caseIdMap         = [];
    /** old case id => new patient id (for bills) */
    private array $casePatientMap    = [];
    private int   $billCount         = 0;

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔄 MINA NEW MIGRATION SEEDER');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("   Old DB : {$this->oldDbName}");
        $this->command->info("   New DB : {$this->newDbName}");
        $this->command->info('');

        // ── 1. Connect to old DB via raw PDO ─────────────────────────────────
        try {
            $oldPdo = new \PDO(
                "mysql:host=127.0.0.1;dbname={$this->oldDbName};charset=utf8mb4",
                config('database.connections.mysql.username', 'root'),
                config('database.connections.mysql.password', '')
            );
            $oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->command->info("   ✓ Connected to old database: {$this->oldDbName}");
        } catch (\Exception $e) {
            $this->command->error("❌ Old DB connection failed: " . $e->getMessage());
            return;
        }

        // ── 2. Register a direct connection to mina_last (no tenancy prefix) ─
        $baseConfig = config('database.connections.mysql');
        $baseConfig['database'] = $this->newDbName;
        config(["database.connections.{$this->connName}" => $baseConfig]);

        // Ensure mina_last DB exists
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$this->newDbName}`");

        // ── 3. Clinic info (min_new has no clinics table) ────────────────────
        $oldClinic = (object)[
            'id'   => 1, 'name' => 'عيادة المينا', 'address' => null, 'rx_img' => null,
            'whatsapp_template_sid' => null, 'whatsapp_message_count' => 20,
            'whatsapp_phone' => null, 'show_image_case' => 0,
        ];
        $this->command->info("📋 Clinic: {$oldClinic->name}");

        // ── 4. Create / recreate the tenant record in central DB ──────────────
        $existing = Tenant::find($this->tenantId);
        if ($existing) {
            $this->command->warn("⚠ Tenant '{$this->tenantId}' already exists – deleting and recreating...");
            $existing->delete();
        }
        $this->command->info("🏥 Creating tenant record: {$this->tenantId}");
        Tenant::create([
            'id'                    => $this->tenantId,
            'name'                  => $oldClinic->name,
            'address'               => $oldClinic->address              ?? null,
            'rx_img'                => $oldClinic->rx_img               ?? null,
            'whatsapp_template_sid' => $oldClinic->whatsapp_template_sid ?? null,
            'whatsapp_message_count'=> $oldClinic->whatsapp_message_count ?? 20,
            'whatsapp_phone'        => $oldClinic->whatsapp_phone        ?? null,
            'show_image_case'       => $oldClinic->show_image_case       ?? 0,
            'db_name'               => $this->newDbName,
            'db_username'           => config('database.connections.mysql.username'),
            'db_password'           => config('database.connections.mysql.password'),
        ]);

        // ── 5. Switch default connection to mina_last and run migrations ──────
        $this->command->info("📦 Running migrations on {$this->newDbName} ...");
        DB::setDefaultConnection($this->connName);

        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--database' => $this->connName,
            '--force'    => true,
        ]);

        // ── 6. Clear existing data ────────────────────────────────────────────
        $this->command->info("🧹 Clearing existing data...");
        DB::connection($this->connName)->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['bills','cases','patients','users','statuses','case_categories',
                  'model_has_roles','model_has_permissions','role_has_permissions',
                  'roles','permissions'] as $table) {
            DB::connection($this->connName)->table($table)->truncate();
        }
        DB::connection($this->connName)->statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info("   ✓ All tables cleared");

        // ── 7. Seed roles/permissions/statuses/base categories ────────────────
        // Default connection is now mina_direct (→ mina_last), so Spatie and
        // all Eloquent models will write directly into mina_last.
        $this->seedRolesAndPermissions();
        $this->seedStatuses();

        // ── 8. Migrate data ───────────────────────────────────────────────────
        $this->migrateCaseCategories($oldPdo);
        $this->migrateDoctorsAsUsers($oldPdo);
        $this->migrateSecretaryUsers($oldPdo);
        $this->migratePatients($oldPdo);
        $this->migrateCases($oldPdo);
        $this->migrateBills($oldPdo);

        // ── 9. Restore default connection ─────────────────────────────────────
        DB::setDefaultConnection('mysql');

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('✅ MIGRATION COMPLETED SUCCESSFULLY!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("   Tenant ID : {$this->tenantId}");
        $this->command->info("   Database  : {$this->newDbName}");
        $this->command->info("   Clinic    : {$oldClinic->name}");
        $this->command->info("   Doctors→Users    : " . count($this->doctorIdMap));
        $this->command->info("   Patients         : " . count($this->patientIdMap));
        $this->command->info("   Cases            : " . count($this->caseIdMap));
        $this->command->info("   Bills            : " . $this->billCount);
        $this->command->info("   Case categories  : " . count($this->caseCategoryIdMap));
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /** Seed Spatie roles and permissions directly into mina_last */
    private function seedRolesAndPermissions(): void
    {
        $this->command->info('🔑 Seeding roles and permissions...');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // config('rolesAndPermissions.roles') is keyed by role name:
        //   'clinic_super_doctor' => ['display_name' => ..., 'permissions' => [...]]
        $rolesConfig = config('rolesAndPermissions.roles', []);

        $allPermissions = [];
        foreach ($rolesConfig as $roleDef) {
            $allPermissions = array_merge($allPermissions, $roleDef['permissions'] ?? []);
        }
        foreach (array_unique($allPermissions) as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        foreach ($rolesConfig as $roleName => $roleDef) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if (!empty($roleDef['permissions'])) {
                $role->syncPermissions($roleDef['permissions']);
            }
        }
        $this->command->info("   ✓ Roles and permissions seeded");
    }

    /** Seed default statuses into mina_last */
    private function seedStatuses(): void
    {
        $this->command->info('📌 Seeding statuses...');
        $statuses = [
            ['name_ar' => 'قيد الانتظار', 'name_en' => 'Pending',     'color' => '#FFA500', 'order' => 1],
            ['name_ar' => 'قيد التنفيذ',  'name_en' => 'In Progress',  'color' => '#2196F3', 'order' => 2],
            ['name_ar' => 'مكتمل',         'name_en' => 'Completed',    'color' => '#4CAF50', 'order' => 3],
            ['name_ar' => 'ملغي',          'name_en' => 'Cancelled',    'color' => '#F44336', 'order' => 4],
        ];
        foreach ($statuses as $s) {
            Status::firstOrCreate(['name_ar' => $s['name_ar']], $s);
        }
        $this->command->info("   ✓ " . count($statuses) . " statuses seeded");
    }

    /**
     * Migrate case categories from old DB.
     * Old: case_categories (id, name_ar, name_en, order, clinic_id, item_cost)
     * New: case_categories (id, name, order, item_cost)
     */
    private function migrateCaseCategories(\PDO $oldPdo): void
    {
        $this->command->info('📁 Migrating case categories...');

        $stmt = $oldPdo->query('SELECT * FROM case_categories');
        $oldCategories = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($oldCategories as $oldCategory) {
            $newCategory = CaseCategory::create([
                'name' => $oldCategory->name_ar ?? $oldCategory->name_en ?? 'Unknown',
                'order' => $oldCategory->order ?? 0,
                'item_cost' => $oldCategory->item_cost ?? 0,
            ]);

            // Preserve original timestamps
            $newCategory->created_at = $oldCategory->created_at;
            $newCategory->updated_at = $oldCategory->updated_at;
            $newCategory->saveQuietly();

            $this->caseCategoryIdMap[$oldCategory->id] = $newCategory->id;
            $this->command->info("   ✓ Category: {$newCategory->name} (old:{$oldCategory->id} → new:{$newCategory->id})");
        }
    }

    /**
     * Migrate doctors as users.
     * 
     * In the old DB, cases reference doctors.id (NOT users.id).
     * Many doctors share user_id=1 but are different people.
     * Each doctor becomes a unique user in the new system.
     * 
     * Role mapping:
     *   - Old doctor with user_id=1 AND is the FIRST doctor (id=1) → clinic_super_doctor
     *   - All other doctors → doctor
     */
    private function migrateDoctorsAsUsers(\PDO $oldPdo): void
    {
        $this->command->info('👥 Migrating doctors as users...');

        $stmt = $oldPdo->query('
            SELECT d.id as doctor_id, d.name as doctor_name, d.user_id, d.clinics_id,
                   u.id as uid, u.name as uname, u.phone, u.password, u.role_id, u.email
            FROM doctors d 
            LEFT JOIN users u ON d.user_id = u.id
            ORDER BY d.id
        ');
        $doctors = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $usedPhones = []; // Track used phones to avoid duplicates

        foreach ($doctors as $doctor) {
            $name     = $doctor->doctor_name;
            $phone    = null;
            $password = Hash::make('12345678');

            // If doctor has a matching user row, use phone and password from old user.
            // Email is intentionally NOT taken from old DB — many doctors share the same
            // user_id (user_id=1) and that user may already have a non-null email,
            // causing duplicate-key violations.
            if ($doctor->uid !== null) {
                $phone    = $doctor->phone;
                $password = $doctor->password ?? Hash::make('12345678');
            }

            // Unique phone: fall back to doctor_N if null or already used
            if (empty($phone) || isset($usedPhones[$phone])) {
                $phone = 'doctor_' . $doctor->doctor_id;
            }
            $usedPhones[$phone] = true;

            // Always generate a unique, deterministic email per doctor
            $email = 'doctor_' . $doctor->doctor_id . '@noemail.local';

            // Determine role
            $role = ($doctor->doctor_id == 1) ? 'clinic_super_doctor' : 'doctor';

            // Insert via raw query builder (no Eloquent events)
            $userId = DB::table('users')->insertGetId([
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'password'   => $password,
                'is_active'  => true,
                'created_at' => $doctor->created_at ?? now(),
                'updated_at' => $doctor->updated_at ?? now(),
            ]);

            // Assign role without firing model events
            User::withoutEvents(function () use ($userId, $role) {
                $newUser = User::find($userId);
                $newUser->assignRole(Role::findByName($role, 'web'));
            });

            $this->doctorIdMap[$doctor->doctor_id] = $userId;
            $this->command->info("   ✓ Doctor→User: {$name} | Role: {$role} | Phone: {$phone} (doctor_id:{$doctor->doctor_id} → user_id:{$userId})");
        }
    }

    /**
     * Migrate secretary users from old DB.
     * Old users with role_id=6 or role_id=3 → secretary role
     * Skip user_id=1 (already migrated as clinic_super_doctor via doctor)
     */
    private function migrateSecretaryUsers(\PDO $oldPdo): void
    {
        $this->command->info('👥 Migrating secretary users...');

        $stmt = $oldPdo->query("SELECT * FROM users WHERE role_id IN (3, 6) ORDER BY id");
        $secretaries = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($secretaries as $secretary) {
            // Check if phone already used
            $existingUser = User::where('phone', $secretary->phone)->first();
            if ($existingUser) {
                $this->command->warn("   ⚠ Secretary with phone {$secretary->phone} already exists as user {$existingUser->id}, skipping");
                continue;
            }

            $secEmail = 'secretary_' . $secretary->id . '@noemail.local';

            $userId = DB::table('users')->insertGetId([
                'name'       => $secretary->name,
                'email'      => $secEmail,
                'phone'      => $secretary->phone ?? 'secretary_' . $secretary->id,
                'password'   => $secretary->password ?? Hash::make('12345678'),
                'is_active'  => true,
                'created_at' => $secretary->created_at,
                'updated_at' => $secretary->updated_at,
            ]);

            User::withoutEvents(function () use ($userId) {
                $newUser = User::find($userId);
                $newUser->assignRole(Role::findByName('secretary', 'web'));
            });
            $this->command->info("   ✓ Secretary: {$secretary->name} | Phone: {$secretary->phone} (old:{$secretary->id} → new:{$userId})");
        }
    }

    /**
     * Migrate patients from old DB.
     * 
     * Old patients table has doctor_id (nullable), clinics_id, user_id etc.
     * New patients table: doctor_id references users table.
     * Map old patient.doctor_id (which is doctors.id in old DB, nullable) → new user_id.
     * 
     * Note: Many old patients have doctor_id=null, we still migrate them.
     */
    private function migratePatients(\PDO $oldPdo): void
    {
        $this->command->info('🏥 Migrating patients...');

        $stmt = $oldPdo->query('SELECT * FROM patients ORDER BY id');
        $oldPatients = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $this->command->info("   Found " . count($oldPatients) . " patients to migrate");

        $usedPhones = [];
        $count = 0;
        $errors = 0;

        foreach ($oldPatients as $oldPatient) {
            // Map old doctor_id (doctors.id) → new user_id
            $newDoctorId = null;
            if ($oldPatient->doctor_id) {
                $newDoctorId = $this->doctorIdMap[$oldPatient->doctor_id] ?? null;
            }
            
            // If no doctor_id on patient, try to find from cases
            if (!$newDoctorId) {
                // Use first doctor as fallback
                $newDoctorId = $this->doctorIdMap[1] ?? User::first()?->id;
            }

            // Handle phone duplicates
            $phone = $oldPatient->phone;
            if (!empty($phone) && isset($usedPhones[$phone])) {
                $phone = $phone . '_' . $oldPatient->id;
            }
            if (!empty($phone)) {
                $usedPhones[$phone] = true;
            }

            try {
                $newPatient = Patient::withoutEvents(function () use ($oldPatient, $newDoctorId, $phone) {
                    return Patient::create([
                        'name' => $oldPatient->name,
                        'age' => $oldPatient->age,
                        'doctor_id' => $newDoctorId,
                        'phone' => $phone,
                        'systemic_conditions' => $oldPatient->systemic_conditions,
                        'sex' => $oldPatient->sex,
                        'address' => $oldPatient->address,
                        'notes' => $oldPatient->notes,
                        'birth_date' => $oldPatient->birth_date,
                        'rx_id' => $oldPatient->rx_id ?? null,
                        'note' => $oldPatient->note ?? null,
                        'identifier' => $oldPatient->identifier ?? null,
                        'credit_balance' => $oldPatient->credit_balance ?? null,
                        'credit_balance_add_at' => $oldPatient->credit_balance_add_at ?? null,
                        'tooth_details' => $oldPatient->tooth_parts ?? null,
                        'public_token' => Str::uuid()->toString(),
                    ]);
                });

                // Preserve original timestamps and soft delete
                $newPatient->created_at = $oldPatient->created_at;
                $newPatient->updated_at = $oldPatient->updated_at;
                if ($oldPatient->deleted_at) {
                    $newPatient->deleted_at = $oldPatient->deleted_at;
                }
                $newPatient->saveQuietly();

                $this->patientIdMap[$oldPatient->id] = $newPatient->id;
                $count++;

                if ($count % 100 === 0) {
                    $this->command->info("   ... migrated {$count} patients");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->command->error("   ✗ Patient: {$oldPatient->name} (id:{$oldPatient->id}) - Error: " . $e->getMessage());
            }
        }

        $this->command->info("   ✓ Migrated {$count} patients ({$errors} errors)");
    }

    /**
     * Migrate cases from old DB.
     * 
     * Old cases have:
     *   - doctor_id → references old doctors.id (NOT users.id)
     *   - patient_id → references old patients.id
     *   - case_categores_id → references old case_categories.id
     *   - status_id → only value 1 in this DB (maps to "In Progress" or first status)
     * 
     * New cases:
     *   - doctor_id → references new users.id (mapped via doctorIdMap)
     *   - patient_id → mapped via patientIdMap
     *   - case_categores_id → mapped via caseCategoryIdMap
     *   - status_id → mapped to new status
     */
    private function migrateCases(\PDO $oldPdo): void
    {
        $this->command->info('📋 Migrating cases...');

        // Get total count
        $totalStmt = $oldPdo->query('SELECT COUNT(*) FROM cases');
        $totalCases = $totalStmt->fetchColumn();
        $this->command->info("   Found {$totalCases} cases to migrate");

        // Process in batches of 1000
        $batchSize = 1000;
        $offset = 0;
        $count = 0;
        $errors = 0;
        $skipped = 0;

        // Map old status_id=1 → new status "In Progress" (id=2 from TenantDatabaseSeeder)
        // Old DB only has status_id=1 for cases
        $defaultStatusId = Status::first()?->id ?? 1;

        while ($offset < $totalCases) {
            $stmt = $oldPdo->prepare("SELECT * FROM cases ORDER BY id LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $oldCases = $stmt->fetchAll(\PDO::FETCH_OBJ);

            if (empty($oldCases)) {
                break;
            }

            foreach ($oldCases as $oldCase) {
                // Map patient_id
                $newPatientId = $this->patientIdMap[$oldCase->patient_id] ?? null;
                if (!$newPatientId) {
                    $skipped++;
                    continue;
                }

                // Map doctor_id (old doctors.id → new users.id)
                $newDoctorId = $this->doctorIdMap[$oldCase->doctor_id] ?? null;
                if (!$newDoctorId) {
                    // Fallback to first user
                    $newDoctorId = User::first()?->id;
                }

                // Map case category
                $newCaseCategoryId = $this->caseCategoryIdMap[$oldCase->case_categores_id] ?? null;
                if (!$newCaseCategoryId) {
                    $newCaseCategoryId = CaseCategory::first()?->id;
                    if (!$newCaseCategoryId) {
                        $skipped++;
                        continue;
                    }
                }

                try {
                    $newCase = CaseModel::create([
                        'patient_id' => $newPatientId,
                        'doctor_id' => $newDoctorId,
                        'case_categores_id' => $newCaseCategoryId,
                        'notes' => $oldCase->notes,
                        'status_id' => $defaultStatusId,
                        'price' => $oldCase->price,
                        'tooth_num' => $oldCase->tooth_num,
                        'root_stuffing' => $oldCase->root_stuffing,
                        'is_paid' => $oldCase->is_paid ?? false,
                    ]);

                    // Preserve original timestamps and soft delete
                    $newCase->created_at = $oldCase->created_at;
                    $newCase->updated_at = $oldCase->updated_at;
                    if ($oldCase->deleted_at) {
                        $newCase->deleted_at = $oldCase->deleted_at;
                    }
                    $newCase->saveQuietly();

                    $this->caseIdMap[$oldCase->id] = $newCase->id;
                    $this->casePatientMap[$oldCase->id] = $newPatientId;
                    $count++;

                    if ($count % 1000 === 0) {
                        $this->command->info("   ... migrated {$count} cases");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    if ($errors <= 10) {
                        $this->command->error("   ✗ Case {$oldCase->id}: " . $e->getMessage());
                    }
                }
            }

            $offset += $batchSize;
        }

        $this->command->info("   ✓ Migrated {$count} cases ({$skipped} skipped, {$errors} errors)");
    }

    /**
     * Migrate bills from old DB.
     *
     * Old bills:
     *   - billable_type = 'App\Models\Cases'  → maps to 'App\Models\CaseModel'
     *   - billable_id   = old cases.id         → mapped via caseIdMap
     *   - doctor_id     = old doctors.id        → mapped via doctorIdMap
     *   - patient_id    = always NULL in old DB
     *   - user_id       = old user who created  → use doctor mapping as creator_id
     */
    private function migrateBills(\PDO $oldPdo): void
    {
        $this->command->info('💰 Migrating bills...');

        $totalStmt = $oldPdo->query('SELECT COUNT(*) FROM bills');
        $totalBills = (int) $totalStmt->fetchColumn();
        $this->command->info("   Found {$totalBills} bills to migrate");

        $batchSize = 1000;
        $offset    = 0;
        $count     = 0;
        $skipped   = 0;
        $errors    = 0;

        // Billable type mapping: old class → new class
        $typeMap = [
            'App\\Models\\Cases' => 'App\\Models\\CaseModel',
            'App\Models\Cases'   => 'App\Models\CaseModel',
        ];

        while ($offset < $totalBills) {
            $stmt = $oldPdo->prepare('SELECT * FROM bills ORDER BY id LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit',  $batchSize, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,    \PDO::PARAM_INT);
            $stmt->execute();
            $oldBills = $stmt->fetchAll(\PDO::FETCH_OBJ);

            if (empty($oldBills)) {
                break;
            }

            $inserts = [];
            $now     = now();

            foreach ($oldBills as $oldBill) {
                // Map billable_type
                $newBillableType = $typeMap[$oldBill->billable_type]
                    ?? $oldBill->billable_type;

                // Map billable_id (old case id → new case id)
                $newBillableId = $this->caseIdMap[$oldBill->billable_id] ?? null;
                if (!$newBillableId) {
                    $skipped++;
                    continue;
                }

                // Map doctor_id (old doctors.id → new users.id)
                $newDoctorId = $this->doctorIdMap[$oldBill->doctor_id] ?? null;

                // Get patient_id from the case this bill belongs to
                $newPatientId = $this->casePatientMap[$oldBill->billable_id] ?? null;

                $inserts[] = [
                    'patient_id'    => $newPatientId,
                    'billable_type' => $newBillableType,
                    'billable_id'   => $newBillableId,
                    'is_paid'       => (bool) ($oldBill->is_paid ?? false),
                    'price'         => (int)  ($oldBill->price   ?? 0),
                    'doctor_id'     => $newDoctorId,
                    'creator_id'    => $newDoctorId,  // use same doctor as creator
                    'updator_id'    => null,
                    'use_credit'    => (bool) ($oldBill->use_credit ?? false),
                    'deleted_at'    => $oldBill->deleted_at ?: null,
                    'created_at'    => $oldBill->created_at ?: $now,
                    'updated_at'    => $oldBill->updated_at ?: $now,
                ];
            }

            if (!empty($inserts)) {
                try {
                    DB::connection($this->connName)->table('bills')->insert($inserts);
                    $count += count($inserts);

                    if ($count % 5000 === 0) {
                        $this->command->info("   ... migrated {$count} bills");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->command->error('   ✗ Bills batch error: ' . $e->getMessage());
                }
            }

            $offset += $batchSize;
        }

        $this->billCount = $count;
        $this->command->info("   ✓ Migrated {$count} bills ({$skipped} skipped, {$errors} errors)");
    }
}
