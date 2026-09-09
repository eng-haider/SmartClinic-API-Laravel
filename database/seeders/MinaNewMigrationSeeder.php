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
                        'tooth_details' => $oldPatient->tooth_parts ? $this->convertToothParts($oldPatient->tooth_parts) : null,
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

    /**
     * Convert old tooth_parts format to new tooth_details format.
     * Old: [{"tooth_number":18,"tooth_id":"tooth-35","part_id":1,"color":"#FF5252"}]
     * New: [{"tooth_number":18,"tooth_id":"tooth-18","part_id":6,"color":"#FF5252"}]
     */
    private function convertToothParts(string $json): ?array
    {
        $parts = json_decode($json, true);
        if (!is_array($parts)) {
            return null;
        }

        $validFdi = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,
                     31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];

        $mapped = [];
        foreach ($parts as $p) {
            $oldToothId = $p['tooth_id'] ?? null;
            $toothNumber = (int)($p['tooth_number'] ?? 0);

            // Check if already in new format
            if ($oldToothId === "tooth-{$toothNumber}" && in_array($toothNumber, $validFdi)) {
                $mapped[] = $p;
                continue;
            }

            // Look up in mapping
            if ($oldToothId && isset(self::OLD_TO_NEW[$oldToothId])) {
                $m = self::OLD_TO_NEW[$oldToothId];
                $mapped[] = [
                    'tooth_number' => (int)str_replace('tooth-', '', $m['toothId']),
                    'tooth_id'     => $m['toothId'],
                    'part_id'      => $m['partId'],
                    'color'        => $p['color'] ?? '#FF5252',
                ];
            }
            // Entries not in mapping are skipped (outline/background paths)
        }

        return empty($mapped) ? null : $mapped;
    }

    private const OLD_TO_NEW = [
        'tooth-100' => ['toothId' => 'tooth-25', 'partId' => 2],
        'tooth-101' => ['toothId' => 'tooth-25', 'partId' => 3],
        'tooth-102' => ['toothId' => 'tooth-25', 'partId' => 4],
        'tooth-103' => ['toothId' => 'tooth-25', 'partId' => 1],
        'tooth-105' => ['toothId' => 'tooth-25', 'partId' => 6],
        'tooth-107' => ['toothId' => 'tooth-13', 'partId' => 2],
        'tooth-109' => ['toothId' => 'tooth-12', 'partId' => 2],
        'tooth-10'  => ['toothId' => 'tooth-14', 'partId' => 2],
        'tooth-111' => ['toothId' => 'tooth-22', 'partId' => 2],
        'tooth-110' => ['toothId' => 'tooth-22', 'partId' => 2],
        'tooth-112' => ['toothId' => 'tooth-13', 'partId' => 5],
        'tooth-113' => ['toothId' => 'tooth-12', 'partId' => 5],
        'tooth-114' => ['toothId' => 'tooth-11', 'partId' => 5],
        'tooth-115' => ['toothId' => 'tooth-22', 'partId' => 5],
        'tooth-116' => ['toothId' => 'tooth-23', 'partId' => 5],
        'tooth-117' => ['toothId' => 'tooth-14', 'partId' => 5],
        'tooth-118' => ['toothId' => 'tooth-24', 'partId' => 5],
        'tooth-119' => ['toothId' => 'tooth-25', 'partId' => 5],
        'tooth-120' => ['toothId' => 'tooth-18', 'partId' => 5],
        'tooth-122' => ['toothId' => 'tooth-16', 'partId' => 5],
        'tooth-123' => ['toothId' => 'tooth-27', 'partId' => 5],
        'tooth-124' => ['toothId' => 'tooth-28', 'partId' => 5],
        'tooth-126' => ['toothId' => 'tooth-15', 'partId' => 5],
        'tooth-127' => ['toothId' => 'tooth-15', 'partId' => 2],
        'tooth-128' => ['toothId' => 'tooth-15', 'partId' => 3],
        'tooth-129' => ['toothId' => 'tooth-15', 'partId' => 4],
        'tooth-131' => ['toothId' => 'tooth-15', 'partId' => 1],
        'tooth-132' => ['toothId' => 'tooth-15', 'partId' => 6],
        'tooth-134' => ['toothId' => 'tooth-26', 'partId' => 5],
        'tooth-136' => ['toothId' => 'tooth-21', 'partId' => 5],
        'tooth-137' => ['toothId' => 'tooth-21', 'partId' => 2],
        'tooth-138' => ['toothId' => 'tooth-21', 'partId' => 2],
        'tooth-139' => ['toothId' => 'tooth-21', 'partId' => 3],
        'tooth-13'  => ['toothId' => 'tooth-12', 'partId' => 6],
        'tooth-140' => ['toothId' => 'tooth-21', 'partId' => 4],
        'tooth-142' => ['toothId' => 'tooth-21', 'partId' => 1],
        'tooth-143' => ['toothId' => 'tooth-21', 'partId' => 6],
        'tooth-145' => ['toothId' => 'tooth-48', 'partId' => 5],
        'tooth-146' => ['toothId' => 'tooth-48', 'partId' => 2],
        'tooth-147' => ['toothId' => 'tooth-48', 'partId' => 3],
        'tooth-148' => ['toothId' => 'tooth-48', 'partId' => 4],
        'tooth-149' => ['toothId' => 'tooth-48', 'partId' => 1],
        'tooth-151' => ['toothId' => 'tooth-47', 'partId' => 5],
        'tooth-152' => ['toothId' => 'tooth-47', 'partId' => 2],
        'tooth-153' => ['toothId' => 'tooth-47', 'partId' => 3],
        'tooth-154' => ['toothId' => 'tooth-47', 'partId' => 4],
        'tooth-155' => ['toothId' => 'tooth-47', 'partId' => 1],
        'tooth-156' => ['toothId' => 'tooth-46', 'partId' => 5],
        'tooth-157' => ['toothId' => 'tooth-46', 'partId' => 5],
        'tooth-158' => ['toothId' => 'tooth-46', 'partId' => 2],
        'tooth-159' => ['toothId' => 'tooth-46', 'partId' => 3],
        'tooth-15'  => ['toothId' => 'tooth-11', 'partId' => 6],
        'tooth-160' => ['toothId' => 'tooth-46', 'partId' => 4],
        'tooth-161' => ['toothId' => 'tooth-36', 'partId' => 1],
        'tooth-162' => ['toothId' => 'tooth-36', 'partId' => 5],
        'tooth-163' => ['toothId' => 'tooth-36', 'partId' => 2],
        'tooth-164' => ['toothId' => 'tooth-36', 'partId' => 3],
        'tooth-165' => ['toothId' => 'tooth-36', 'partId' => 4],
        'tooth-166' => ['toothId' => 'tooth-36', 'partId' => 1],
        'tooth-168' => ['toothId' => 'tooth-37', 'partId' => 5],
        'tooth-169' => ['toothId' => 'tooth-37', 'partId' => 2],
        'tooth-170' => ['toothId' => 'tooth-37', 'partId' => 3],
        'tooth-171' => ['toothId' => 'tooth-37', 'partId' => 4],
        'tooth-172' => ['toothId' => 'tooth-37', 'partId' => 1],
        'tooth-174' => ['toothId' => 'tooth-38', 'partId' => 5],
        'tooth-175' => ['toothId' => 'tooth-38', 'partId' => 2],
        'tooth-176' => ['toothId' => 'tooth-38', 'partId' => 3],
        'tooth-177' => ['toothId' => 'tooth-38', 'partId' => 4],
        'tooth-178' => ['toothId' => 'tooth-38', 'partId' => 1],
        'tooth-179' => ['toothId' => 'tooth-47', 'partId' => 6],
        'tooth-17'  => ['toothId' => 'tooth-22', 'partId' => 6],
        'tooth-180' => ['toothId' => 'tooth-46', 'partId' => 6],
        'tooth-181' => ['toothId' => 'tooth-36', 'partId' => 6],
        'tooth-182' => ['toothId' => 'tooth-37', 'partId' => 1],
        'tooth-184' => ['toothId' => 'tooth-48', 'partId' => 6],
        'tooth-185' => ['toothId' => 'tooth-38', 'partId' => 6],
        'tooth-189' => ['toothId' => 'tooth-43', 'partId' => 2],
        'tooth-190' => ['toothId' => 'tooth-43', 'partId' => 3],
        'tooth-191' => ['toothId' => 'tooth-43', 'partId' => 4],
        'tooth-192' => ['toothId' => 'tooth-43', 'partId' => 1],
        'tooth-194' => ['toothId' => 'tooth-33', 'partId' => 5],
        'tooth-196' => ['toothId' => 'tooth-33', 'partId' => 2],
        'tooth-197' => ['toothId' => 'tooth-33', 'partId' => 3],
        'tooth-198' => ['toothId' => 'tooth-33', 'partId' => 4],
        'tooth-199' => ['toothId' => 'tooth-33', 'partId' => 1],
        'tooth-202' => ['toothId' => 'tooth-34', 'partId' => 5],
        'tooth-203' => ['toothId' => 'tooth-34', 'partId' => 2],
        'tooth-204' => ['toothId' => 'tooth-34', 'partId' => 3],
        'tooth-205' => ['toothId' => 'tooth-34', 'partId' => 4],
        'tooth-206' => ['toothId' => 'tooth-34', 'partId' => 1],
        'tooth-208' => ['toothId' => 'tooth-35', 'partId' => 5],
        'tooth-209' => ['toothId' => 'tooth-35', 'partId' => 2],
        'tooth-210' => ['toothId' => 'tooth-35', 'partId' => 3],
        'tooth-211' => ['toothId' => 'tooth-35', 'partId' => 4],
        'tooth-212' => ['toothId' => 'tooth-35', 'partId' => 1],
        'tooth-216' => ['toothId' => 'tooth-42', 'partId' => 5],
        'tooth-218' => ['toothId' => 'tooth-42', 'partId' => 2],
        'tooth-219' => ['toothId' => 'tooth-42', 'partId' => 3],
        'tooth-21'  => ['toothId' => 'tooth-17', 'partId' => 7],
        'tooth-220' => ['toothId' => 'tooth-42', 'partId' => 4],
        'tooth-221' => ['toothId' => 'tooth-42', 'partId' => 1],
        'tooth-223' => ['toothId' => 'tooth-41', 'partId' => 5],
        'tooth-225' => ['toothId' => 'tooth-41', 'partId' => 2],
        'tooth-226' => ['toothId' => 'tooth-41', 'partId' => 3],
        'tooth-227' => ['toothId' => 'tooth-41', 'partId' => 4],
        'tooth-228' => ['toothId' => 'tooth-41', 'partId' => 1],
        'tooth-229' => ['toothId' => 'tooth-31', 'partId' => 1],
        'tooth-22'  => ['toothId' => 'tooth-27', 'partId' => 7],
        'tooth-230' => ['toothId' => 'tooth-31', 'partId' => 5],
        'tooth-232' => ['toothId' => 'tooth-31', 'partId' => 2],
        'tooth-233' => ['toothId' => 'tooth-31', 'partId' => 3],
        'tooth-234' => ['toothId' => 'tooth-31', 'partId' => 4],
        'tooth-235' => ['toothId' => 'tooth-31', 'partId' => 1],
        'tooth-237' => ['toothId' => 'tooth-32', 'partId' => 5],
        'tooth-239' => ['toothId' => 'tooth-32', 'partId' => 2],
        'tooth-240' => ['toothId' => 'tooth-32', 'partId' => 3],
        'tooth-241' => ['toothId' => 'tooth-32', 'partId' => 4],
        'tooth-242' => ['toothId' => 'tooth-32', 'partId' => 1],
        'tooth-243' => ['toothId' => 'tooth-43', 'partId' => 6],
        'tooth-244' => ['toothId' => 'tooth-33', 'partId' => 6],
        'tooth-247' => ['toothId' => 'tooth-45', 'partId' => 5],
        'tooth-248' => ['toothId' => 'tooth-45', 'partId' => 2],
        'tooth-249' => ['toothId' => 'tooth-45', 'partId' => 3],
        'tooth-250' => ['toothId' => 'tooth-45', 'partId' => 4],
        'tooth-251' => ['toothId' => 'tooth-45', 'partId' => 1],
        'tooth-253' => ['toothId' => 'tooth-44', 'partId' => 5],
        'tooth-254' => ['toothId' => 'tooth-44', 'partId' => 2],
        'tooth-255' => ['toothId' => 'tooth-44', 'partId' => 3],
        'tooth-256' => ['toothId' => 'tooth-44', 'partId' => 4],
        'tooth-257' => ['toothId' => 'tooth-44', 'partId' => 1],
        'tooth-259' => ['toothId' => 'tooth-46', 'partId' => 1],
        'tooth-25'  => ['toothId' => 'tooth-16', 'partId' => 1],
        'tooth-260' => ['toothId' => 'tooth-42', 'partId' => 6],
        'tooth-261' => ['toothId' => 'tooth-32', 'partId' => 6],
        'tooth-262' => ['toothId' => 'tooth-45', 'partId' => 6],
        'tooth-264' => ['toothId' => 'tooth-35', 'partId' => 6],
        'tooth-266' => ['toothId' => 'tooth-41', 'partId' => 6],
        'tooth-267' => ['toothId' => 'tooth-31', 'partId' => 6],
        'tooth-269' => ['toothId' => 'tooth-44', 'partId' => 6],
        'tooth-26'  => ['toothId' => 'tooth-26', 'partId' => 1],
        'tooth-270' => ['toothId' => 'tooth-34', 'partId' => 6],
        'tooth-27'  => ['toothId' => 'tooth-17', 'partId' => 7],
        'tooth-28'  => ['toothId' => 'tooth-17', 'partId' => 6],
        'tooth-29'  => ['toothId' => 'tooth-27', 'partId' => 6],
        'tooth-2'   => ['toothId' => 'tooth-14', 'partId' => 6],
        'tooth-31'  => ['toothId' => 'tooth-18', 'partId' => 7],
        'tooth-32'  => ['toothId' => 'tooth-28', 'partId' => 7],
        'tooth-33'  => ['toothId' => 'tooth-16', 'partId' => 1],
        'tooth-34'  => ['toothId' => 'tooth-26', 'partId' => 7],
        'tooth-35'  => ['toothId' => 'tooth-18', 'partId' => 6],
        'tooth-36'  => ['toothId' => 'tooth-28', 'partId' => 6],
        'tooth-37'  => ['toothId' => 'tooth-18', 'partId' => 7],
        'tooth-38'  => ['toothId' => 'tooth-28', 'partId' => 7],
        'tooth-47'  => ['toothId' => 'tooth-16', 'partId' => 7],
        'tooth-48'  => ['toothId' => 'tooth-18', 'partId' => 4],
        'tooth-49'  => ['toothId' => 'tooth-17', 'partId' => 4],
        'tooth-50'  => ['toothId' => 'tooth-16', 'partId' => 1],
        'tooth-51'  => ['toothId' => 'tooth-14', 'partId' => 1],
        'tooth-52'  => ['toothId' => 'tooth-24', 'partId' => 1],
        'tooth-53'  => ['toothId' => 'tooth-26', 'partId' => 4],
        'tooth-54'  => ['toothId' => 'tooth-27', 'partId' => 4],
        'tooth-55'  => ['toothId' => 'tooth-28', 'partId' => 4],
        'tooth-56'  => ['toothId' => 'tooth-13', 'partId' => 1],
        'tooth-57'  => ['toothId' => 'tooth-12', 'partId' => 1],
        'tooth-58'  => ['toothId' => 'tooth-11', 'partId' => 1],
        'tooth-59'  => ['toothId' => 'tooth-22', 'partId' => 1],
        'tooth-60'  => ['toothId' => 'tooth-23', 'partId' => 1],
        'tooth-61'  => ['toothId' => 'tooth-14', 'partId' => 4],
        'tooth-62'  => ['toothId' => 'tooth-14', 'partId' => 3],
        'tooth-63'  => ['toothId' => 'tooth-24', 'partId' => 4],
        'tooth-64'  => ['toothId' => 'tooth-24', 'partId' => 3],
        'tooth-65'  => ['toothId' => 'tooth-18', 'partId' => 1],
        'tooth-66'  => ['toothId' => 'tooth-18', 'partId' => 3],
        'tooth-67'  => ['toothId' => 'tooth-17', 'partId' => 1],
        'tooth-68'  => ['toothId' => 'tooth-17', 'partId' => 3],
        'tooth-69'  => ['toothId' => 'tooth-16', 'partId' => 4],
        'tooth-6'   => ['toothId' => 'tooth-24', 'partId' => 6],
        'tooth-70'  => ['toothId' => 'tooth-16', 'partId' => 3],
        'tooth-71'  => ['toothId' => 'tooth-26', 'partId' => 4],
        'tooth-72'  => ['toothId' => 'tooth-26', 'partId' => 3],
        'tooth-73'  => ['toothId' => 'tooth-27', 'partId' => 1],
        'tooth-74'  => ['toothId' => 'tooth-27', 'partId' => 3],
        'tooth-75'  => ['toothId' => 'tooth-28', 'partId' => 1],
        'tooth-76'  => ['toothId' => 'tooth-28', 'partId' => 3],
        'tooth-78'  => ['toothId' => 'tooth-18', 'partId' => 2],
        'tooth-79'  => ['toothId' => 'tooth-17', 'partId' => 2],
        'tooth-80'  => ['toothId' => 'tooth-16', 'partId' => 2],
        'tooth-81'  => ['toothId' => 'tooth-26', 'partId' => 2],
        'tooth-82'  => ['toothId' => 'tooth-27', 'partId' => 2],
        'tooth-83'  => ['toothId' => 'tooth-28', 'partId' => 2],
        'tooth-84'  => ['toothId' => 'tooth-24', 'partId' => 2],
        'tooth-86'  => ['toothId' => 'tooth-13', 'partId' => 4],
        'tooth-87'  => ['toothId' => 'tooth-13', 'partId' => 3],
        'tooth-88'  => ['toothId' => 'tooth-12', 'partId' => 4],
        'tooth-89'  => ['toothId' => 'tooth-12', 'partId' => 3],
        'tooth-8'   => ['toothId' => 'tooth-13', 'partId' => 6],
        'tooth-90'  => ['toothId' => 'tooth-11', 'partId' => 4],
        'tooth-91'  => ['toothId' => 'tooth-11', 'partId' => 3],
        'tooth-92'  => ['toothId' => 'tooth-22', 'partId' => 4],
        'tooth-93'  => ['toothId' => 'tooth-22', 'partId' => 3],
        'tooth-94'  => ['toothId' => 'tooth-23', 'partId' => 4],
        'tooth-95'  => ['toothId' => 'tooth-23', 'partId' => 3],
        'tooth-96'  => ['toothId' => 'tooth-11', 'partId' => 2],
        'tooth-97'  => ['toothId' => 'tooth-23', 'partId' => 2],
        'tooth-9'   => ['toothId' => 'tooth-23', 'partId' => 6],
    ];
}
