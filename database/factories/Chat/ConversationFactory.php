<?php

declare(strict_types=1);

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->optional()->words(3, true),
            'type' => $this->faker->randomElement(['direct', 'group']),
            'description' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the conversation is a direct conversation.
     */
    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'direct',
            'name' => null,
            'description' => null,
        ]);
    }

    /**
     * Indicate that the conversation is a group conversation.
     */
    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'group',
            'name' => $this->faker->words(3, true),
        ]);
    }
}
