<?php

declare(strict_types=1);

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\EncryptionKey>
 */
class EncryptionKeyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EncryptionKey::class;

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
            'public_key' => $this->generateMockPublicKey(),
            'encrypted_key' => base64_encode($this->faker->randomBytes(1024)),
            'is_active' => true,
        ];
    }

    /**
     * Generate a mock RSA public key for testing.
     */
    private function generateMockPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".
               chunk_split(base64_encode($this->faker->randomBytes(270)), 64).
               '-----END PUBLIC KEY-----';
    }
}
