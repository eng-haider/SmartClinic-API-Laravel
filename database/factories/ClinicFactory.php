<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Clinic>
 */
class ClinicFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Clinic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Dental Clinic',
            'address' => fake()->address(),
            'rx_img' => fake()->optional()->imageUrl(200, 200, 'medical'),
            'whatsapp_template_sid' => fake()->optional()->uuid(),
            'whatsapp_message_count' => fake()->numberBetween(0, 1000),
            'whatsapp_phone' => fake()->phoneNumber(),
            'show_image_case' => fake()->boolean(30),
            'doctor_mony' => fake()->numberBetween(0, 1000000),
            'teeth_v2' => fake()->boolean(50),
            'send_msg' => fake()->boolean(40),
            'show_rx_id' => fake()->boolean(60),
            'logo' => fake()->optional()->imageUrl(100, 100, 'business'),
            'api_whatsapp' => fake()->boolean(30),
        ];
    }

    /**
     * Indicate that the clinic has all features enabled.
     */
    public function withAllFeatures(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_image_case' => true,
            'teeth_v2' => true,
            'send_msg' => true,
            'show_rx_id' => true,
            'api_whatsapp' => true,
        ]);
    }
}
