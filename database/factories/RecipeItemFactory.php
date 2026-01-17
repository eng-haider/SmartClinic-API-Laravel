<?php

namespace Database\Factories;

use App\Models\RecipeItem;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecipeItem>
 */
class RecipeItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RecipeItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $medications = [
            'Amoxicillin 500mg',
            'Ibuprofen 400mg',
            'Paracetamol 500mg',
            'Aspirin 100mg',
            'Metronidazole 400mg',
            'Ciprofloxacin 500mg',
            'Chlorhexidine Mouthwash',
            'Dexamethasone 4mg',
            'Omeprazole 20mg',
            'Diclofenac 50mg',
            'Vitamin B Complex',
            'Calcium Supplement',
            'Fluoride Gel',
            'Lidocaine Gel 2%',
            'Benzocaine Oral Gel',
        ];

        return [
            'name' => fake()->randomElement($medications),
            'doctors_id' => User::factory(),
            'clinics_id' => Clinic::factory(),
        ];
    }

    /**
     * Indicate that the recipe item has no doctor.
     */
    public function withoutDoctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'doctors_id' => null,
        ]);
    }

    /**
     * Indicate that the recipe item has no clinic.
     */
    public function withoutClinic(): static
    {
        return $this->state(fn (array $attributes) => [
            'clinics_id' => null,
        ]);
    }

    /**
     * Indicate that the recipe item is for a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
