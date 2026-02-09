<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Patient;
use App\Models\CaseModel;
use App\Models\CaseCategory;
use App\Models\Status;
use App\Models\Note;
use App\Models\Bill;
use App\Models\Image;
use App\Models\ClinicExpense;
use App\Models\ClinicExpenseCategory;
use Spatie\Permission\Models\Role;

class OldDatabaseMigrationSeeder extends Seeder
{
    /**
     * ============================================================
     * CONFIGURATION - Set the clinic ID you want to migrate
     * ============================================================
     * 
     * Set the old clinic ID to migrate data for.
     * Each clinic becomes a tenant in the new system.
     */
    private int $oldClinicId =1; // <-- CHANGE THIS to the clinic ID you want to migrate

    /**
     * The tenant ID to use (will be generated from clinic name)
     */
    private string $tenantId = '';

    /**
     * Old DB connection name (defined in config/database.php)
     */
    private string $oldDb = 'mysql_old';

    /**
     * ID mappings: old_id => new_id
     */
    private array $userIdMap = [];
    private array $patientIdMap = [];
    private array $caseCategoryIdMap = [];
    private array $statusIdMap = [];
    private array $caseIdMap = [];
    private array $expenseCategoryIdMap = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔄 OLD DATABASE MIGRATION SEEDER');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("   Migrating clinic ID: {$this->oldClinicId}");
        
        // Test old database connection
        try {
            $dbName = DB::connection($this->oldDb)->getDatabaseName();
            $this->command->info("   Old database: {$dbName}");
        } catch (\Exception $e) {
            $this->command->error("❌ Cannot connect to old database: " . $e->getMessage());
            return;
        }
        
        $this->command->info('');

        // 1. Get old clinic info
        $oldClinic = DB::connection($this->oldDb)->table('clinics')->where('id', $this->oldClinicId)->first();

        if (!$oldClinic) {
            $this->command->warn("⚠ Clinic with ID {$this->oldClinicId} not found in old database!");
            $this->command->info("   Creating default clinic information...");
            
            // Create a default clinic object
            $oldClinic = (object) [
                'id' => $this->oldClinicId,
                'name' => 'Clinic ' . $this->oldClinicId,
                'address' => null,
                'rx_img' => null,
                'whatsapp_template_sid' => null,
                'whatsapp_message_count' => 20,
                'whatsapp_phone' => null,
                'show_image_case' => 0,
            ];
        }

        $this->command->info("📋 Using clinic: {$oldClinic->name}");

        // 2. Generate tenant ID
        $this->tenantId = 'clinic_' . $this->oldClinicId;

        // 3. Check if tenant already exists
        $existingTenant = Tenant::find($this->tenantId);
        if ($existingTenant) {
            $this->command->warn("⚠ Tenant '{$this->tenantId}' already exists. Deleting and recreating...");
            $existingTenant->delete();
        }

        // 4. Create the tenant
        $this->command->info("🏥 Creating tenant: {$this->tenantId}");
        
        // Generate database name based on tenant ID
        $dbPrefix = config('tenancy.database.prefix', 'tenant');
        $dbName = $dbPrefix . str_replace('clinic_', '', $this->tenantId);
        
        $this->command->info("   Database: {$dbName}");
        $this->command->info("   Using credentials from .env (DB_USERNAME and DB_PASSWORD)");
        
        $tenant = Tenant::create([
            'id' => $this->tenantId,
            'name' => $oldClinic->name,
            'address' => $oldClinic->address,
            'rx_img' => $oldClinic->rx_img,
            'whatsapp_template_sid' => $oldClinic->whatsapp_template_sid ?? null,
            'whatsapp_message_count' => $oldClinic->whatsapp_message_count ?? 20,
            'whatsapp_phone' => $oldClinic->whatsapp_phone ?? null,
            'show_image_case' => $oldClinic->show_image_case ?? 0,
            // Database credentials - use same as central DB
            'db_name' => $dbName,
            'db_username' => config('database.connections.mysql.username'),
            'db_password' => config('database.connections.mysql.password'),
        ]);

        // 5. Run tenant-scoped migration
        $this->command->info("📦 Running tenant migrations...");

        $tenant->run(function () {
            // Run all tenant migrations first
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            
            // Clear ALL existing data first to avoid duplicates
            $this->command->info("🧹 Clearing existing data...");
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('clinic_expenses')->truncate();
            DB::table('clinic_expense_categories')->truncate();
            DB::table('images')->truncate();
            DB::table('bills')->truncate();
            DB::table('notes')->truncate();
            DB::table('cases')->truncate();
            DB::table('patients')->truncate();
            DB::table('users')->truncate();
            DB::table('statuses')->truncate();
            DB::table('case_categories')->truncate();
            DB::table('model_has_roles')->truncate();
            DB::table('model_has_permissions')->truncate();
            DB::table('role_has_permissions')->truncate();
            DB::table('roles')->truncate();
            DB::table('permissions')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->command->info("   ✓ All tables cleared");

            // Run tenant seeders for roles/permissions first
            $this->call(TenantDatabaseSeeder::class);

            // Now migrate the data
            $this->mapStatuses();
            $this->migrateCaseCategories();
            $this->migrateUsers();
            $this->migratePatients();
            $this->migrateCases();
            $this->migrateSessions();
            $this->migrateBills();
            $this->migrateImages();
            $this->migrateExpenseCategories();
            $this->migrateExpenses();
        });

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('✅ MIGRATION COMPLETED SUCCESSFULLY!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("   Tenant ID: {$this->tenantId}");
        $this->command->info("   Clinic: {$oldClinic->name}");
        $this->command->info("   Users migrated: " . count($this->userIdMap));
        $this->command->info("   Patients migrated: " . count($this->patientIdMap));
        $this->command->info("   Cases migrated: " . count($this->caseIdMap));
        $this->command->info("   Statuses migrated: " . count($this->statusIdMap));
        $this->command->info("   Case Categories migrated: " . count($this->caseCategoryIdMap));
        $this->command->info("   Expense Categories migrated: " . count($this->expenseCategoryIdMap));
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * Map old status IDs to new status IDs.
     * 
     * The TenantDatabaseSeeder already creates 4 default statuses:
     *   1 = قيد الانتظار (Pending)
     *   2 = قيد التنفيذ (In Progress)
     *   3 = مكتمل (Completed)
     *   4 = ملغي (Cancelled)
     * 
     * Old case statuses:
     *   42 = لم تنتهي (binding) → maps to 2 (In Progress)
     *   43 = مكتمله (completed) → maps to 3 (Completed)
     */
    private function mapStatuses(): void
    {
        $this->command->info('📌 Mapping statuses...');

        // Only map the statuses used by cases (42 and 43)
        $this->statusIdMap[42] = 2; // لم تنتهي → قيد التنفيذ (In Progress)
        $this->statusIdMap[43] = 3; // مكتمله → مكتمل (Completed)

        $this->command->info("   ✓ Old status 42 (لم تنتهي) → New status 2 (In Progress)");
        $this->command->info("   ✓ Old status 43 (مكتمله) → New status 3 (Completed)");
    }

    /**
     * Migrate case categories from old DB.
     * Old: case_categories (id, name_ar, name_en)
     * New: case_categories (id, name, order, item_cost)
     */
    private function migrateCaseCategories(): void
    {
        $this->command->info('📁 Migrating case categories...');

        $oldCategories = DB::connection($this->oldDb)->table('case_categories')->get();
        $order = 1;

        foreach ($oldCategories as $oldCategory) {
            $newCategory = CaseCategory::create([
                'name' => $oldCategory->name_ar ?? $oldCategory->name_en,
                'order' => 0,
                'item_cost' => 0,
            ]);

            // Preserve original timestamps
            $newCategory->created_at = $oldCategory->created_at;
            $newCategory->updated_at = $oldCategory->updated_at;
            $newCategory->saveQuietly();

            $this->caseCategoryIdMap[$oldCategory->id] = $newCategory->id;
            $this->command->info("   ✓ Case Category: {$newCategory->name} (old:{$oldCategory->id} → new:{$newCategory->id})");
        }
    }

    /**
     * Migrate users from old DB.
     * Old role_id mapping:
     *   - NULL  → Patient (DO NOT create as user, only as patient)
     *   - 5     → clinic_super_doctor
     *   - 4     → doctor
     *   - 3, 6  → secretary
     * 
     * Only migrate users who belong to this clinic's doctors or are linked to this clinic.
     */
    private function migrateUsers(): void
    {
        $this->command->info('👥 Migrating users...');

        // Get all doctor IDs for this clinic from the old doctors table
        $oldDoctorRecords = DB::connection($this->oldDb)
            ->table('doctors')
            ->where('clinics_id', $this->oldClinicId)
            ->get();

        // Collect user IDs of doctors in this clinic
        $clinicUserIds = $oldDoctorRecords->pluck('user_id')->toArray();

        // Also get users directly linked to this clinic via clinic_id
        $clinicDirectUsers = DB::connection($this->oldDb)
            ->table('users')
            ->where('clinic_id', $this->oldClinicId)
            ->whereNotNull('role_id')
            ->pluck('id')
            ->toArray();

        $allUserIds = array_unique(array_merge($clinicUserIds, $clinicDirectUsers));

        if (empty($allUserIds)) {
            $this->command->warn('   ⚠ No users found for this clinic');
            return;
        }

        // Get old users with role_id (not null = not patients)
        $oldUsers = DB::connection($this->oldDb)
            ->table('users')
            ->whereIn('id', $allUserIds)
            ->whereNotNull('role_id')
            ->get();

        foreach ($oldUsers as $oldUser) {
            // Map old role_id to new Spatie role
            $newRole = $this->mapOldRoleToNew($oldUser->role_id);

            if (!$newRole) {
                $this->command->warn("   ⚠ Skipping user {$oldUser->name} (unknown role_id: {$oldUser->role_id})");
                continue;
            }

            // Check for duplicate phone
            $phone = $oldUser->phone ?? ('old_' . $oldUser->id);
            $existingUser = User::where('phone', $phone)->first();

            if ($existingUser) {
                $this->userIdMap[$oldUser->id] = $existingUser->id;
                $this->command->warn("   ⚠ User with phone {$phone} already exists, mapping old:{$oldUser->id} → existing:{$existingUser->id}");
                continue;
            }

            $newUser = User::create([
                'name' => $oldUser->name,
                'email' => $oldUser->email,
                'phone' => $phone,
                'password' => $oldUser->password ?? Hash::make('12345678'),
                'is_active' => true,
            ]);

            // Preserve original timestamps
            $newUser->created_at = $oldUser->created_at;
            $newUser->updated_at = $oldUser->updated_at;
            $newUser->saveQuietly();

            // Assign Spatie role
            $newUser->assignRole($newRole);

            $this->userIdMap[$oldUser->id] = $newUser->id;
            $this->command->info("   ✓ User: {$oldUser->name} | Role: {$newRole} (old:{$oldUser->id} → new:{$newUser->id})");
        }
    }

    /**
     * Map old role_id to new Spatie role name.
     */
    private function mapOldRoleToNew(?int $roleId): ?string
    {
        return match ($roleId) {
            5 => 'clinic_super_doctor',
            4 => 'doctor',
            3, 6 => 'secretary',
            default => null,
        };
    }

    /**
     * Migrate patients from old DB.
     * 
     * Uses DoctorPatient table to find patients linked to doctors in this clinic.
     * Filters by clinic using: DoctorPatient → doctors → clinics_id
     */
    private function migratePatients(): void
    {
        $this->command->info('🏥 Migrating patients...');

        // Get all doctor IDs for this clinic
        $clinicDoctorIds = DB::connection($this->oldDb)
            ->table('doctors')
            ->where('clinics_id', $this->oldClinicId)
            ->pluck('id')
            ->toArray();

        if (empty($clinicDoctorIds)) {
            $this->command->warn('   ⚠ No doctors found for this clinic, cannot find patients');
            return;
        }

        $this->command->info("   Found " . count($clinicDoctorIds) . " doctors for clinic {$this->oldClinicId}");

        // Get patient IDs from DoctorPatient table (many-to-many relationship)
        $clinicPatientIds = DB::connection($this->oldDb)
            ->table('DoctorPatient')
            ->whereIn('doctors_id', $clinicDoctorIds)
            ->pluck('patients_id')
            ->unique()
            ->toArray();

        if (empty($clinicPatientIds)) {
            $this->command->warn('   ⚠ No patients found in DoctorPatient table for this clinic');
            return;
        }

        $this->command->info("   Found " . count($clinicPatientIds) . " patients linked to clinic {$this->oldClinicId}");

        // Get patients data from patients table (including deleted ones)
        $oldPatients = DB::connection($this->oldDb)
            ->table('patients')
            ->whereIn('id', $clinicPatientIds)
            ->get();

        foreach ($oldPatients as $oldPatient) {
            // Get primary doctor for this patient from DoctorPatient table
            $doctorPatientRecord = DB::connection($this->oldDb)
                ->table('DoctorPatient')
                ->where('patients_id', $oldPatient->id)
                ->whereIn('doctors_id', $clinicDoctorIds) // Only doctors from this clinic
                ->first();

            $newDoctorId = null;
            $clinicId = null;
            
            if ($doctorPatientRecord) {
                $oldDoctorRecord = DB::connection($this->oldDb)
                    ->table('doctors')
                    ->where('id', $doctorPatientRecord->doctors_id)
                    ->first();

                if ($oldDoctorRecord) {
                    if (isset($this->userIdMap[$oldDoctorRecord->user_id])) {
                        $newDoctorId = $this->userIdMap[$oldDoctorRecord->user_id];
                    }
                    // Get clinic_id from doctor record
                    $clinicId = $oldDoctorRecord->clinics_id;
                }
            }

            // Get the patient name
            $patientName = $oldPatient->name;

            // Handle duplicate phones: each old patient = separate new patient
            $phone = $oldPatient->phone;
            if ($phone) {
                $existingPatient = Patient::where('phone', $phone)->first();
                if ($existingPatient) {
                    // Append old patient ID to make phone unique
                    $phone = $phone . '_' . $oldPatient->id;
                    $this->command->warn("   ⚠ Duplicate phone for {$patientName}, using: {$phone}");
                }
            }

            try {
                $newPatient = Patient::withoutEvents(function () use ($oldPatient, $newDoctorId, $clinicId, $patientName, $phone) {
                    return Patient::create([
                        'name' => $patientName,
                        'age' => $oldPatient->age,
                        'doctor_id' => $newDoctorId,
                        'clinic_id' => $clinicId,
                        'phone' => $phone,
                        'systemic_conditions' => $oldPatient->systemic_conditions,
                        'sex' => $oldPatient->sex,
                        'address' => $oldPatient->address,
                        'notes' => $oldPatient->notes,
                        'birth_date' => $oldPatient->birth_date,
                        'public_token' => Str::uuid()->toString(),
                    ]);
                });

                // Preserve original timestamps and soft delete status
                $newPatient->created_at = $oldPatient->created_at;
                $newPatient->updated_at = $oldPatient->updated_at;
                if ($oldPatient->deleted_at) {
                    $newPatient->deleted_at = $oldPatient->deleted_at;
                }
                $newPatient->saveQuietly();

                $this->patientIdMap[$oldPatient->id] = $newPatient->id;
                $this->command->info("   ✓ Patient: {$patientName} (old:{$oldPatient->id} → new:{$newPatient->id})");
            } catch (\Exception $e) {
                $this->command->error("   ✗ Patient: {$patientName} - Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Migrate cases from old DB.
     * 
     * Old: cases (patient_id, case_categores_id, notes, status_id, user_id, price, tooth_num, root_stuffing, is_paid)
     * New: cases (patient_id, doctor_id, case_categores_id, notes, status_id, price, tooth_num, root_stuffing, is_paid)
     * 
     * doctor_id comes from CaseDoctor table in old DB.
     */
    private function migrateCases(): void
    {
        $this->command->info('📋 Migrating cases...');

        // Get all cases for patients we already migrated
        $oldPatientIds = array_keys($this->patientIdMap);

        if (empty($oldPatientIds)) {
            $this->command->warn('   ⚠ No patients migrated, skipping cases');
            return;
        }

        $oldCases = DB::connection($this->oldDb)
            ->table('cases')
            ->whereIn('patient_id', $oldPatientIds)
            ->get();

        foreach ($oldCases as $oldCase) {
            // Map patient_id
            $newPatientId = $this->patientIdMap[$oldCase->patient_id] ?? null;
            if (!$newPatientId) {
                $this->command->warn("   ⚠ Skipping case {$oldCase->id}: patient not mapped");
                continue;
            }

            // Map case category
            $newCaseCategoryId = $this->caseCategoryIdMap[$oldCase->case_categores_id] ?? null;
            if (!$newCaseCategoryId) {
                $this->command->warn("   ⚠ Case {$oldCase->id}: category not mapped, using first available");
                $newCaseCategoryId = CaseCategory::first()?->id;
                if (!$newCaseCategoryId) {
                    $this->command->warn("   ⚠ Skipping case {$oldCase->id}: no category available");
                    continue;
                }
            }

            // Map status
            $newStatusId = $this->statusIdMap[$oldCase->status_id] ?? null;
            if (!$newStatusId) {
                $this->command->warn("   ⚠ Case {$oldCase->id}: status not mapped, using first available");
                $newStatusId = Status::first()?->id;
                if (!$newStatusId) {
                    $this->command->warn("   ⚠ Skipping case {$oldCase->id}: no status available");
                    continue;
                }
            }

            // Get doctor_id from CaseDoctor table
            $caseDoctorRecord = DB::connection($this->oldDb)
                ->table('CaseDoctor')
                ->where('cases_id', $oldCase->id)
                ->first();

            $newDoctorId = null;
            if ($caseDoctorRecord) {
                // CaseDoctor.doctors_id references old doctors.id
                $oldDoctorRecord = DB::connection($this->oldDb)
                    ->table('doctors')
                    ->where('id', $caseDoctorRecord->doctors_id)
                    ->first();

                if ($oldDoctorRecord && isset($this->userIdMap[$oldDoctorRecord->user_id])) {
                    $newDoctorId = $this->userIdMap[$oldDoctorRecord->user_id];
                }
            }

            // Fallback: use user_id from case if CaseDoctor not found
            if (!$newDoctorId && $oldCase->user_id) {
                $newDoctorId = $this->userIdMap[$oldCase->user_id] ?? null;
            }

            // Final fallback: use first user
            if (!$newDoctorId) {
                $newDoctorId = User::first()?->id;
            }

            $newCase = CaseModel::create([
                'patient_id' => $newPatientId,
                'doctor_id' => $newDoctorId,
                'case_categores_id' => $newCaseCategoryId,
                'notes' => $oldCase->notes,
                'status_id' => $newStatusId,
                'price' => $oldCase->price,
                'tooth_num' => $oldCase->tooth_num,
                'root_stuffing' => $oldCase->root_stuffing,
                'is_paid' => $oldCase->is_paid ?? false,
            ]);

            // Preserve original timestamps
            $newCase->created_at = $oldCase->created_at;
            $newCase->updated_at = $oldCase->updated_at;
            $newCase->saveQuietly();

            $this->caseIdMap[$oldCase->id] = $newCase->id;
            $this->command->info("   ✓ Case #{$oldCase->id} → #{$newCase->id} (patient: {$newPatientId}, doctor: {$newDoctorId})");
        }
    }

    /**
     * Migrate sessions from old DB → notes in new DB.
     * 
     * Old: sessions (id, note, case_id, date)
     * New: notes (noteable_id, noteable_type, content, created_by) - polymorphic to CaseModel
     */
    private function migrateSessions(): void
    {
        $this->command->info('📝 Migrating sessions → notes...');

        $oldCaseIds = array_keys($this->caseIdMap);

        if (empty($oldCaseIds)) {
            $this->command->warn('   ⚠ No cases migrated, skipping sessions/notes');
            return;
        }

        $oldSessions = DB::connection($this->oldDb)
            ->table('sessions')
            ->whereIn('case_id', $oldCaseIds)
            ->get();

        $count = 0;
        foreach ($oldSessions as $oldSession) {
            $newCaseId = $this->caseIdMap[$oldSession->case_id] ?? null;
            if (!$newCaseId) {
                continue;
            }

            $content = $oldSession->note ?? '';
            if (empty(trim($content))) {
                $content = '(session without notes)';
            }

            $note = Note::create([
                'noteable_id' => $newCaseId,
                'noteable_type' => CaseModel::class,
                'content' => $content,
                'created_by' => User::first()?->id,
            ]);

            // Preserve original date
            if ($oldSession->date) {
                $note->created_at = $oldSession->date;
                $note->updated_at = $oldSession->date;
                $note->saveQuietly();
            }

            $count++;
        }

        $this->command->info("   ✓ Migrated {$count} sessions → notes");
    }

    /**
     * Migrate bills from old DB.
     * 
     * Old: bills (billable_id, billable_type, price, PaymentDate)
     * New: bills (patient_id, billable_id, billable_type, is_paid, price, doctor_id)
     */
    private function migrateBills(): void
    {
        $this->command->info('💰 Migrating bills...');

        // Old bills reference cases via billable
        $oldBills = DB::connection($this->oldDb)->table('bills')->get();

        $count = 0;
        foreach ($oldBills as $oldBill) {
            // Only migrate bills related to cases we migrated
            if ($oldBill->billable_type === 'App\\Models\\CaseModel' || 
                $oldBill->billable_type === 'App\\Models\\MedicalCase' ||
                str_contains($oldBill->billable_type, 'Case')) {
                
                $newCaseId = $this->caseIdMap[$oldBill->billable_id] ?? null;
                if (!$newCaseId) {
                    continue;
                }

                // Find the case to get patient and doctor
                $newCase = CaseModel::find($newCaseId);
                if (!$newCase) {
                    continue;
                }

                $bill = Bill::withoutEvents(function () use ($oldBill, $newCase, $newCaseId) {
                    return Bill::create([
                        'patient_id' => $newCase->patient_id,
                        'billable_id' => $newCaseId,
                        'billable_type' => CaseModel::class,
                        'is_paid' => $oldBill->PaymentDate ? true : false,
                        'price' => $oldBill->price,
                        'doctor_id' => $newCase->doctor_id,
                    ]);
                });

                // Preserve timestamps
                $bill->created_at = $oldBill->created_at;
                $bill->updated_at = $oldBill->updated_at;
                $bill->saveQuietly();

                $count++;
            }
        }

        $this->command->info("   ✓ Migrated {$count} bills");
    }

    /**
     * Migrate images from old DB.
     * 
     * Old: images (image_url, imageable_id, imageable_type, descrption)
     * New: images (path, disk, type, imageable_id, imageable_type, alt_text)
     */
    private function migrateImages(): void
    {
        $this->command->info('🖼️ Migrating images...');

        $oldImages = DB::connection($this->oldDb)->table('images')->get();

        $count = 0;
        foreach ($oldImages as $oldImage) {
            $newImageableId = null;
            $newImageableType = null;

            // Map imageable_type and imageable_id
            if (str_contains($oldImage->imageable_type, 'Patient')) {
                $newImageableId = $this->patientIdMap[$oldImage->imageable_id] ?? null;
                $newImageableType = Patient::class;
            } elseif (str_contains($oldImage->imageable_type, 'Case')) {
                $newImageableId = $this->caseIdMap[$oldImage->imageable_id] ?? null;
                $newImageableType = CaseModel::class;
            }

            if (!$newImageableId || !$newImageableType) {
                continue;
            }

            $image = Image::create([
                'path' => $oldImage->image_url,
                'disk' => 'public',
                'type' => 'general',
                'imageable_id' => $newImageableId,
                'imageable_type' => $newImageableType,
                'alt_text' => $oldImage->descrption,
            ]);

            // Preserve original timestamps
            $image->created_at = $oldImage->created_at;
            $image->updated_at = $oldImage->updated_at;
            $image->saveQuietly();

            $count++;
        }

        $this->command->info("   ✓ Migrated {$count} images");
    }

    /**
     * Migrate conjugations_categoriesv2 → clinic_expense_categories.
     * 
     * Old: conjugations_categoriesv2 (id, name, doctors_id, clinic_id)
     * New: clinic_expense_categories (id, name, description, is_active, creator_id, updator_id)
     * 
     * Note: Migrates categories that are USED by this clinic's expenses,
     * not just categories where clinic_id matches (since categories can be shared).
     */
    private function migrateExpenseCategories(): void
    {
        $this->command->info('📂 Migrating expense categories (conjugations_categoriesv2)...');

        // Get category IDs used by this clinic's expenses
        $usedCategoryIds = DB::connection($this->oldDb)
            ->table('conjugationsv3')
            ->where('clinics_id', $this->oldClinicId)
            ->whereNotNull('conjugations_categories_id')
            ->pluck('conjugations_categories_id')
            ->unique()
            ->toArray();

        if (empty($usedCategoryIds)) {
            $this->command->warn('   ⚠ No expense categories used by this clinic');
            return;
        }

        $this->command->info("   Found " . count($usedCategoryIds) . " categories used by clinic expenses");

        // Get these categories
        $oldCategories = DB::connection($this->oldDb)
            ->table('conjugations_categoriesv2')
            ->whereIn('id', $usedCategoryIds)
            ->get();

        foreach ($oldCategories as $oldCategory) {
            $creatorId = null;
            // Map old doctors_id → old doctor record → old user_id → new user_id
            if ($oldCategory->doctors_id) {
                $oldDoctorRecord = DB::connection($this->oldDb)
                    ->table('doctors')
                    ->where('id', $oldCategory->doctors_id)
                    ->first();

                if ($oldDoctorRecord && isset($this->userIdMap[$oldDoctorRecord->user_id])) {
                    $creatorId = $this->userIdMap[$oldDoctorRecord->user_id];
                }
            }

            // Fallback to first user if no creator found
            if (!$creatorId) {
                $creatorId = User::first()?->id;
            }

            $newCategory = ClinicExpenseCategory::withoutEvents(function () use ($oldCategory, $creatorId) {
                return ClinicExpenseCategory::create([
                    'name' => $oldCategory->name,
                    'description' => null,
                    'is_active' => true,
                    'creator_id' => $creatorId,
                    'updator_id' => $creatorId,
                ]);
            });

            // Preserve original timestamps
            $newCategory->created_at = $oldCategory->created_at;
            $newCategory->updated_at = $oldCategory->updated_at;
            $newCategory->saveQuietly();

            $this->expenseCategoryIdMap[$oldCategory->id] = $newCategory->id;
            $this->command->info("   ✓ Expense Category: {$oldCategory->name} (old:{$oldCategory->id} → new:{$newCategory->id})");
        }
    }

    /**
     * Migrate conjugationsv3 → clinic_expenses.
     * 
     * Old: conjugationsv3 (id, name, quantity, conjugations_categories_id, clinics_id, date, price, is_paid, doctor_id)
     * New: clinic_expenses (id, name, quantity, clinic_expense_category_id, date, price, is_paid, doctor_id, creator_id, updator_id)
     */
    private function migrateExpenses(): void
    {
        $this->command->info('💸 Migrating expenses (conjugationsv3)...');

        $oldExpenses = DB::connection($this->oldDb)
            ->table('conjugationsv3')
            ->where('clinics_id', $this->oldClinicId)
            ->get();

        $count = 0;
        foreach ($oldExpenses as $oldExpense) {
            // Map category
            $newCategoryId = null;
            if ($oldExpense->conjugations_categories_id) {
                $newCategoryId = $this->expenseCategoryIdMap[$oldExpense->conjugations_categories_id] ?? null;
            }

            // Map doctor_id
            $newDoctorId = null;
            if ($oldExpense->doctor_id) {
                // Old doctor_id in conjugationsv3 might reference doctors.id or users.id
                // Try doctors table first
                $oldDoctorRecord = DB::connection($this->oldDb)
                    ->table('doctors')
                    ->where('id', $oldExpense->doctor_id)
                    ->first();

                if ($oldDoctorRecord && isset($this->userIdMap[$oldDoctorRecord->user_id])) {
                    $newDoctorId = $this->userIdMap[$oldDoctorRecord->user_id];
                } elseif (isset($this->userIdMap[$oldExpense->doctor_id])) {
                    // Fallback: maybe it references users directly
                    $newDoctorId = $this->userIdMap[$oldExpense->doctor_id];
                }
            }

            $expense = ClinicExpense::withoutEvents(function () use ($oldExpense, $newCategoryId, $newDoctorId) {
                return ClinicExpense::create([
                    'name' => $oldExpense->name,
                    'quantity' => $oldExpense->quantity,
                    'clinic_expense_category_id' => $newCategoryId,
                    'date' => $oldExpense->date,
                    'price' => $oldExpense->price,
                    'is_paid' => $oldExpense->is_paid ?? false,
                    'doctor_id' => $newDoctorId,
                    'creator_id' => $newDoctorId,
                    'updator_id' => $newDoctorId,
                ]);
            });

            // Preserve timestamps
            $expense->created_at = $oldExpense->created_at;
            $expense->updated_at = $oldExpense->updated_at;
            $expense->saveQuietly();

            $count++;
        }

        $this->command->info("   ✓ Migrated {$count} expenses");
    }
}
