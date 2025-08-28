<?php

declare(strict_types=1);

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'content' => $this->faker->sentence(),
            'type' => 'text',
            'reply_to_id' => null,
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'mime_type' => null,
            'encryption_iv' => null,
            'encryption_tag' => null,
            'edited_at' => null,
        ];
    }

    /**
     * Indicate that the message is a text message.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'content' => $this->faker->sentence(),
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'mime_type' => null,
        ]);
    }

    /**
     * Indicate that the message is a file message.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'content' => null,
            'file_name' => $this->faker->word().'.pdf',
            'file_path' => 'chat-files/'.$this->faker->ulid().'.encrypted',
            'file_size' => $this->faker->numberBetween(1024, 1024 * 1024), // 1KB to 1MB
            'mime_type' => 'application/pdf',
            'encryption_iv' => base64_encode($this->faker->randomBytes(16)),
            'encryption_tag' => base64_encode($this->faker->randomBytes(16)),
        ]);
    }

    /**
     * Indicate that the message is an image message.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'content' => null,
            'file_name' => $this->faker->word().'.jpg',
            'file_path' => 'chat-files/'.$this->faker->ulid().'.encrypted',
            'file_size' => $this->faker->numberBetween(1024 * 10, 1024 * 1024 * 5), // 10KB to 5MB
            'mime_type' => 'image/jpeg',
            'encryption_iv' => base64_encode($this->faker->randomBytes(16)),
            'encryption_tag' => base64_encode($this->faker->randomBytes(16)),
        ]);
    }

    /**
     * Indicate that the message is a reply to another message.
     */
    public function reply(?string $messageId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'reply_to_id' => $messageId ?? Message::factory(),
        ]);
    }

    /**
     * Indicate that the message has been edited.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'edited_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
