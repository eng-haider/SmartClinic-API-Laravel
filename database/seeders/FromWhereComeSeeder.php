<?php

namespace Database\Seeders;

use App\Models\FromWhereCome;
use Illuminate\Database\Seeder;

class FromWhereComeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Social Media',
                'name_ar' => 'وسائل التواصل الاجتماعي',
                'description' => 'Patient came through social media advertising',
                'order' => 1,
            ],
            [
                'name' => 'Google Search',
                'name_ar' => 'بحث جوجل',
                'description' => 'Found through Google search',
                'order' => 2,
            ],
            [
                'name' => 'Friend Referral',
                'name_ar' => 'إحالة من صديق',
                'description' => 'Referred by existing patient',
                'order' => 3,
            ],
            [
                'name' => 'Walk-in',
                'name_ar' => 'زيارة مباشرة',
                'description' => 'Direct walk-in patient',
                'order' => 4,
            ],
            [
                'name' => 'Doctor Referral',
                'name_ar' => 'إحالة من طبيب',
                'description' => 'Referred by another doctor',
                'order' => 5,
            ],
            [
                'name' => 'Advertisement',
                'name_ar' => 'إعلان',
                'description' => 'Through advertisement campaign',
                'order' => 6,
            ],
            [
                'name' => 'Website',
                'name_ar' => 'الموقع الإلكتروني',
                'description' => 'From clinic website',
                'order' => 7,
            ],
            [
                'name' => 'Insurance Company',
                'name_ar' => 'شركة التأمين',
                'description' => 'Referred by insurance company',
                'order' => 8,
            ],
            [
                'name' => 'WhatsApp',
                'name_ar' => 'واتساب',
                'description' => 'Contact through WhatsApp',
                'order' => 9,
            ],
            [
                'name' => 'Other',
                'name_ar' => 'أخرى',
                'description' => 'Other sources',
                'order' => 10,
            ],
        ];

        foreach ($sources as $source) {
            FromWhereCome::create($source);
        }

        $this->command->info('✓ From Where Come sources seeded successfully!');
    }
}
