<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note>
 */
class NoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Note::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the note is for a specific noteable model.
     */
    public function forNoteable($noteable): static
    {
        return $this->state(fn (array $attributes) => [
            'noteable_id' => $noteable->id,
            'noteable_type' => get_class($noteable),
        ]);
    }
}
