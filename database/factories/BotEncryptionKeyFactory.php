<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\Chat\BotEncryptionKey;
use App\Models\Chat\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class BotEncryptionKeyFactory extends Factory
{
    protected $model = BotEncryptionKey::class;

    public function definition(): array
    {
        $algorithm = $this->faker->randomElement(['RSA-4096-OAEP', 'ML-KEM-768', 'HYBRID-RSA4096-MLKEM768']);
        
        return [
            'bot_id' => Bot::factory(),
            'conversation_id' => Conversation::factory(),
            'key_type' => $this->faker->randomElement(['primary', 'backup', 'rotated']),
            'algorithm' => $algorithm,
            'public_key' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{344}') . '=='), // Mock RSA public key
            'encrypted_private_key' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{1024}') . '=='),
            'key_pair_id' => 'bot_key_' . $this->faker->unique()->uuid(),
            'version' => $this->getVersionForAlgorithm($algorithm),
            'is_active' => true,
            'expires_at' => $this->faker->optional()->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    public function quantum(): static
    {
        return $this->state(fn (array $attributes) => [
            'algorithm' => 'ML-KEM-768',
            'version' => 3,
            'public_key' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{1024}') . '=='), // Mock ML-KEM key
        ]);
    }

    public function rsa(): static
    {
        return $this->state(fn (array $attributes) => [
            'algorithm' => 'RSA-4096-OAEP',
            'version' => 2,
            'public_key' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{344}') . '=='),
        ]);
    }

    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'version' => 3,
            'public_key' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{1368}') . '=='), // Combined key
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'key_type' => 'primary',
        ]);
    }

    public function backup(): static
    {
        return $this->state(fn (array $attributes) => [
            'key_type' => 'backup',
            'is_active' => false,
        ]);
    }

    private function getVersionForAlgorithm(string $algorithm): int
    {
        return match($algorithm) {
            'RSA-4096-OAEP' => 2,
            'ML-KEM-768', 'HYBRID-RSA4096-MLKEM768' => 3,
            default => 1,
        };
    }
}