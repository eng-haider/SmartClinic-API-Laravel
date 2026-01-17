<?php

namespace Database\Factories;

use App\Models\CaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaseCategory>
 */
class CaseCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CaseCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'order' => fake()->numberBetween(1, 100),
            'clinic_id' => fake()->optional()->uuid(),
            'item_cost' => fake()->numberBetween(0, 10000),
            'parent_case_categories_id' => null,
        ];
    }

    /**
     * Indicate that the category has a parent.
     */
    public function withParent(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_case_categories_id' => $parentId,
        ]);
    }
}
