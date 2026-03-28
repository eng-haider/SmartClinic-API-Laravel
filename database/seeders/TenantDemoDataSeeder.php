<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * TenantDemoDataSeeder
 *
 * Seeds full demo data into tenant database u876784197_tenant_02.
 * Covers all tenant tables: statuses, case_categories, from_where_come,
 * reservation_types, clinic_settings, roles/permissions, users,
 * patients, cases, dental_encounter_details, ophthalmology_encounter_details,
 * reservations, bills, recipes.
 *
 * Run with:
 *   php artisan db:seed --class=TenantDemoDataSeeder
 *
 * Or override DB at runtime:
 *   TENANT_DB_DATABASE=u876784197_tenant_02 \
 *   TENANT_DB_USERNAME=u876784197_tenant_02 \
 *   TENANT_DB_PASSWORD='9!iSeEys:6sO' \
 *   php artisan db:seed --class=TenantDemoDataSeeder
 */
class TenantDemoDataSeeder extends Seeder
{
    // ── Tenant DB config ──────────────────────────────────────────────────────
    private const DB_NAME     = 'u876784197_tenant_02';
    private const DB_USER     = 'u876784197_tenant_02';
    private const DB_PASSWORD = '9!iSeEys:6sO';

    /** Specialty to demo: 'dental' | 'ophthalmology' | 'beauty' */
    private const SPECIALTY   = 'ophthalmology';

    // ── Runtime state ─────────────────────────────────────────────────────────
    private int $doctorId;
    private int $secretaryId;
    private array $patientIds  = [];
    private array $statusIds   = [];
    private array $categoryIds = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->configureTenantConnection();

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  SmartClinic Demo Seeder – ' . self::SPECIALTY);
        $this->command->info('  Database: ' . self::DB_NAME);
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');

        DB::connection('tenant')->transaction(function () {
            $this->seedUsers();
            $this->seedStatuses();
            $this->seedCaseCategories();
            $this->seedFromWhereCome();
            $this->seedReservationTypes();
            $this->seedClinicSettings();
            $this->seedPatients();
            $this->seedCasesWithEncounters();
            $this->seedReservations();
            $this->seedRecipes();
        });

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  ✅ Demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('  Specialty: ' . self::SPECIALTY);
        $this->command->info('  Login credentials:');
        $this->command->info('    Doctor 1  → phone: 07700100001 / pass: demo1234 (Dr. Khalid Al-Nouri)');
        $this->command->info('    Doctor 2  → phone: 07700100003 / pass: demo1234 (Dr. Rania Al-Jubouri)');
        $this->command->info('    Secretary → phone: 07700100002 / pass: demo1234 (Sara Mahmoud)');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');
    }

    // ── DB Connection ─────────────────────────────────────────────────────────

    private function configureTenantConnection(): void
    {
        // Always target the hardcoded tenant DB — env vars are intentionally ignored.
        Config::set('database.connections.tenant.database', self::DB_NAME);
        Config::set('database.connections.tenant.username', self::DB_USER);
        Config::set('database.connections.tenant.password', self::DB_PASSWORD);

        // Purge any cached connection so the new config is picked up.
        DB::purge('tenant');
        DB::reconnect('tenant');

        $this->command->info('  ⚡ Connected to: ' . Config::get('database.connections.tenant.database'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function db(): \Illuminate\Database\Connection
    {
        return DB::connection('tenant');
    }

    private function now(): string
    {
        return now()->toDateTimeString();
    }

    private function insertOrIgnore(string $table, array $row): int
    {
        // Returns the inserted id or 0 if duplicate
        try {
            return (int) $this->db()->table($table)->insertGetId($row);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return 0; // duplicate key – skip silently
            }
            throw $e;
        }
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        $this->command->info('  👤 Seeding users...');

        $now  = $this->now();
        $pass = Hash::make('demo1234');

        $users = [
            [
                'name'       => 'Dr. Khalid Al-Nouri',
                'phone'      => '07700100001',
                'email'      => 'doctor@demo-tenant02.com',
                'password'   => $pass,
                'is_active'  => 1,
                'role'       => 'clinic_super_doctor',
            ],
            [
                'name'       => 'Dr. Rania Al-Jubouri',
                'phone'      => '07700100003',
                'email'      => 'doctor2@demo-tenant02.com',
                'password'   => $pass,
                'is_active'  => 1,
                'role'       => 'clinic_doctor',
            ],
            [
                'name'       => 'Sara Mahmoud',
                'phone'      => '07700100002',
                'email'      => 'secretary@demo-tenant02.com',
                'password'   => $pass,
                'is_active'  => 1,
                'role'       => 'clinic_secretary',
            ],
        ];

        foreach ($users as $i => $data) {
            unset($data['role']);

            $existing = $this->db()->table('users')->where('phone', $data['phone'])->first();

            if ($existing) {
                $userId = $existing->id;
                $this->command->warn('    ⚠ User ' . $data['phone'] . ' already exists, skipping');
            } else {
                $userId = $this->db()->table('users')->insertGetId(array_merge($data, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $this->command->info('    ✓ Created user: ' . $data['name']);
            }

            if ($i === 0) {
                $this->doctorId    = $userId;
            } elseif ($i === 2) {
                $this->secretaryId = $userId;
            }
        }
    }

    // ── Statuses ──────────────────────────────────────────────────────────────

    private function seedStatuses(): void
    {
        $this->command->info('  📋 Seeding statuses...');

        $now      = $this->now();
        $statuses = [
            ['name_ar' => 'جديد',        'name_en' => 'New',         'color' => '#3B82F6', 'order' => 1],
            ['name_ar' => 'قيد التقدم',  'name_en' => 'In Progress', 'color' => '#F59E0B', 'order' => 2],
            ['name_ar' => 'مكتمل',       'name_en' => 'Completed',   'color' => '#10B981', 'order' => 3],
            ['name_ar' => 'ملغي',        'name_en' => 'Cancelled',   'color' => '#EF4444', 'order' => 4],
            ['name_ar' => 'معلق',        'name_en' => 'On Hold',     'color' => '#6B7280', 'order' => 5],
        ];

        foreach ($statuses as $status) {
            $existing = $this->db()->table('statuses')->where('name_en', $status['name_en'])->first();
            if ($existing) {
                $id = $existing->id;
            } else {
                $id = $this->db()->table('statuses')->insertGetId(array_merge($status, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
            $this->statusIds[$status['name_en']] = $id;
        }

        $this->command->info('    ✓ ' . count($statuses) . ' statuses');
    }

    // ── Case Categories ───────────────────────────────────────────────────────

    private function seedCaseCategories(): void
    {
        $this->command->info('  🏥 Seeding case categories...');

        $now = $this->now();

        $ophthalmologyCategories = [
            ['name' => 'فحص العيون',             'name_ar' => 'فحص العيون',              'name_en' => 'Eye Examination',            'category_type' => 'dental', 'item_cost' => 30000,   'order' => 1],
            ['name' => 'قياس النظر',             'name_ar' => 'قياس النظر',              'name_en' => 'Vision Test',                'category_type' => 'dental', 'item_cost' => 20000,   'order' => 2],
            ['name' => 'قياس ضغط العين',         'name_ar' => 'قياس ضغط العين',          'name_en' => 'IOP Measurement',            'category_type' => 'dental', 'item_cost' => 15000,   'order' => 3],
            ['name' => 'فحص قاع العين',          'name_ar' => 'فحص قاع العين',           'name_en' => 'Fundus Examination',         'category_type' => 'dental', 'item_cost' => 40000,   'order' => 4],
            ['name' => 'عملية الليزر',           'name_ar' => 'عملية الليزر',            'name_en' => 'Laser Surgery',              'category_type' => 'dental', 'item_cost' => 1200000, 'order' => 5],
            ['name' => 'عملية الماء الأبيض',     'name_ar' => 'عملية الماء الأبيض',      'name_en' => 'Cataract Surgery',           'category_type' => 'dental', 'item_cost' => 800000,  'order' => 6],
            ['name' => 'تصحيح النظر بالليزر',   'name_ar' => 'تصحيح النظر بالليزر',    'name_en' => 'LASIK Refractive Surgery',   'category_type' => 'dental', 'item_cost' => 1500000, 'order' => 7],
            ['name' => 'علاج الزرق',             'name_ar' => 'علاج الزرق',              'name_en' => 'Glaucoma Treatment',         'category_type' => 'dental', 'item_cost' => 60000,   'order' => 8],
            ['name' => 'حقن داخل الزجاجية',      'name_ar' => 'حقن داخل الزجاجية',       'name_en' => 'Intravitreal Injection',     'category_type' => 'dental', 'item_cost' => 250000,  'order' => 9],
            ['name' => 'علاج شبكية العين',        'name_ar' => 'علاج شبكية العين',         'name_en' => 'Retinal Laser Treatment',    'category_type' => 'dental', 'item_cost' => 350000,  'order' => 10],
            ['name' => 'فحص طفل',                'name_ar' => 'فحص طفل',                 'name_en' => 'Pediatric Eye Exam',         'category_type' => 'dental', 'item_cost' => 25000,   'order' => 11],
            ['name' => 'طوارئ عيون',             'name_ar' => 'طوارئ عيون',              'name_en' => 'Eye Emergency',              'category_type' => 'dental', 'item_cost' => 75000,   'order' => 12],
        ];

        $allCategories = $ophthalmologyCategories;

        foreach ($allCategories as $cat) {
            $existing = $this->db()->table('case_categories')->where('name_en', $cat['name_en'])->first();
            if ($existing) {
                $id = $existing->id;
            } else {
                $id = $this->db()->table('case_categories')->insertGetId(array_merge($cat, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
            $this->categoryIds[$cat['name_en']] = $id;
        }

        $this->command->info('    ✓ ' . count($allCategories) . ' case categories');
    }

    // ── From Where Come ───────────────────────────────────────────────────────

    private function seedFromWhereCome(): void
    {
        $this->command->info('  📍 Seeding patient sources...');

        $now     = $this->now();
        $sources = [
            ['name' => 'Social Media',    'name_ar' => 'وسائل التواصل الاجتماعي', 'order' => 1],
            ['name' => 'Google Search',   'name_ar' => 'بحث جوجل',                'order' => 2],
            ['name' => 'Friend Referral', 'name_ar' => 'إحالة من صديق',           'order' => 3],
            ['name' => 'Walk-in',         'name_ar' => 'زيارة مباشرة',            'order' => 4],
            ['name' => 'Doctor Referral', 'name_ar' => 'إحالة من طبيب',           'order' => 5],
            ['name' => 'Advertisement',   'name_ar' => 'إعلان',                   'order' => 6],
            ['name' => 'Website',         'name_ar' => 'الموقع الإلكتروني',        'order' => 7],
            ['name' => 'WhatsApp',        'name_ar' => 'واتساب',                  'order' => 8],
            ['name' => 'Other',           'name_ar' => 'أخرى',                   'order' => 9],
        ];

        foreach ($sources as $src) {
            $this->db()->table('from_where_comes')->insertOrIgnore(array_merge($src, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('    ✓ ' . count($sources) . ' sources');
    }

    // ── Reservation Types ─────────────────────────────────────────────────────

    private function seedReservationTypes(): void
    {
        $this->command->info('  📅 Seeding reservation types...');

        $now   = $this->now();
        $types = [
            ['name' => 'First Visit',  'name_ar' => 'زيارة أولى',    'order' => 1],
            ['name' => 'Follow Up',    'name_ar' => 'متابعة',         'order' => 2],
            ['name' => 'Emergency',    'name_ar' => 'طوارئ',          'order' => 3],
            ['name' => 'Consultation', 'name_ar' => 'استشارة',        'order' => 4],
            ['name' => 'Procedure',    'name_ar' => 'إجراء طبي',      'order' => 5],
        ];

        foreach ($types as $type) {
            $this->db()->table('reservation_types')->insertOrIgnore(array_merge($type, [
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('    ✓ ' . count($types) . ' reservation types');
    }

    // ── Clinic Settings ───────────────────────────────────────────────────────

    private function seedClinicSettings(): void
    {
        $this->command->info('  ⚙️  Seeding clinic settings...');

        $now      = $this->now();
        $settings = [
            ['setting_key' => 'clinic_name',                'setting_value' => 'SmartClinic Demo – Tenant 02', 'setting_type' => 'string',  'description' => 'Clinic display name'],
            ['setting_key' => 'clinic_reg_num',             'setting_value' => 'SC-DEMO-02-2026',              'setting_type' => 'string',  'description' => 'Clinic registration number'],
            ['setting_key' => 'specialty',                  'setting_value' => self::SPECIALTY,                'setting_type' => 'string',  'description' => 'Clinic specialty'],
            ['setting_key' => 'working_hours',              'setting_value' => '9:00 AM – 6:00 PM',           'setting_type' => 'string',  'description' => 'Clinic working hours'],
            ['setting_key' => 'appointment_duration',       'setting_value' => '30',                          'setting_type' => 'integer', 'description' => 'Default appointment duration (minutes)'],
            ['setting_key' => 'currency',                   'setting_value' => 'IQD',                         'setting_type' => 'string',  'description' => 'Currency'],
            ['setting_key' => 'tax_rate',                   'setting_value' => '0',                           'setting_type' => 'decimal', 'description' => 'Tax rate %'],
            ['setting_key' => 'paid_at_secretary',          'setting_value' => '1',                           'setting_type' => 'boolean', 'description' => 'Enable payment at secretary'],
            ['setting_key' => 'doctor_show_all_patient',    'setting_value' => '1',                           'setting_type' => 'boolean', 'description' => 'Doctors can view all patients'],
            ['setting_key' => 'enable_sms_notifications',   'setting_value' => '0',                           'setting_type' => 'boolean', 'description' => 'Enable SMS notifications'],
            ['setting_key' => 'enable_email_notifications', 'setting_value' => '0',                           'setting_type' => 'boolean', 'description' => 'Enable email notifications'],
            ['setting_key' => 'show_tooth_chart',           'setting_value' => self::SPECIALTY === 'dental' ? '1' : '0', 'setting_type' => 'boolean', 'description' => 'Show tooth chart UI'],
            ['setting_key' => 'show_eye_chart',             'setting_value' => self::SPECIALTY === 'ophthalmology' ? '1' : '0', 'setting_type' => 'boolean', 'description' => 'Show eye chart UI'],
        ];

        foreach ($settings as $s) {
            $existing = $this->db()->table('clinic_settings')->where('setting_key', $s['setting_key'])->first();
            if (!$existing) {
                $this->db()->table('clinic_settings')->insert(array_merge($s, [
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $this->command->info('    ✓ ' . count($settings) . ' settings');
    }

    // ── Patients ──────────────────────────────────────────────────────────────

    private function seedPatients(): void
    {
        $this->command->info('  👥 Seeding patients...');

        $now        = $this->now();
        $sourceIds  = $this->db()->table('from_where_comes')->pluck('id')->toArray();
        $sourceId   = fn () => $sourceIds[array_rand($sourceIds)];

        $patients = [
            // Ophthalmology patients
            ['name' => 'Ahmed Mohammed Al-Rashid',  'age' => 35, 'phone' => '07701000001', 'sex' => 1, 'birth_date' => '1990-05-15', 'address' => 'Baghdad, Al-Karrada',   'systemic_conditions' => 'Diabetes Type 2',    'notes' => 'Diabetic retinopathy screening required every 6 months.'],
            ['name' => 'Fatima Hassan Al-Saadi',    'age' => 28, 'phone' => '07701000002', 'sex' => 2, 'birth_date' => '1997-08-22', 'address' => 'Baghdad, Al-Mansour',   'systemic_conditions' => null,                 'notes' => 'Myopia -3.50 OU. Wears contact lenses.'],
            ['name' => 'Omar Khalid Al-Zubaidi',    'age' => 55, 'phone' => '07701000003', 'sex' => 1, 'birth_date' => '1970-03-10', 'address' => 'Baghdad, Al-Jadriya',   'systemic_conditions' => 'Hypertension',       'notes' => 'Open-angle glaucoma. On Timolol 0.5% BD.'],
            ['name' => 'Zahra Kareem Al-Amiri',     'age' => 25, 'phone' => '07701000004', 'sex' => 2, 'birth_date' => '2000-11-30', 'address' => 'Baghdad, Al-Amiriya',  'systemic_conditions' => null,                 'notes' => 'First visit. Keen on LASIK evaluation.'],
            ['name' => 'Hussein Jabbar Al-Tamimi',  'age' => 67, 'phone' => '07701000005', 'sex' => 1, 'birth_date' => '1958-07-18', 'address' => 'Baghdad, Al-Karkh',    'systemic_conditions' => 'Diabetes, HTN',      'notes' => 'Dense nuclear cataract OS. Surgery planned.'],
            ['name' => 'Nour Ibrahim Khalaf',       'age' => 32, 'phone' => '07701000006', 'sex' => 2, 'birth_date' => '1993-02-14', 'address' => 'Baghdad, Al-Adhamiya', 'systemic_conditions' => null,                 'notes' => 'Dry eye syndrome. Using artificial tears.'],
            ['name' => 'Mustafa Ali Al-Hadithi',    'age' => 38, 'phone' => '07701000007', 'sex' => 1, 'birth_date' => '1987-09-01', 'address' => 'Baghdad, Al-Kadhimiya','systemic_conditions' => 'Rheumatoid arthritis','notes' => 'Uveitis recurrence. Monitor closely.'],
            ['name' => 'Huda Salim Abed',           'age' => 45, 'phone' => '07701000008', 'sex' => 2, 'birth_date' => '1980-04-20', 'address' => 'Basra, Ashar',         'systemic_conditions' => null,                 'notes' => 'Pterygium OD. Referred for surgical evaluation.'],
            ['name' => 'Zaid Adnan Qasim',          'age' => 14, 'phone' => '07701000009', 'sex' => 1, 'birth_date' => '2011-12-05', 'address' => 'Baghdad, Sadr City',   'systemic_conditions' => null,                 'notes' => 'Amblyopia OS. Patching therapy ongoing.'],
            ['name' => 'Layla Tariq Al-Bayati',     'age' => 30, 'phone' => '07701000010', 'sex' => 2, 'birth_date' => '1995-06-28', 'address' => 'Baghdad, Al-Dora',     'systemic_conditions' => 'Thyroid disorder',   'notes' => 'Thyroid eye disease (TED). Proptosis OD.'],
        ];

        $created = 0;
        foreach ($patients as $idx => $p) {
            $existing = $this->db()->table('patients')->where('phone', $p['phone'])->first();
            if ($existing) {
                $this->patientIds[] = $existing->id;
                continue;
            }

            $id = $this->db()->table('patients')->insertGetId(array_merge($p, [
                'doctor_id'          => $this->doctorId,
                'from_where_come_id' => $sourceId(),
                'identifier'         => 'P-T02-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'credit_balance'     => 0,
                'creator_id'         => $this->doctorId,
                'updator_id'         => $this->doctorId,
                'public_token'       => (string) Str::uuid(),
                'is_public_profile_enabled' => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]));

            $this->patientIds[] = $id;
            $created++;
        }

        $this->command->info('    ✓ ' . $created . ' patients created (' . (count($patients) - $created) . ' already existed)');
    }

    // ── Cases + Encounter Details ─────────────────────────────────────────────

    private function seedCasesWithEncounters(): void
    {
        $this->command->info('  🩺 Seeding cases + encounter details...');

        $now           = $this->now();
        $completedId   = $this->statusIds['Completed']   ?? array_values($this->statusIds)[2];
        $inProgressId  = $this->statusIds['In Progress'] ?? array_values($this->statusIds)[1];
        $newId         = $this->statusIds['New']         ?? array_values($this->statusIds)[0];

        // Ophthalmology cases data
        $allCases = [
            [
                'category'       => 'Eye Examination',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Comprehensive eye examination. Pupil dilation performed.',
                'price'          => 30000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'            => 'both',
                    'visual_acuity_left'  => '6/6',
                    'visual_acuity_right' => '6/9',
                    'iop_left'            => 13.0,
                    'iop_right'           => 14.5,
                    'refraction_left'     => 'Plano',
                    'refraction_right'    => '-0.50 / -0.25 x 90',
                    'anterior_segment'    => 'Clear cornea bilaterally. Deep anterior chambers. Lens clear.',
                    'posterior_segment'   => 'Normal optic disc C/D 0.3 OU. Flat macula. No peripheral lesions.',
                    'diagnosis'           => 'Normal eye exam. Mild myopia OD. Glasses prescribed.',
                ],
            ],
            [
                'category'       => 'Vision Test',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Refraction and visual acuity assessment. Contact lens fitting.',
                'price'          => 20000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'            => 'both',
                    'visual_acuity_left'  => '6/18',
                    'visual_acuity_right' => '6/12',
                    'refraction_left'     => '-3.50 / -0.75 x 180',
                    'refraction_right'    => '-3.00 / -0.50 x 175',
                    'diagnosis'           => 'Moderate myopia OU. Contact lenses prescribed.',
                ],
            ],
            [
                'category'       => 'IOP Measurement',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Glaucoma follow-up. Goldman applanation tonometry performed.',
                'price'          => 15000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'  => 'both',
                    'iop_left'  => 17.0,
                    'iop_right' => 19.0,
                    'diagnosis' => 'Open-angle glaucoma OU. IOP within target. Continue Timolol 0.5% BD.',
                ],
            ],
            [
                'category'       => 'Fundus Examination',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Diabetic retinopathy annual screening. Wide-field fundus photography obtained.',
                'price'          => 40000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'          => 'both',
                    'visual_acuity_left'  => '6/9',
                    'visual_acuity_right' => '6/12',
                    'iop_left'          => 15.0,
                    'iop_right'         => 16.0,
                    'posterior_segment' => 'OD: microaneurysms and dot haemorrhages in 2 quadrants. No NVD/NVE. OS: clear fundus.',
                    'diagnosis'         => 'Mild non-proliferative diabetic retinopathy OD. Annual review. Optimise glycaemic control.',
                ],
            ],
            [
                'category'       => 'Glaucoma Treatment',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'SLT (Selective Laser Trabeculoplasty) performed OD. Post-laser IOP check scheduled.',
                'price'          => 60000,
                'is_paid'        => 0,
                'status_id'      => $inProgressId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'  => 'right',
                    'iop_right' => 24.0,
                    'diagnosis' => 'POAG OD. IOP uncontrolled on drops. SLT performed. Review in 6 weeks.',
                ],
            ],
            [
                'category'       => 'Intravitreal Injection',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Intravitreal Bevacizumab (Avastin) injection OD for wet AMD. Sterile technique. No complications.',
                'price'          => 250000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'          => 'right',
                    'visual_acuity_right'=> '6/36',
                    'iop_right'         => 14.0,
                    'posterior_segment' => 'Subretinal fluid and CNV membrane at macula OD. OCT confirmed.',
                    'diagnosis'         => 'Wet Age-related macular degeneration OD. 3rd Bevacizumab injection. Improve VA expected.',
                ],
            ],
            [
                'category'       => 'Cataract Surgery',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Phacoemulsification + IOL implant OS under local anaesthesia. Uncomplicated. Pre-op VA 6/60, post-op VA 6/6.',
                'price'          => 800000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'           => 'left',
                    'visual_acuity_left' => '6/6',
                    'iop_left'           => 12.0,
                    'anterior_segment'   => 'Dense nuclear cataract removed. Monofocal IOL +21.5D implanted in bag.',
                    'diagnosis'          => 'Age-related nuclear cataract OS. Successful phaco + IOL. Post-op drops prescribed.',
                ],
            ],
            [
                'category'       => 'LASIK Refractive Surgery',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'LASIK evaluation. Corneal topography and pachymetry performed. Patient suitable for LASIK.',
                'price'          => 1500000,
                'is_paid'        => 0,
                'status_id'      => $newId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'            => 'both',
                    'visual_acuity_left'  => '6/60 unaided → 6/6 corrected',
                    'visual_acuity_right' => '6/60 unaided → 6/6 corrected',
                    'refraction_left'     => '-4.00 / -0.50 x 10',
                    'refraction_right'    => '-3.75 / -0.75 x 170',
                    'anterior_segment'    => 'Cornea clear. Topography normal. Central thickness OD 545µm OS 542µm.',
                    'diagnosis'           => 'High myopia OU. LASIK planned. Consent obtained.',
                ],
            ],
            [
                'category'       => 'Retinal Laser Treatment',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Focal laser photocoagulation for macular oedema OS. 120 burns applied.',
                'price'          => 350000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'           => 'left',
                    'visual_acuity_left' => '6/18',
                    'iop_left'           => 13.5,
                    'posterior_segment'  => 'Clinically significant macular oedema OS. Hard exudates circinate pattern.',
                    'diagnosis'          => 'Diabetic macular oedema OS. Focal laser completed. Review 3 months.',
                ],
            ],
            [
                'category'       => 'Pediatric Eye Exam',
                'tooth_num'      => null,
                'root_stuffing'  => null,
                'notes'          => 'Cycloplegic refraction. Amblyopia patching review. Good compliance reported.',
                'price'          => 25000,
                'is_paid'        => 1,
                'status_id'      => $completedId,
                'encounter_type' => 'ophthalmology',
                'eye_data'       => [
                    'eye_side'           => 'left',
                    'visual_acuity_left' => '6/24 (amblyopic)',
                    'visual_acuity_right'=> '6/6',
                    'refraction_left'    => '+2.50 / -1.00 x 90',
                    'refraction_right'   => '+0.50',
                    'diagnosis'          => 'Amblyopia OS. Anisometropic type. Patching 4h/day continued. Improved from 6/36.',
                ],
            ],
        ];
        $patientCount = count($this->patientIds);
        $casesCreated = 0;
        $billsCreated = 0;

        foreach ($allCases as $idx => $caseData) {
            $patientId  = $this->patientIds[$idx % $patientCount];
            $categoryId = $this->categoryIds[$caseData['category']] ?? array_values($this->categoryIds)[0];

            $caseId = $this->db()->table('cases')->insertGetId([
                'patient_id'        => $patientId,
                'doctor_id'         => $this->doctorId,
                'case_categores_id' => $categoryId,
                'notes'             => $caseData['notes'],
                'status_id'         => $caseData['status_id'],
                'price'             => $caseData['price'],
                'tooth_num'         => $caseData['tooth_num'],
                'root_stuffing'     => $caseData['root_stuffing'],
                'is_paid'           => $caseData['is_paid'],
                'case_date'         => now()->subDays(rand(1, 90))->toDateString(),
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $casesCreated++;

            // Specialty-specific encounter details
            if ($caseData['encounter_type'] === 'dental' && $this->tableExists('dental_encounter_details')) {
                $this->db()->table('dental_encounter_details')->insert([
                    'case_id'       => $caseId,
                    'tooth_num'     => $caseData['tooth_num'],
                    'root_stuffing' => $caseData['root_stuffing'],
                    'extra_data'    => json_encode(['seeded' => true]),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            if ($caseData['encounter_type'] === 'ophthalmology' && $this->tableExists('ophthalmology_encounter_details')) {
                $eye = $caseData['eye_data'] ?? [];
                $this->db()->table('ophthalmology_encounter_details')->insert([
                    'case_id'             => $caseId,
                    'eye_side'            => $eye['eye_side']            ?? null,
                    'visual_acuity_left'  => $eye['visual_acuity_left']  ?? null,
                    'visual_acuity_right' => $eye['visual_acuity_right'] ?? null,
                    'iop_left'            => $eye['iop_left']            ?? null,
                    'iop_right'           => $eye['iop_right']           ?? null,
                    'refraction_left'     => $eye['refraction_left']     ?? null,
                    'refraction_right'    => $eye['refraction_right']    ?? null,
                    'anterior_segment'    => $eye['anterior_segment']    ?? null,
                    'posterior_segment'   => $eye['posterior_segment']   ?? null,
                    'diagnosis'           => $eye['diagnosis']           ?? null,
                    'extra_data'          => json_encode(['seeded' => true]),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
            }

            // Bill for each case
            $this->db()->table('bills')->insert([
                'patient_id'    => $patientId,
                'billable_id'   => $caseId,
                'billable_type' => 'App\\Models\\CaseModel',
                'is_paid'       => $caseData['is_paid'],
                'price'         => $caseData['price'],
                'doctor_id'     => $this->doctorId,
                'use_credit'    => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $billsCreated++;
        }

        $this->command->info('    ✓ ' . $casesCreated . ' cases, ' . $billsCreated . ' bills');
    }

    // ── Reservations ──────────────────────────────────────────────────────────

    private function seedReservations(): void
    {
        $this->command->info('  📆 Seeding reservations...');

        $now          = $this->now();
        $newId        = $this->statusIds['New']         ?? array_values($this->statusIds)[0];
        $completedId  = $this->statusIds['Completed']   ?? array_values($this->statusIds)[2];
        $cancelledId  = $this->statusIds['Cancelled']   ?? array_values($this->statusIds)[3];

        $reservationTypeIds = $this->db()->table('reservation_types')->pluck('id')->toArray();
        $typeId = fn () => $reservationTypeIds ? $reservationTypeIds[array_rand($reservationTypeIds)] : null;

        $slots = [
            ['days' => -10, 'from' => '09:00:00', 'to' => '09:30:00', 'status_id' => $completedId],
            ['days' => -7,  'from' => '10:00:00', 'to' => '10:30:00', 'status_id' => $completedId],
            ['days' => -3,  'from' => '11:00:00', 'to' => '11:30:00', 'status_id' => $cancelledId],
            ['days' => 0,   'from' => '14:00:00', 'to' => '14:30:00', 'status_id' => $newId],
            ['days' => 1,   'from' => '09:30:00', 'to' => '10:00:00', 'status_id' => $newId],
            ['days' => 3,   'from' => '10:30:00', 'to' => '11:00:00', 'status_id' => $newId],
            ['days' => 5,   'from' => '15:00:00', 'to' => '15:30:00', 'status_id' => $newId],
            ['days' => 7,   'from' => '16:00:00', 'to' => '16:30:00', 'status_id' => $newId],
        ];

        $patientCount = count($this->patientIds);
        $created      = 0;

        foreach ($slots as $idx => $slot) {
            $date = now()->addDays($slot['days'])->toDateString();
            $this->db()->table('reservations')->insert([
                'patient_id'             => $this->patientIds[$idx % $patientCount],
                'doctor_id'              => $this->doctorId,
                'status_id'              => $slot['status_id'],
                'reservation_start_date' => $date,
                'reservation_end_date'   => $date,
                'reservation_from_time'  => $slot['from'],
                'reservation_to_time'    => $slot['to'],
                'is_waiting'             => 0,
                'notes'                  => 'Demo reservation #' . ($idx + 1),
                'creator_id'             => $this->secretaryId,
                'updator_id'             => $this->secretaryId,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
            $created++;
        }

        $this->command->info('    ✓ ' . $created . ' reservations');
    }

    // ── Recipes ───────────────────────────────────────────────────────────────

    private function seedRecipes(): void
    {
        $this->command->info('  💊 Seeding recipes...');

        $now = $this->now();

        $prescriptions = [
            'Pred Forte 1% eye drops – 1 drop QID for 1 week then taper. Ciprofloxacin 0.3% eye drops – 1 drop QID for 5 days. Wear sunglasses outdoors.',
            'Timolol 0.5% eye drops – 1 drop BD (morning and evening) OD. Xalatan 0.005% – 1 drop once nightly OS. Check IOP in 4 weeks.',
            'Refresh Tears lubricant eye drops – 1–2 drops every 4 hours OU. Avoid screen time > 30 min without break. Warm compresses BD.',
            'Moxifloxacin 0.5% eye drops – 1 drop 4 times daily for 7 days post-op. Prednisolone 1% – taper over 4 weeks. No rubbing or swimming.',
            'Bevacizumab follow-up: Nil eye drops. Review OCT in 4 weeks. Alert if sudden vision loss or floaters.',
        ];

        $created = 0;
        foreach (array_slice($this->patientIds, 0, 5) as $idx => $patientId) {
            $this->db()->table('recipes')->insert([
                'patient_id' => $patientId,
                'doctors_id' => $this->doctorId,
                'notes'      => $prescriptions[$idx % count($prescriptions)],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created++;
        }

        $this->command->info('    ✓ ' . $created . ' recipes');
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private function tableExists(string $table): bool
    {
        try {
            return $this->db()->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
