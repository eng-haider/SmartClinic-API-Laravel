<?php

namespace Database\Seeders;

use App\Models\ReservationType;
use Illuminate\Database\Seeder;

class ReservationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name'        => 'Check-up',
                'name_ar'     => 'فحص',
                'description' => 'Regular examination / check-up appointment',
                'order'       => 1,
            ],
            [
                'name'        => 'Other',
                'name_ar'     => 'أخرى',
                'description' => 'Other type — patient can specify in the note field',
                'order'       => 2,
            ],
        ];

        foreach ($types as $type) {
            ReservationType::firstOrCreate(
                ['name' => $type['name']],
                array_merge($type, ['is_active' => true])
            );
        }
    }
}
