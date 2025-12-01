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
            'message_type' => $type,
            'encrypted_content' => $this->getEncryptedContentForType($type),
            'content_hash' => $this->faker->sha256(),
            'encryption_algorithm' => 'signal',
            'encryption_version' => 1,
            'is_edited' => false,
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'text',
            'encrypted_content' => base64_encode($this->faker->sentence()),
        ]);
    }

    public function voice(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'voice',
            'encrypted_content' => base64_encode('encrypted_voice_content'),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'file',
            'encrypted_content' => base64_encode('encrypted_file_content'),
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'image',
            'encrypted_content' => base64_encode('encrypted_image_content'),
        ]);
    }

    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
        ]);
    }

    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'reply_to_id' => Message::factory(),
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
