<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use App\Models\Clinic;
use App\Models\FromWhereCome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'age' => fake()->numberBetween(1, 90),
            'doctor_id' => User::factory(),
            'clinics_id' => Clinic::factory(),
            'phone' => fake()->phoneNumber(),
            'systemic_conditions' => fake()->optional()->randomElement([
                'Diabetes',
                'Hypertension',
                'Heart Disease',
                'Asthma',
                'None'
            ]),
            'sex' => fake()->randomElement([1, 2]), // 1=Male, 2=Female
            'address' => fake()->address(),
            'notes' => fake()->optional()->paragraph(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'rx_id' => fake()->optional()->numerify('RX-####'),
            'note' => fake()->optional()->sentence(),
            'from_where_come_id' => FromWhereCome::factory(),
            'identifier' => fake()->optional()->uuid(),
            'credit_balance' => fake()->optional()->numberBetween(0, 100000),
            'credit_balance_add_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the patient is male.
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 1,
        ]);
    }

    /**
     * Indicate that the patient is female.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 2,
        ]);
    }

    /**
     * Indicate that the patient has credit balance.
     */
    public function withCredit(int $amount = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_balance' => $amount,
            'credit_balance_add_at' => now(),
        ]);
    }
}
