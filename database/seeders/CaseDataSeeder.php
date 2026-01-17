<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Models\CaseCategory;
use Illuminate\Database\Seeder;

class CaseDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Statuses
        $statuses = [
            [
                'name_ar' => 'جديد',
                'name_en' => 'New',
                'color' => '#3B82F6',
                'order' => 1,
            ],
            [
                'name_ar' => 'قيد التقدم',
                'name_en' => 'In Progress',
                'color' => '#F59E0B',
                'order' => 2,
            ],
            [
                'name_ar' => 'مكتمل',
                'name_en' => 'Completed',
                'color' => '#10B981',
                'order' => 3,
            ],
            [
                'name_ar' => 'ملغي',
                'name_en' => 'Cancelled',
                'color' => '#EF4444',
                'order' => 4,
            ],
            [
                'name_ar' => 'معلق',
                'name_en' => 'On Hold',
                'color' => '#6B7280',
                'order' => 5,
            ],
        ];

        foreach ($statuses as $status) {
            Status::create($status);
        }

        // Seed Case Categories
        $categories = [
            [
                'name_ar' => 'فحص عام',
                'name_en' => 'General Examination',
                'order' => 1,
                'item_cost' => 5000,
            ],
            [
                'name_ar' => 'تنظيف الأسنان',
                'name_en' => 'Teeth Cleaning',
                'order' => 2,
                'item_cost' => 10000,
            ],
            [
                'name_ar' => 'حشو الأسنان',
                'name_en' => 'Tooth Filling',
                'order' => 3,
                'item_cost' => 15000,
            ],
            [
                'name_ar' => 'خلع الأسنان',
                'name_en' => 'Tooth Extraction',
                'order' => 4,
                'item_cost' => 20000,
            ],
            [
                'name_ar' => 'علاج الجذور',
                'name_en' => 'Root Canal Treatment',
                'order' => 5,
                'item_cost' => 50000,
            ],
            [
                'name_ar' => 'تركيب التاج',
                'name_en' => 'Crown Installation',
                'order' => 6,
                'item_cost' => 80000,
            ],
            [
                'name_ar' => 'تقويم الأسنان',
                'name_en' => 'Orthodontics',
                'order' => 7,
                'item_cost' => 100000,
            ],
            [
                'name_ar' => 'زراعة الأسنان',
                'name_en' => 'Dental Implant',
                'order' => 8,
                'item_cost' => 150000,
            ],
            [
                'name_ar' => 'تبييض الأسنان',
                'name_en' => 'Teeth Whitening',
                'order' => 9,
                'item_cost' => 25000,
            ],
            [
                'name_ar' => 'جراحة الفم',
                'name_en' => 'Oral Surgery',
                'order' => 10,
                'item_cost' => 60000,
            ],
        ];

        foreach ($categories as $category) {
            CaseCategory::create($category);
        }

        $this->command->info('✓ Statuses and Case Categories seeded successfully!');
    }
}
