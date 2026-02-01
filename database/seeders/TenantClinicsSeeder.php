<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantClinicsSeeder extends Seeder
{
    /**
     * Seed multiple clinics with demo data.
     */
    public function run(): void
    {
        $this->command->info('🏥 Creating demo clinics...');

        // Clinic 1: عيادة الأمل
        $clinic1 = $this->createClinic([
            'id' => '_amal',
            'name' => 'عيادة الأمل للأسنان',
            'name_en' => 'Al-Amal Dental Clinic',
            'address' => 'بغداد - الكرادة - شارع الرشيد',
            'phone' => '07701234567',
            'email' => 'info@amal-clinic.com',
            'whatsapp_enabled' => true,
            'whatsapp_number' => '9647701234567',
        ]);

        // Clinic 2: عيادة النور
        $clinic2 = $this->createClinic([
            'id' => '_noor',
            'name' => 'عيادة النور للأسنان',
            'name_en' => 'Al-Noor Dental Clinic',
            'address' => 'البصرة - العشار - شارع الكويت',
            'phone' => '07809876543',
            'email' => 'info@noor-clinic.com',
            'whatsapp_enabled' => true,
            'whatsapp_number' => '9647809876543',
        ]);

        // Clinic 3: عيادة الشفاء
        $clinic3 = $this->createClinic([
            'id' => '_shifa',
            'name' => 'عيادة الشفاء للأسنان',
            'name_en' => 'Al-Shifa Dental Clinic',
            'address' => 'أربيل - 100 متر - قرب مجمع مولتقى',
            'phone' => '07511122334',
            'email' => 'info@shifa-clinic.com',
            'whatsapp_enabled' => false,
            'whatsapp_number' => null,
        ]);

        $this->command->info('✅ Created 3 clinics successfully!');
        $this->command->newLine();

        // Migrate and seed each tenant
        $this->setupTenant($clinic1, '_amal');
        $this->setupTenant($clinic2, '_noor');
        $this->setupTenant($clinic3, '_shifa');

        $this->command->info('🎉 All clinics are ready with demo data!');
        $this->displaySummary();
    }

    /**
     * Create a clinic (tenant).
     */
    private function createClinic(array $data): Tenant
    {
        $this->command->info("Creating: {$data['name']}...");
        
        // Check if tenant already exists
        $existingTenant = Tenant::find($data['id']);
        if ($existingTenant) {
            $this->command->warn("  ⚠ Clinic already exists, using existing one...");
            return $existingTenant;
        }
        
        // Create tenant record with all fields
        DB::table('tenants')->insert([
            'id' => $data['id'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'rx_img' => '',
            'whatsapp_template_sid' => '',
            'whatsapp_message_count' => 0,
            'whatsapp_phone' => $data['whatsapp_number'] ?? '',
            'show_image_case' => true,
            'doctor_mony' => 0,
            'teeth_v2' => true,
            'send_msg' => $data['whatsapp_enabled'] ?? false,
            'show_rx_id' => true,
            'logo' => '',
            'api_whatsapp' => false,
            'data' => json_encode([
                'name_en' => $data['name_en'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $tenant = Tenant::find($data['id']);
        
        $this->command->info("✓ Clinic ID: {$tenant->id}");
        
        return $tenant;
    }

    /**
     * Setup tenant database with migrations and seed data.
     */
    private function setupTenant(Tenant $tenant, string $clinicName): void
    {
        $this->command->info("🔧 Setting up {$tenant->name}...");

        try {
            // Create database manually if it doesn't exist
            $dbName = 'tenant' . $tenant->id;
            $this->command->info("  ↳ Creating database: {$dbName}");
            
            try {
                // Drop if exists and recreate
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
                DB::statement("CREATE DATABASE `{$dbName}`");
                $this->command->info("  ✓ Database created");
            } catch (\Exception $e) {
                $this->command->error("  ✗ Database creation failed: " . $e->getMessage());
                return;
            }

            // Run migrations
            $this->command->info("  ↳ Running migrations...");
            try {
                // Create cache table first to avoid the Spatie permission issue
                $cacheTable = "CREATE TABLE IF NOT EXISTS `{$dbName}`.`cache` (
                    `key` varchar(255) NOT NULL,
                    `value` mediumtext NOT NULL,
                    `expiration` int NOT NULL,
                    PRIMARY KEY (`key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                DB::statement($cacheTable);
                
                // Now run all migrations
                Artisan::call('tenants:migrate', [
                    '--tenants' => [$tenant->id],
                    '--force' => true,
                ]);
                $this->command->info("  ✓ Migrations completed");
            } catch (\Exception $e) {
                $this->command->error("  ✗ Migrations failed: " . $e->getMessage());
                return;
            }

            // Seed basic data
            $this->command->info("  ↳ Seeding basic data...");
            try {
                Artisan::call('tenants:seed', [
                    '--tenants' => [$tenant->id],
                    '--class' => 'TenantDatabaseSeeder',
                ]);
                $this->command->info("  ✓ Basic data seeded");
            } catch (\Exception $e) {
                $this->command->warn("  ⚠ Basic seeding failed: " . $e->getMessage());
            }

            // Add demo data specific to each clinic
            $this->command->info("  ↳ Adding demo data...");
            $this->seedDemoData($tenant, $clinicName);

            $this->command->info("✅ {$tenant->name} is ready!");
            $this->command->newLine();

        } catch (\Exception $e) {
            $this->command->error("❌ Error setting up {$tenant->name}: {$e->getMessage()}");
        }
    }

    /**
     * Seed demo data for a specific tenant.
     */
    private function seedDemoData(Tenant $tenant, string $clinicName): void
    {
        $tenant->run(function () use ($clinicName) {
            $this->seedDoctors($clinicName);
            $this->seedPatients($clinicName);
            $this->seedCases($clinicName);
        });
    }

    /**
     * Seed doctors for each clinic.
     */
    private function seedDoctors(string $clinicName): void
    {
        $doctors = [
            '_amal' => [
                [
                    'name' => 'د. أحمد محمد',
                    'email' => 'ahmed@amal.com',
                    'phone' => '07701111111',
                    'specialization' => 'طبيب أسنان عام',
                ],
                [
                    'name' => 'د. سارة علي',
                    'email' => 'sara@amal.com',
                    'phone' => '07701111112',
                    'specialization' => 'تقويم الأسنان',
                ],
            ],
            '_noor' => [
                [
                    'name' => 'د. خالد حسن',
                    'email' => 'khaled@noor.com',
                    'phone' => '07802222221',
                    'specialization' => 'جراحة الفم والأسنان',
                ],
                [
                    'name' => 'د. منى يوسف',
                    'email' => 'mona@noor.com',
                    'phone' => '07802222222',
                    'specialization' => 'طب أسنان الأطفال',
                ],
            ],
            '_shifa' => [
                [
                    'name' => 'د. عمر الكردي',
                    'email' => 'omar@shifa.com',
                    'phone' => '07513333331',
                    'specialization' => 'تركيبات الأسنان',
                ],
                [
                    'name' => 'د. ليلى رشيد',
                    'email' => 'layla@shifa.com',
                    'phone' => '07513333332',
                    'specialization' => 'علاج العصب',
                ],
            ],
        ];

        foreach ($doctors[$clinicName] as $doctor) {
            DB::table('users')->insert([
                'name' => $doctor['name'],
                'email' => $doctor['email'],
                'phone' => $doctor['phone'],
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign doctor role
            $user = DB::table('users')->where('email', $doctor['email'])->first();
            if ($user) {
                DB::table('model_has_roles')->insert([
                    'role_id' => 1, // Doctor role
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $user->id,
                ]);
            }
        }
    }

    /**
     * Seed patients for each clinic.
     */
    private function seedPatients(string $clinicName): void
    {
        $patients = [
            '_amal' => [
                ['name' => 'محمد أحمد', 'phone' => '07701234561', 'age' => 35, 'gender' => 'male'],
                ['name' => 'فاطمة علي', 'phone' => '07701234562', 'age' => 28, 'gender' => 'female'],
                ['name' => 'حسين محمود', 'phone' => '07701234563', 'age' => 42, 'gender' => 'male'],
                ['name' => 'زينب حسن', 'phone' => '07701234564', 'age' => 31, 'gender' => 'female'],
                ['name' => 'علي جاسم', 'phone' => '07701234565', 'age' => 25, 'gender' => 'male'],
            ],
            '_noor' => [
                ['name' => 'ياسر خالد', 'phone' => '07809876541', 'age' => 38, 'gender' => 'male'],
                ['name' => 'نور عبدالله', 'phone' => '07809876542', 'age' => 22, 'gender' => 'female'],
                ['name' => 'كريم سعيد', 'phone' => '07809876543', 'age' => 45, 'gender' => 'male'],
                ['name' => 'رنا محمد', 'phone' => '07809876544', 'age' => 29, 'gender' => 'female'],
                ['name' => 'أسامة فاضل', 'phone' => '07809876545', 'age' => 33, 'gender' => 'male'],
            ],
            '_shifa' => [
                ['name' => 'شيرين أحمد', 'phone' => '07511122331', 'age' => 27, 'gender' => 'female'],
                ['name' => 'دلشاد رشيد', 'phone' => '07511122332', 'age' => 40, 'gender' => 'male'],
                ['name' => 'آفين علي', 'phone' => '07511122333', 'age' => 24, 'gender' => 'female'],
                ['name' => 'سردار حسن', 'phone' => '07511122334', 'age' => 36, 'gender' => 'male'],
                ['name' => 'هيفي يوسف', 'phone' => '07511122335', 'age' => 30, 'gender' => 'female'],
            ],
        ];

        foreach ($patients[$clinicName] as $patient) {
            DB::table('patients')->insert([
                'name' => $patient['name'],
                'phone' => $patient['phone'],
                'age' => $patient['age'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Seed cases for each clinic.
     */
    private function seedCases(string $clinicName): void
    {
        // Get first doctor and first 3 patients
        $doctor = DB::table('users')->first();
        $patients = DB::table('patients')->limit(3)->get();
        
        // Get first category and status
        $category = DB::table('case_categories')->first();
        $status = DB::table('statuses')->first();

        if (!$doctor || $patients->isEmpty() || !$category || !$status) {
            return;
        }

        $caseTypes = [
            ['cost' => 50000, 'notes' => 'حالة علاج تسوس'],
            ['cost' => 75000, 'notes' => 'حالة التهاب لثة'],
            ['cost' => 100000, 'notes' => 'حالة خلع ضرس'],
        ];

        foreach ($patients as $index => $patient) {
            $caseType = $caseTypes[$index % count($caseTypes)];
            
            DB::table('cases')->insert([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'case_categores_id' => $category->id,
                'status_id' => $status->id,
                'price' => $caseType['cost'],
                'notes' => $caseType['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Display summary of created data.
     */
    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('          📊 SUMMARY OF CREATED DATA');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->newLine();

        // Use central connection to get tenants
        $tenants = DB::connection('mysql')->table('tenants')->get();

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::find($tenantData->id);
            if (!$tenant) continue;
            
            try {
                $tenant->run(function () use ($tenant) {
                    $doctorsCount = DB::table('users')->count();
                    $patientsCount = DB::table('patients')->count();
                    $casesCount = DB::table('cases')->count();

                    $this->command->info("🏥 {$tenant->name}");
                    $this->command->info("   Database: tenant{$tenant->id}");
                    $this->command->info("   Doctors: {$doctorsCount}");
                    $this->command->info("   Patients: {$patientsCount}");
                    $this->command->info("   Cases: {$casesCount}");
                    $this->command->newLine();
                });
            } catch (\Exception $e) {
                $this->command->error("❌ Could not retrieve data for {$tenant->name}: {$e->getMessage()}");
                $this->command->newLine();
            }
        }

        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('🎯 Test the API with these credentials:');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->newLine();
        $this->command->info('Clinic 1 (عيادة الأمل):');
        $this->command->info('  X-Tenant-ID: _amal');
        $this->command->info('  Email: ahmed@amal.com');
        $this->command->info('  Password: password');
        $this->command->newLine();
        $this->command->info('Clinic 2 (عيادة النور):');
        $this->command->info('  X-Tenant-ID: _noor');
        $this->command->info('  Email: khaled@noor.com');
        $this->command->info('  Password: password');
        $this->command->newLine();
        $this->command->info('Clinic 3 (عيادة الشفاء):');
        $this->command->info('  X-Tenant-ID: _shifa');
        $this->command->info('  Email: omar@shifa.com');
        $this->command->info('  Password: password');
        $this->command->info('═══════════════════════════════════════════════');
    }
}
