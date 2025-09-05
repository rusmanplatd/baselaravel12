<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['text', 'file', 'image', 'audio', 'voice', 'video']);
        
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'type' => $type,
            'encrypted_content' => $this->getEncryptedContentForType($type),
            'content_hash' => $this->faker->sha256(),
            'content_hmac' => $this->faker->sha256(),
            'status' => $this->faker->randomElement(['sent', 'delivered', 'read']),
            'message_priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'metadata' => $this->faker->optional()->passthrough([
                'mentions' => [],
                'hashtags' => [],
                'links' => [],
            ]),
            'is_edited' => false,
            'scheduled_at' => null,
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'encrypted_content' => base64_encode($this->faker->sentence()),
            'file_path' => null,
            'file_name' => null,
            'file_mime_type' => null,
            'file_size' => null,
            'voice_duration_seconds' => null,
        ]);
    }

    public function voice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'voice',
            'file_path' => 'voice_messages/' . $this->faker->uuid() . '.mp3',
            'file_name' => $this->faker->uuid() . '.mp3',
            'file_mime_type' => 'audio/mp3',
            'file_size' => $this->faker->numberBetween(50000, 2000000),
            'voice_duration_seconds' => $this->faker->numberBetween(3, 180),
            'voice_transcript' => $this->faker->optional()->sentence(),
            'encrypted_voice_transcript' => $this->faker->optional()->sha256(),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'file_path' => 'files/' . $this->faker->uuid() . '.pdf',
            'file_name' => $this->faker->word() . '.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(10000, 10000000),
            'voice_duration_seconds' => null,
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'file_path' => 'images/' . $this->faker->uuid() . '.jpg',
            'file_name' => $this->faker->word() . '.jpg',
            'file_mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'voice_duration_seconds' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('+1 hour', '+7 days'),
            'status' => 'scheduled',
        ]);
    }

    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
            'edited_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'reply_to_id' => Message::factory(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_priority' => 'high',
        ]);
    }

    public function withMentions(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'mentions' => [
                    ['user_id' => $this->faker->uuid(), 'username' => $this->faker->userName()],
                ],
            ]),
        ]);
    }

    private function getEncryptedContentForType(string $type): string
    {
        return match($type) {
            'text' => base64_encode($this->faker->sentence()),
            'voice' => base64_encode('encrypted_voice_content'),
            'file', 'image', 'audio', 'video' => base64_encode('encrypted_file_content'),
            default => base64_encode($this->faker->sentence()),
        };
    }
}