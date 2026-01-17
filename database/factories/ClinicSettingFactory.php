<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicSetting>
 */
class ClinicSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ClinicSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['string', 'boolean', 'integer', 'json'];
        $type = fake()->randomElement($types);
        
        $value = match ($type) {
            'boolean' => fake()->boolean() ? '1' : '0',
            'integer' => (string) fake()->numberBetween(0, 100),
            'json' => json_encode(['key' => fake()->word(), 'value' => fake()->word()]),
            default => fake()->sentence(),
        };

        return [
            'clinic_id' => Clinic::factory(),
            'setting_key' => fake()->unique()->slug(2),
            'setting_value' => $value,
            'setting_type' => $type,
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }

    /**
     * Indicate that the setting is a boolean type.
     */
    public function boolean(bool $value = true): static
    {
        return $this->state(fn (array $attributes) => [
            'setting_type' => 'boolean',
            'setting_value' => $value ? '1' : '0',
        ]);
    }

    /**
     * Indicate that the setting is an integer type.
     */
    public function integer(int $value = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'setting_type' => 'integer',
            'setting_value' => (string) $value,
        ]);
    }

    /**
     * Indicate that the setting is a json type.
     */
    public function json(array $value = []): static
    {
        return $this->state(fn (array $attributes) => [
            'setting_type' => 'json',
            'setting_value' => json_encode($value),
        ]);
    }
}
