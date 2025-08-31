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
        // Create dummy encrypted content for testing
        $content = $this->faker->sentence();
        $encryptedContent = json_encode([
            'data' => base64_encode($content),
            'iv' => base64_encode(random_bytes(16)),
            'hmac' => base64_encode(random_bytes(32)),
            'auth_data' => base64_encode(random_bytes(16)),
            'timestamp' => now()->timestamp,
            'nonce' => base64_encode(random_bytes(12))
        ]);

        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'encrypted_content' => $encryptedContent,
            'content_hash' => hash('sha256', $content),
            'content_hmac' => base64_encode(random_bytes(32)),
            'type' => 'text',
            'reply_to_id' => null,
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'file_mime_type' => null,
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
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'file_mime_type' => null,
        ]);
    }

    /**
     * Indicate that the message is a file message.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'file_name' => $this->faker->word().'.pdf',
            'file_path' => 'chat-files/'.$this->faker->ulid().'.encrypted',
            'file_size' => $this->faker->numberBetween(1024, 1024 * 1024), // 1KB to 1MB
            'file_mime_type' => 'application/pdf',
            'file_iv' => base64_encode(random_bytes(16)),
            'file_tag' => base64_encode(random_bytes(16)),
        ]);
    }

    /**
     * Indicate that the message is an image message.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'file_name' => $this->faker->word().'.jpg',
            'file_path' => 'chat-files/'.$this->faker->ulid().'.encrypted',
            'file_size' => $this->faker->numberBetween(1024 * 10, 1024 * 1024 * 5), // 10KB to 5MB
            'file_mime_type' => 'image/jpeg',
            'file_iv' => base64_encode(random_bytes(16)),
            'file_tag' => base64_encode(random_bytes(16)),
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
