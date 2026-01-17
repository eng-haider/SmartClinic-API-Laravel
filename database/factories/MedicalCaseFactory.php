<?php

namespace Database\Factories;

use App\Models\Case;
use App\Models\Patient;
use App\Models\User;
use App\Models\CaseCategory;
use App\Models\Status;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Case>
 */
class CaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Case::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'clinic_id' => Clinic::factory(),
            'case_categores_id' => CaseCategory::factory(),
            'notes' => fake()->optional()->paragraph(),
            'status_id' => Status::factory(),
            'price' => fake()->numberBetween(1000, 50000),
            'tooth_num' => fake()->optional()->numberBetween(1, 32),
            'root_stuffing' => fake()->optional()->words(3, true),
            'is_paid' => fake()->boolean(30),
        ];
    }

    /**
     * Indicate that the case is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => true,
        ]);
    }

    /**
     * Indicate that the case is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
        ]);
    }
}
