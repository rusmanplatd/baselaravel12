<?php

namespace Database\Factories\Chat;

use App\Models\Chat\SignalIdentityKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\SignalIdentityKey>
 */
class SignalIdentityKeyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SignalIdentityKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'registration_id' => $this->faker->numberBetween(1, 16384),
            'public_key' => base64_encode(random_bytes(65)), // Uncompressed P-256 public key
            'private_key_encrypted' => base64_encode(random_bytes(48)), // Encrypted private key
            'key_fingerprint' => hash('sha256', random_bytes(65)),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'quantum_public_key' => base64_encode(random_bytes(1568)), // ML-KEM-768 public key size
            'quantum_algorithm' => $this->faker->randomElement(['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024']),
            'is_quantum_capable' => $this->faker->boolean(70), // 70% quantum capable
            'quantum_version' => $this->faker->randomElement([2, 3]),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the identity key is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the identity key is quantum capable.
     */
    public function quantumCapable(string $algorithm = 'ML-KEM-768'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_capable' => true,
            'quantum_algorithm' => $algorithm,
            'quantum_public_key' => base64_encode(random_bytes($this->getQuantumKeySize($algorithm))),
            'quantum_version' => 3,
        ]);
    }

    /**
     * Indicate that the identity key is classical only.
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
     * Indicate that the identity key uses hybrid encryption.
     */
    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'quantum_public_key' => base64_encode(random_bytes(1568)), // ML-KEM-768 size
            'quantum_version' => 2, // Hybrid version
        ]);
    }

    /**
     * Get random device capabilities.
     */
    private function getRandomDeviceCapabilities(): array
    {
        $allCapabilities = [
            'ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512',
            'HYBRID-RSA4096-MLKEM768',
            'Curve25519', 'P-256',
            'RSA-4096-OAEP', 'RSA-2048-OAEP'
        ];

        $numCapabilities = $this->faker->numberBetween(2, 6);
        return $this->faker->randomElements($allCapabilities, $numCapabilities);
    }

    /**
     * Get quantum capabilities for a specific algorithm.
     */
    private function getQuantumCapabilities(string $algorithm): array
    {
        $base = ['Curve25519', 'P-256'];
        
        switch ($algorithm) {
            case 'ML-KEM-1024':
                return array_merge(['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512'], $base);
            case 'ML-KEM-768':
                return array_merge(['ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'], $base);
            case 'ML-KEM-512':
                return array_merge(['ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'], $base);
            default:
                return $base;
        }
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