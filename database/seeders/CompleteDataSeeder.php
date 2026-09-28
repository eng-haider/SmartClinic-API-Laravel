<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\CaseCategory;
use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\ClinicExpense;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicSetting;
use App\Models\FromWhereCome;
use App\Models\Image;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Reservation;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompleteDataSeeder extends Seeder
{
    private $user;
    private $clinic;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Complete Data Seeder...');
        $this->command->info('');

        // 1. Create the main user and clinic
        $this->createUserAndClinic();

        // 2. Seed lookup tables
        $this->seedStatuses();
        $this->seedCaseCategories();
        $this->seedFromWhereCome();
        $this->seedClinicExpenseCategories();

        // 3. Seed clinic settings
        $this->seedClinicSettings();

        // 4. Seed patients
        $this->seedPatients();

        // 5. Seed medical cases
        $this->seedMedicalCases();

        // 6. Seed reservations
        $this->seedReservations();

        // 7. Seed clinic expenses
        $this->seedClinicExpenses();

        $this->command->info('');
        $this->command->info('✅ Complete Data Seeder finished successfully!');
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📊 Login Credentials:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('   Phone: 07700281899');
        $this->command->info('   Password: 12345678');
        $this->command->info('   Role: clinic_super_doctor');
        $this->command->info('   Clinic: ' . $this->clinic->name);
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    private function createUserAndClinic(): void
    {
        $this->command->info('👤 Creating user and clinic...');

        // Check if user already exists
        $existingUser = User::where('phone', '07700281899')->first();

        if ($existingUser) {
            $this->user = $existingUser;
            $this->clinic = $existingUser->clinic;
            
            $this->command->warn('   ⚠ User already exists: ' . $this->user->name);
            $this->command->warn('   ⚠ Using existing clinic: ' . $this->clinic->name);
            
            return;
        }

        // Create clinic
        $this->clinic = Clinic::create([
            'name' => 'SmartClinic Medical Center',
            'address' => 'Baghdad, Iraq',
            'whatsapp_phone' => '07700281899',
            'whatsapp_message_count' => 0,
            'doctor_mony' => 50,
            'show_image_case' => true,
            'teeth_v2' => true,
            'send_msg' => true,
            'show_rx_id' => true,
            'api_whatsapp' => false,
        ]);

        // Create user
        $this->user = User::create([
            'name' => 'Dr. Haider Al-Temimy',
            'phone' => '07700281899',
            'email' => 'haider@smartclinic.com',
            'password' => Hash::make('12345678'),
            'clinic_id' => $this->clinic->id,
            'is_active' => true,
        ]);

        // Assign role
        $this->user->assignRole('clinic_super_doctor');

        $this->command->info('   ✓ User created: ' . $this->user->name);
        $this->command->info('   ✓ Clinic created: ' . $this->clinic->name);
    }

    private function seedStatuses(): void
    {
        $this->command->info('📋 Seeding statuses...');

        $statuses = [
            ['name_ar' => 'جديد', 'name_en' => 'New', 'color' => '#3B82F6', 'order' => 1],
            ['name_ar' => 'قيد التقدم', 'name_en' => 'In Progress', 'color' => '#F59E0B', 'order' => 2],
            ['name_ar' => 'مكتمل', 'name_en' => 'Completed', 'color' => '#10B981', 'order' => 3],
            ['name_ar' => 'ملغي', 'name_en' => 'Cancelled', 'color' => '#EF4444', 'order' => 4],
            ['name_ar' => 'معلق', 'name_en' => 'On Hold', 'color' => '#6B7280', 'order' => 5],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(['name_en' => $status['name_en']], $status);
        }

        $this->command->info('   ✓ Seeded ' . count($statuses) . ' statuses');
    }

    private function seedCaseCategories(): void
    {
        $this->command->info('🏥 Seeding case categories...');

        $categories = [
            ['name' => 'General Examination', 'order' => 1, 'item_cost' => 25000],
            ['name' => 'Teeth Cleaning', 'order' => 2, 'item_cost' => 50000],
            ['name' => 'Tooth Filling', 'order' => 3, 'item_cost' => 75000],
            ['name' => 'Tooth Extraction', 'order' => 4, 'item_cost' => 50000],
            ['name' => 'Root Canal Treatment', 'order' => 5, 'item_cost' => 150000],
            ['name' => 'Crown Installation', 'order' => 6, 'item_cost' => 200000],
            ['name' => 'Orthodontics', 'order' => 7, 'item_cost' => 1000000],
            ['name' => 'Dental Implant', 'order' => 8, 'item_cost' => 500000],
            ['name' => 'Teeth Whitening', 'order' => 9, 'item_cost' => 100000],
            ['name' => 'Emergency Care', 'order' => 10, 'item_cost' => 75000],
        ];

        foreach ($categories as $category) {
            CaseCategory::firstOrCreate(['name' => $category['name']], $category);
        }

        $this->command->info('   ✓ Seeded ' . count($categories) . ' case categories');
    }

    private function seedFromWhereCome(): void
    {
        $this->command->info('📍 Seeding patient sources...');

        $sources = [
            ['name' => 'Social Media', 'name_ar' => 'وسائل التواصل الاجتماعي', 'order' => 1],
            ['name' => 'Google Search', 'name_ar' => 'بحث جوجل', 'order' => 2],
            ['name' => 'Friend Referral', 'name_ar' => 'إحالة من صديق', 'order' => 3],
            ['name' => 'Walk-in', 'name_ar' => 'زيارة مباشرة', 'order' => 4],
            ['name' => 'Doctor Referral', 'name_ar' => 'إحالة من طبيب', 'order' => 5],
            ['name' => 'Advertisement', 'name_ar' => 'إعلان', 'order' => 6],
            ['name' => 'Website', 'name_ar' => 'الموقع الإلكتروني', 'order' => 7],
            ['name' => 'WhatsApp', 'name_ar' => 'واتساب', 'order' => 8],
            ['name' => 'Other', 'name_ar' => 'أخرى', 'order' => 9],
        ];

        foreach ($sources as $source) {
            FromWhereCome::firstOrCreate(['name' => $source['name']], $source);
        }

        $this->command->info('   ✓ Seeded ' . count($sources) . ' patient sources');
    }

    private function seedClinicExpenseCategories(): void
    {
        $this->command->info('💰 Seeding expense categories...');

        $categories = [
            ['name' => 'Rent', 'description' => 'Monthly clinic rent'],
            ['name' => 'Utilities', 'description' => 'Electricity, water, internet'],
            ['name' => 'Salaries', 'description' => 'Staff salaries'],
            ['name' => 'Medical Supplies', 'description' => 'Medical equipment and supplies'],
            ['name' => 'Maintenance', 'description' => 'Equipment maintenance'],
            ['name' => 'Marketing', 'description' => 'Advertising and marketing'],
            ['name' => 'Other', 'description' => 'Miscellaneous expenses'],
        ];

        foreach ($categories as $category) {
            ClinicExpenseCategory::firstOrCreate(
                ['name' => $category['name']],
                array_merge($category, [
                    'is_active' => true,
                    'creator_id' => $this->user->id,
                    'updator_id' => $this->user->id,
                ])
            );
        }

        $this->command->info('   ✓ Seeded ' . count($categories) . ' expense categories');
    }

    private function seedClinicSettings(): void
    {
        $this->command->info('⚙️  Seeding clinic settings...');

        $settings = [
            ['setting_key' => 'clinic_reg_num', 'setting_value' => 'SC-2026-001', 'setting_type' => 'string', 'description' => 'Clinic registration number'],
            ['setting_key' => 'working_hours', 'setting_value' => '9:00 AM - 6:00 PM', 'setting_type' => 'string', 'description' => 'Clinic working hours'],
            ['setting_key' => 'appointment_duration', 'setting_value' => '30', 'setting_type' => 'integer', 'description' => 'Default appointment duration in minutes'],
            ['setting_key' => 'currency', 'setting_value' => 'IQD', 'setting_type' => 'string', 'description' => 'Currency used'],
            ['setting_key' => 'tax_rate', 'setting_value' => '0', 'setting_type' => 'decimal', 'description' => 'Tax rate percentage'],
            ['setting_key' => 'paid_at_secretary', 'setting_value' => '1', 'setting_type' => 'boolean', 'description' => 'Enable payment at secretary'],
            ['setting_key' => 'doctor_show_all_patient', 'setting_value' => '1', 'setting_type' => 'boolean', 'description' => 'Doctors can view all patients'],
            ['setting_key' => 'enable_sms_notifications', 'setting_value' => '1', 'setting_type' => 'boolean', 'description' => 'Enable SMS notifications'],
            ['setting_key' => 'enable_email_notifications', 'setting_value' => '1', 'setting_type' => 'boolean', 'description' => 'Enable email notifications'],
        ];

        foreach ($settings as $setting) {
            ClinicSetting::firstOrCreate(
                ['clinic_id' => $this->clinic->id, 'setting_key' => $setting['setting_key']],
                array_merge($setting, ['clinic_id' => $this->clinic->id, 'is_active' => true])
            );
        }

        $this->command->info('   ✓ Seeded ' . count($settings) . ' clinic settings');
    }

    private function seedPatients(): void
    {
        $this->command->info('👥 Seeding patients...');

        $fromWhereComes = FromWhereCome::all();
        
        $patients = [
            [
                'name' => 'Ahmed Mohammed',
                'age' => 35,
                'phone' => '07701234567',
                'sex' => 1,
                'address' => 'Baghdad, Al-Karrada',
                'birth_date' => '1989-05-15',
                'systemic_conditions' => 'None',
                'notes' => 'Regular patient, good dental hygiene',
            ],
            [
                'name' => 'Fatima Hassan',
                'age' => 28,
                'phone' => '07702345678',
                'sex' => 2,
                'address' => 'Baghdad, Al-Mansour',
                'birth_date' => '1996-08-22',
                'systemic_conditions' => 'Diabetes Type 2',
                'notes' => 'Sensitive teeth, prefers gentle treatment',
            ],
            [
                'name' => 'Omar Ali',
                'age' => 42,
                'phone' => '07703456789',
                'sex' => 1,
                'address' => 'Baghdad, Al-Jadriya',
                'birth_date' => '1982-03-10',
                'systemic_conditions' => 'High blood pressure',
                'notes' => 'Requires antibiotic premedication',
            ],
            [
                'name' => 'Zahra Karim',
                'age' => 25,
                'phone' => '07704567890',
                'sex' => 2,
                'address' => 'Baghdad, Al-Amiriya',
                'birth_date' => '1999-11-30',
                'systemic_conditions' => 'None',
                'notes' => 'First time patient, nervous about dental procedures',
            ],
            [
                'name' => 'Hussein Jabbar',
                'age' => 50,
                'phone' => '07705678901',
                'sex' => 1,
                'address' => 'Baghdad, Al-Karkh',
                'birth_date' => '1974-07-18',
                'systemic_conditions' => 'Asthma',
                'notes' => 'Multiple missing teeth, needs extensive work',
            ],
        ];

        $createdCount = 0;
        foreach ($patients as $index => $patientData) {
            // Check if patient already exists by phone
            $existingPatient = Patient::where('phone', $patientData['phone'])->first();
            
            if ($existingPatient) {
                continue;
            }

            $patient = Patient::create(array_merge($patientData, [
                'doctor_id' => $this->user->id,
                'clinics_id' => $this->clinic->id,
                'from_where_come_id' => $fromWhereComes->random()->id,
                'identifier' => 'P-2026-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'credit_balance' => 0,
                'creator_id' => $this->user->id,
                'updator_id' => $this->user->id,
            ]));

            // Add a note for each patient using polymorphic relationship
            Note::create([
                'noteable_id' => $patient->id,
                'noteable_type' => Patient::class,
                'content' => 'Initial consultation completed. Patient history recorded.',
                'created_by' => $this->user->id,
            ]);
            
            $createdCount++;
        }

        if ($createdCount > 0) {
            $this->command->info('   ✓ Seeded ' . $createdCount . ' new patients with notes');
        } else {
            $this->command->warn('   ⚠ All patients already exist, skipped patient creation');
        }
    }

    private function seedMedicalCases(): void
    {
        $this->command->info('🏥 Seeding medical cases...');

        $patients = Patient::where('clinics_id', $this->clinic->id)->get();
        $categories = CaseCategory::all();
        $statuses = Status::all();

        $casesCreated = 0;

        foreach ($patients->take(3) as $patient) {
            // Create 1-2 cases per patient
            $numCases = rand(1, 2);

            for ($i = 0; $i < $numCases; $i++) {
                $category = $categories->random();
                $status = $statuses->random();

                $case = CaseModel::create([
                    'doctor_id' => $this->user->id,
                    'clinic_id' => $this->clinic->id,
                    'patient_id' => $patient->id,
                    'case_categores_id' => $category->id,
                    'status_id' => $status->id,
                    'tooth_num' => (string)rand(11, 48),
                    'notes' => 'Treatment for ' . $category->name,
                    'price' => $category->item_cost,
                    'is_paid' => (bool)rand(0, 1),
                ]);

                // Create a recipe for each case
                $recipe = Recipe::create([
                    'patient_id' => $patient->id,
                    'doctors_id' => $this->user->id,
                    'notes' => 'Standard post-treatment care: Amoxicillin 500mg (1 capsule every 8 hours for 7 days), Ibuprofen 400mg (as needed for pain), Chlorhexidine mouthwash (rinse twice daily)',
                ]);

                // Create a bill for each case
                Bill::create([
                    'patient_id' => $patient->id,
                    'billable_id' => $case->id,
                    'billable_type' => CaseModel::class,
                    'clinics_id' => $this->clinic->id,
                    'doctor_id' => $this->user->id,
                    'price' => $category->item_cost,
                    'is_paid' => (bool)rand(0, 1),
                    'use_credit' => false,
                    'creator_id' => $this->user->id,
                    'updator_id' => $this->user->id,
                ]);

                // Add case notes using polymorphic relationship
                Note::create([
                    'noteable_id' => $case->id,
                    'noteable_type' => CaseModel::class,
                    'content' => 'Case created and treatment plan discussed with patient.',
                    'created_by' => $this->user->id,
                ]);

                $casesCreated++;
            }
        }

        $this->command->info('   ✓ Seeded ' . $casesCreated . ' medical cases with recipes and bills');
    }

    private function seedReservations(): void
    {
        $this->command->info('📅 Seeding reservations...');

        $patients = Patient::where('clinics_id', $this->clinic->id)->get();
        $statuses = Status::all();

        $reservations = [];

        foreach ($patients->take(4) as $index => $patient) {
            $startDate = now()->addDays($index + 1);
            $endDate = $startDate->copy();

            Reservation::create([
                'patient_id' => $patient->id,
                'doctor_id' => $this->user->id,
                'clinics_id' => $this->clinic->id,
                'status_id' => $statuses->where('name_en', 'New')->first()->id,
                'reservation_start_date' => $startDate->format('Y-m-d'),
                'reservation_end_date' => $endDate->format('Y-m-d'),
                'reservation_from_time' => $startDate->format('H:i:s'),
                'reservation_to_time' => $startDate->copy()->addMinutes(30)->format('H:i:s'),
                'notes' => 'Regular checkup and follow-up. Patient requested morning appointment.',
                'is_waiting' => false,
                'creator_id' => $this->user->id,
                'updator_id' => $this->user->id,
            ]);
        }

        $this->command->info('   ✓ Seeded 4 reservations');
    }

    private function seedClinicExpenses(): void
    {
        $this->command->info('💸 Seeding clinic expenses...');

        $categories = ClinicExpenseCategory::all();

        $expenses = [
            ['category' => 'Rent', 'name' => 'Monthly clinic rent - January 2026', 'price' => 500000, 'quantity' => 1],
            ['category' => 'Utilities', 'name' => 'Electricity and water bills', 'price' => 150000, 'quantity' => 1],
            ['category' => 'Medical Supplies', 'name' => 'Dental instruments and materials', 'price' => 300000, 'quantity' => 1],
            ['category' => 'Salaries', 'name' => 'Staff salaries for January', 'price' => 1000000, 'quantity' => 1],
            ['category' => 'Marketing', 'name' => 'Social media advertising campaign', 'price' => 200000, 'quantity' => 1],
        ];

        foreach ($expenses as $expenseData) {
            $category = $categories->where('name', $expenseData['category'])->first();

            if ($category) {
                ClinicExpense::create([
                    'clinic_expense_category_id' => $category->id,
                    'name' => $expenseData['name'],
                    'quantity' => $expenseData['quantity'],
                    'price' => $expenseData['price'],
                    'date' => now()->subDays(rand(1, 15)),
                    'is_paid' => true,
                    'doctor_id' => $this->user->id,
                    'creator_id' => $this->user->id,
                    'updator_id' => $this->user->id,
                ]);
            }
        }

        $this->command->info('   ✓ Seeded ' . count($expenses) . ' clinic expenses');
    }
}
