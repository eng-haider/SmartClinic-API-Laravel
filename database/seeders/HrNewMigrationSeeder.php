<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class HrNewMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Runs new tenant migrations and seeds the reservation_types table
     * for all existing tenants.
     */
    public function run(): void
    {
        // Run the new tenant migrations
        Artisan::call('tenants:migrate', [
            '--path' => 'database/migrations/tenant/2026_03_04_000001_create_reservation_types_table.php',
            '--force' => true,
        ]);
        $this->command->info('Migration: create_reservation_types_table — done.');

        Artisan::call('tenants:migrate', [
            '--path' => 'database/migrations/tenant/2026_03_04_000002_add_reservation_type_id_to_reservations_table.php',
            '--force' => true,
        ]);
        $this->command->info('Migration: add_reservation_type_id_to_reservations_table — done.');

        // Seed reservation types for all tenants
        Artisan::call('tenants:seed', [
            '--class' => ReservationTypeSeeder::class,
            '--force' => true,
        ]);
        $this->command->info('Seeder: ReservationTypeSeeder — done.');
    }
}
