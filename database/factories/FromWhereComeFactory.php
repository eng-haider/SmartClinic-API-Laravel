<?php

namespace Database\Factories;

use App\Models\FromWhereCome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FromWhereCome>
 */
class FromWhereComeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FromWhereCome::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'name_ar' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
            'order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Indicate that the source is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the source is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
