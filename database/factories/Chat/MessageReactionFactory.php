<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Message;
use App\Models\Chat\MessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\MessageReaction>
 */
class MessageReactionFactory extends Factory
{
    protected $model = MessageReaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'emoji' => $this->faker->randomElement(['👍', '❤️', '😂', '😮', '😢', '😡']),
            'is_anonymous' => $this->faker->boolean(20), // 20% chance of being anonymous
            'metadata' => null,
        ];
    }

    /**
     * Mark the reaction as anonymous.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    /**
     * Mark the reaction as public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => false,
        ]);
    }
}
