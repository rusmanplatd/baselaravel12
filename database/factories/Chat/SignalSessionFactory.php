<?php

namespace Database\Factories\Chat;

use App\Models\Chat\SignalSession;
use App\Models\Chat\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\SignalSession>
 */
class SignalSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SignalSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $localUser = User::factory()->create();
        $remoteUser = User::factory()->create();
        
        return [
            'session_id' => 'session_' . $this->faker->uuid(),
            'conversation_id' => Conversation::factory(),
            'local_user_id' => $localUser->id,
            'remote_user_id' => $remoteUser->id,
            'local_registration_id' => $this->faker->numberBetween(1, 16384),
            'remote_registration_id' => $this->faker->numberBetween(1, 16384),
            'remote_identity_key' => base64_encode(random_bytes(65)),
            'session_state_encrypted' => base64_encode(random_bytes(256)),
            'is_active' => $this->faker->boolean(85), // 85% active
            'verification_status' => $this->faker->randomElement(['unverified', 'verified', 'trusted']),
            'protocol_version' => $this->faker->randomElement(['2.0', '3.0']),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'messages_sent' => $this->faker->numberBetween(0, 1000),
            'messages_received' => $this->faker->numberBetween(0, 1000),
            'key_rotations' => $this->faker->numberBetween(0, 20),
            'quantum_keys_encrypted' => base64_encode(random_bytes(128)),
            'remote_quantum_key' => base64_encode(random_bytes(1184)),
            'quantum_algorithm' => $this->faker->randomElement(['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024']),
            'is_quantum_resistant' => $this->faker->boolean(70),
            'quantum_version' => $this->faker->randomElement([2, 3]),
        ];
    }

    /**
     * Indicate that the session is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Indicate that the session is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'verified',
            'security_score' => $this->faker->numberBetween(85, 100),
        ]);
    }

    /**
     * Indicate that the session is quantum resistant.
     */
    public function quantumResistant(string $algorithm = 'ML-KEM-768'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_resistant' => true,
            'quantum_algorithm' => $algorithm,
            'quantum_version' => 3,
            'protocol_version' => '3.0',
            'security_score' => $this->faker->numberBetween(85, 100),
            'remote_quantum_key' => base64_encode(random_bytes($this->getQuantumKeySize($algorithm))),
        ]);
    }

    /**
     * Indicate that the session is classical only.
     */
    public function classicalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_resistant' => false,
            'quantum_algorithm' => null,
            'quantum_version' => null,
            'remote_quantum_key' => null,
            'protocol_version' => '2.0',
        ]);
    }

    /**
     * Indicate that the session is compromised.
     */
    public function compromised(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'compromised',
            'is_active' => false,
            'security_score' => $this->faker->numberBetween(0, 40),
        ]);
    }

    /**
     * Indicate that the session is for a group conversation.
     */
    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_type' => 'group',
        ]);
    }

    /**
     * Indicate that the session has high activity.
     */
    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'messages_sent' => $this->faker->numberBetween(500, 2000),
            'messages_received' => $this->faker->numberBetween(500, 2000),
            'key_rotations' => $this->faker->numberBetween(10, 50),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Indicate that the session is old/inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'last_activity_at' => $this->faker->dateTimeBetween('-3 months', '-1 month'),
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