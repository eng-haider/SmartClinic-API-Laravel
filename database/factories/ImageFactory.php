<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Image>
 */
class ImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'images/' . $this->faker->uuid() . '.jpg',
            'disk' => 'public',
            'type' => $this->faker->randomElement(['profile', 'document', 'xray', 'scan', 'report']),
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(50000, 5000000), // 50KB to 5MB
            'width' => $this->faker->numberBetween(800, 4000),
            'height' => $this->faker->numberBetween(600, 3000),
            'alt_text' => $this->faker->sentence(),
            'order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the image is a profile picture.
     */
    public function profile(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'profile',
        ]);
    }

    /**
     * Indicate that the image is a document.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'document',
        ]);
    }

    /**
     * Indicate that the image is an X-ray.
     */
    public function xray(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'xray',
        ]);
    }
}
