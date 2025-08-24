<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Message;
use App\Models\Chat\MessageReadReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\MessageReadReceipt>
 */
class MessageReadReceiptFactory extends Factory
{
    protected $model = MessageReadReceipt::class;

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
            'read_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'is_private' => $this->faker->boolean(30), // 30% chance of being private
            'metadata' => null,
        ];
    }

    /**
     * Mark the read receipt as private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Mark the read receipt as public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => false,
        ]);
    }
}
