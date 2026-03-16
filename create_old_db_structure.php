<?php

/**
 * Script to create old database structure for migration
 * 
 * Run: php create_old_db_structure.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "==========================================\n";
echo "  CREATE OLD DATABASE STRUCTURE\n";
echo "==========================================\n\n";

$connection = 'mysql_old';
$dbName = DB::connection($connection)->getDatabaseName();

echo "📋 Database: {$dbName}\n\n";

// SQL statements to create the old database structure
$sql = <<<'SQL'

CREATE TABLE IF NOT EXISTS `clinics` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `rx_img` varchar(255) DEFAULT NULL,
  `whatsapp_message_count` bigint(20) NOT NULL DEFAULT 20,
  `whatsapp_phone` varchar(255) DEFAULT NULL,
  `show_image_case` int(11) NOT NULL DEFAULT 0,
  `whatsapp_template_sid` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `tctate_user_id` int(11) DEFAULT NULL,
  `img_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role_id` bigint(20) DEFAULT NULL,
  `send_msg` int(11) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `master_user_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `phone1` varchar(255) DEFAULT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tctate_token` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `clinics_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `clinics_id` (`clinics_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `patients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(33) DEFAULT NULL,
  `phone1` varchar(255) DEFAULT NULL,
  `systemic_conditions` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `sex` int(11) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `is_scheduled_today` tinyint(4) NOT NULL DEFAULT 0,
  `waitinglist_status_id` tinyint(4) NOT NULL DEFAULT 1,
  `birth_date` date DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `is_sent` int(11) NOT NULL DEFAULT 0,
  `is_deleted` int(11) NOT NULL DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cases_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patients_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `statuses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `status_type_id` bigint(20) UNSIGNED NOT NULL,
  `status_name` varchar(255) NOT NULL,
  `status_name_ar` varchar(255) NOT NULL,
  `status_color` varchar(255) NOT NULL,
  `status_icon` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `status_type` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `status_type_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `case_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cases` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `case_categores_id` bigint(20) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `status_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `price` bigint(20) DEFAULT NULL,
  `upper_right` int(11) DEFAULT NULL,
  `upper_left` int(11) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 1,
  `lower_right` int(11) DEFAULT NULL,
  `lower_left` int(11) DEFAULT NULL,
  `tooth_num` text DEFAULT NULL,
  `root_stuffing` text DEFAULT NULL,
  `is_paid` tinyint(4) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cases_patient_id_foreign` (`patient_id`),
  KEY `cases_case_categores_id_foreign` (`case_categores_id`),
  KEY `cases_status_id_foreign` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CaseDoctor` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `doctors_id` bigint(20) NOT NULL,
  `cases_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `note` text DEFAULT NULL,
  `case_id` bigint(20) UNSIGNED NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `bills` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `billable_id` int(11) NOT NULL,
  `billable_type` varchar(255) NOT NULL,
  `price` bigint(20) NOT NULL,
  `PaymentDate` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `images` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_url` varchar(255) NOT NULL,
  `imageable_id` int(10) UNSIGNED DEFAULT NULL,
  `imageable_type` varchar(255) NOT NULL,
  `descrption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conjugations_categoriesv2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `doctors_id` bigint(20) NOT NULL,
  `clinic_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `conjugationsv3` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `quantity` bigint(20) DEFAULT NULL,
  `conjugations_categories_id` bigint(20) UNSIGNED DEFAULT NULL,
  `clinics_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL DEFAULT current_timestamp(),
  `price` varchar(255) NOT NULL,
  `is_paid` int(11) NOT NULL DEFAULT 0,
  `doctor_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `DoctorPatient` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctors_id` bigint(20) NOT NULL,
  `patients_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SQL;

try {
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            DB::connection($connection)->statement($statement);
            // Extract table name from CREATE TABLE statement
            if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $statement, $matches)) {
                echo "✅ Created table: {$matches[1]}\n";
            }
        }
    }
    
    echo "\n✅ All tables created successfully!\n\n";
    
    // Now insert sample data for testing
    echo "📝 Inserting sample data...\n\n";
    
    // Insert a clinic
    DB::connection($connection)->table('clinics')->insert([
        'id' => 1,
        'name' => 'SmartClinic Demo',
        'address' => 'Baghdad, Iraq',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Inserted clinic: SmartClinic Demo (ID: 1)\n";
    
    // Insert a doctor user (role_id = 5 = clinic_super_doctor)
    DB::connection($connection)->table('users')->insert([
        'id' => 1,
        'name' => 'Dr. Admin',
        'email' => 'admin@clinic.com',
        'phone' => '07701234567',
        'password' => password_hash('12345678', PASSWORD_DEFAULT),
        'role_id' => 5,
        'clinic_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Inserted user: Dr. Admin (ID: 1, role_id: 5 = clinic_super_doctor)\n";
    
    // Insert doctor record
    DB::connection($connection)->table('doctors')->insert([
        'id' => 1,
        'name' => 'Dr. Admin',
        'user_id' => 1,
        'clinics_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Inserted doctor record (ID: 1)\n";
    
    // Insert status type
    DB::connection($connection)->table('status_type')->insert([
        'id' => 1,
        'status_type_name' => 'case_status',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Insert statuses
    $statuses = [
        ['id' => 1, 'status_name' => 'New', 'status_name_ar' => 'جديد', 'status_color' => '#3498db', 'status_icon' => 'new'],
        ['id' => 2, 'status_name' => 'In Progress', 'status_name_ar' => 'قيد التنفيذ', 'status_color' => '#f39c12', 'status_icon' => 'progress'],
        ['id' => 3, 'status_name' => 'Completed', 'status_name_ar' => 'مكتمل', 'status_color' => '#27ae60', 'status_icon' => 'done'],
    ];
    foreach ($statuses as $status) {
        DB::connection($connection)->table('statuses')->insert(array_merge($status, [
            'status_type_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
    echo "✅ Inserted 3 statuses\n";
    
    // Insert case categories
    $categories = [
        ['id' => 1, 'name_ar' => 'حشوة', 'name_en' => 'Filling'],
        ['id' => 2, 'name_ar' => 'قلع', 'name_en' => 'Extraction'],
        ['id' => 3, 'name_ar' => 'تبييض', 'name_en' => 'Whitening'],
        ['id' => 4, 'name_ar' => 'تنظيف', 'name_en' => 'Cleaning'],
    ];
    foreach ($categories as $cat) {
        DB::connection($connection)->table('case_categories')->insert(array_merge($cat, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
    echo "✅ Inserted 4 case categories\n";
    
    // Insert sample patients
    $patients = [
        ['id' => 1, 'name' => 'أحمد محمد', 'age' => 30, 'phone' => '07801111111', 'sex' => 1],
        ['id' => 2, 'name' => 'سارة علي', 'age' => 25, 'phone' => '07802222222', 'sex' => 2],
        ['id' => 3, 'name' => 'محمد حسن', 'age' => 45, 'phone' => '07803333333', 'sex' => 1],
    ];
    foreach ($patients as $patient) {
        DB::connection($connection)->table('patients')->insert(array_merge($patient, [
            'user_id' => 1,
            'doctor_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
    echo "✅ Inserted 3 patients\n";
    
    // Insert sample cases
    $cases = [
        ['id' => 1, 'patient_id' => 1, 'case_categores_id' => 1, 'status_id' => 3, 'price' => 50000, 'notes' => 'حشوة أمامية', 'is_paid' => 1],
        ['id' => 2, 'patient_id' => 1, 'case_categores_id' => 4, 'status_id' => 3, 'price' => 25000, 'notes' => 'تنظيف الأسنان', 'is_paid' => 1],
        ['id' => 3, 'patient_id' => 2, 'case_categores_id' => 3, 'status_id' => 2, 'price' => 150000, 'notes' => 'تبييض كامل', 'is_paid' => 0],
        ['id' => 4, 'patient_id' => 3, 'case_categores_id' => 2, 'status_id' => 1, 'price' => 30000, 'notes' => 'قلع ضرس العقل', 'is_paid' => 0],
    ];
    foreach ($cases as $case) {
        DB::connection($connection)->table('cases')->insert(array_merge($case, [
            'user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        // Also insert CaseDoctor record
        DB::connection($connection)->table('CaseDoctor')->insert([
            'doctors_id' => 1,
            'cases_id' => $case['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    echo "✅ Inserted 4 cases with CaseDoctor records\n";
    
    // Insert sample sessions (notes)
    $sessions = [
        ['case_id' => 1, 'note' => 'تم إزالة التسوس وتطبيق الحشوة', 'date' => now()->subDays(5)],
        ['case_id' => 1, 'note' => 'مراجعة بعد أسبوع - الحالة جيدة', 'date' => now()->subDays(2)],
        ['case_id' => 2, 'note' => 'تنظيف كامل للأسنان', 'date' => now()->subDays(10)],
        ['case_id' => 3, 'note' => 'الجلسة الأولى للتبييض', 'date' => now()->subDays(3)],
    ];
    foreach ($sessions as $session) {
        DB::connection($connection)->table('sessions')->insert(array_merge($session, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
    echo "✅ Inserted 4 sessions (notes)\n";
    
    echo "\n==========================================\n";
    echo "  ✅ OLD DATABASE SETUP COMPLETE!\n";
    echo "==========================================\n\n";
    echo "Now run the migration seeder:\n";
    echo "  php artisan db:seed --class=OldDatabaseMigrationSeeder\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
