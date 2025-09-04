<?php

namespace Database\Factories\Chat;

use App\Models\Chat\SignalSignedPrekey;
use App\Models\Chat\SignalIdentityKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\SignalSignedPrekey>
 */
class SignalSignedPrekeyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SignalSignedPrekey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key_id' => $this->faker->numberBetween(1, 999999),
            'public_key' => base64_encode(random_bytes(65)), // P-256 public key
            'private_key_encrypted' => base64_encode(random_bytes(48)),
            'signature' => base64_encode(random_bytes(64)), // ECDSA signature
            'generated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_active' => $this->faker->boolean(85), // 85% active
            'quantum_public_key' => base64_encode(random_bytes(1184)), // ML-KEM-768
            'quantum_algorithm' => $this->faker->randomElement(['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024']),
            'is_quantum_capable' => $this->faker->boolean(70),
        ];
    }

    /**
     * Indicate that the signed prekey is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the signed prekey is quantum capable.
     */
    public function quantumCapable(string $algorithm = 'ML-KEM-768'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_capable' => true,
            'quantum_algorithm' => $algorithm,
            'quantum_public_key' => base64_encode(random_bytes($this->getQuantumKeySize($algorithm))),
        ]);
    }

    /**
     * Indicate that the signed prekey is classical only.
     */
    public function classicalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_capable' => false,
            'quantum_algorithm' => null,
            'quantum_public_key' => null,
            'quantum_version' => null,
        ]);
    }

    /**
     * Indicate that the signed prekey is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'generated_at' => $this->faker->dateTimeBetween('-1 year', '-2 months'),
        ]);
    }

    /**
     * Get the key size for different quantum algorithms.
     */
    private function getQuantumKeySize(string $algorithm): int
    {
        return match ($algorithm) {
            'ML-KEM-512' => 800,   // ML-KEM-512 public key size
            'ML-KEM-768' => 1184,  // ML-KEM-768 public key size
            'ML-KEM-1024' => 1568, // ML-KEM-1024 public key size
            default => 1184,       // Default to ML-KEM-768
        };
    }
}