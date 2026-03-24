<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Patient;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bill>
 */
class BillFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bill::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creator = User::factory();
        
        return [
            'patient_id' => Patient::factory(),
            'price' => fake()->numberBetween(10000, 500000),
            'clinics_id' => Clinic::factory(),
            'doctor_id' => User::factory(),
            'creator_id' => $creator,
            'updator_id' => fake()->boolean(70) ? $creator : User::factory(),
            'use_credit' => fake()->boolean(15),
        ];
    }

    /**
     * Indicate that the bill is paid (no-op: bills are always paid).
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate that the bill is unpaid (no-op: bills are always paid).
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate that the bill used credit.
     */
    public function usingCredit(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_credit' => true,
        ]);
    }

    /**
     * Indicate that the bill didn't use credit.
     */
    public function notUsingCredit(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_credit' => false,
        ]);
    }

    /**
     * Indicate that the bill is for a specific billable model.
     */
    public function forBillable($billable): static
    {
        return $this->state(fn (array $attributes) => [
            'billable_id' => $billable->id,
            'billable_type' => get_class($billable),
        ]);
    }

    /**
     * Indicate that the bill has a specific price.
     */
    public function withPrice(int $price): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }
}
