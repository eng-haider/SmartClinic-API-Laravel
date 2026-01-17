<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Patient;
use App\Models\User;
use App\Models\Clinic;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+30 days');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(0, 3) . ' days');
        $fromTime = fake()->time('H:i:s');
        $toTime = date('H:i:s', strtotime($fromTime) + (30 * 60)); // Add 30 minutes

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'clinics_id' => Clinic::factory(),
            'status_id' => Status::factory(),
            'notes' => fake()->optional()->sentence(),
            'reservation_start_date' => $startDate->format('Y-m-d'),
            'reservation_end_date' => $endDate->format('Y-m-d'),
            'reservation_from_time' => $fromTime,
            'reservation_to_time' => $toTime,
            'is_waiting' => fake()->boolean(20),
        ];
    }

    /**
     * Indicate that the reservation is waiting.
     */
    public function waiting(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_waiting' => true,
        ]);
    }

    /**
     * Indicate that the reservation is not waiting.
     */
    public function notWaiting(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_waiting' => false,
        ]);
    }

    /**
     * Indicate that the reservation is for today.
     */
    public function today(): static
    {
        $fromTime = fake()->time('H:i:s');
        $toTime = date('H:i:s', strtotime($fromTime) + (30 * 60));

        return $this->state(fn (array $attributes) => [
            'reservation_start_date' => now()->format('Y-m-d'),
            'reservation_end_date' => now()->format('Y-m-d'),
            'reservation_from_time' => $fromTime,
            'reservation_to_time' => $toTime,
        ]);
    }

    /**
     * Indicate that the reservation is upcoming.
     */
    public function upcoming(): static
    {
        $startDate = fake()->dateTimeBetween('+1 day', '+30 days');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(0, 3) . ' days');
        $fromTime = fake()->time('H:i:s');
        $toTime = date('H:i:s', strtotime($fromTime) + (30 * 60));

        return $this->state(fn (array $attributes) => [
            'reservation_start_date' => $startDate->format('Y-m-d'),
            'reservation_end_date' => $endDate->format('Y-m-d'),
            'reservation_from_time' => $fromTime,
            'reservation_to_time' => $toTime,
        ]);
    }

    /**
     * Indicate that the reservation is in the past.
     */
    public function past(): static
    {
        $startDate = fake()->dateTimeBetween('-30 days', '-1 day');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(0, 3) . ' days');
        $fromTime = fake()->time('H:i:s');
        $toTime = date('H:i:s', strtotime($fromTime) + (30 * 60));

        return $this->state(fn (array $attributes) => [
            'reservation_start_date' => $startDate->format('Y-m-d'),
            'reservation_end_date' => $endDate->format('Y-m-d'),
            'reservation_from_time' => $fromTime,
            'reservation_to_time' => $toTime,
        ]);
    }
}
