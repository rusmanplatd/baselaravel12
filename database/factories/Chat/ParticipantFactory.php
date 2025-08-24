<?php

declare(strict_types=1);

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\Participant>
 */
class ParticipantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Participant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'joined_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'left_at' => null,
            'last_read_at' => null,
        ];
    }

    /**
     * Indicate that the participant is an owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
        ]);
    }

    /**
     * Indicate that the participant is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the participant is a member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'member',
        ]);
    }

    /**
     * Indicate that the participant has left the conversation.
     */
    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'left_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the participant has read messages recently.
     */
    public function hasRead(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_read_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }
}
