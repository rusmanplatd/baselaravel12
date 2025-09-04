<?php

namespace Database\Factories\Chat;

use App\Models\Chat\SignalMessage;
use App\Models\Chat\Conversation;
use App\Models\Chat\SignalSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\SignalMessage>
 */
class SignalMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SignalMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => 'msg_' . $this->faker->uuid(),
            'conversation_id' => Conversation::factory(),
            'session_id' => SignalSession::factory(),
            'sender_user_id' => User::factory(),
            'recipient_user_id' => User::factory(),
            'message_type' => $this->faker->randomElement(['prekey', 'normal']),
            'protocol_version' => $this->faker->randomElement([2, 3]),
            'registration_id' => $this->faker->numberBetween(1, 16384),
            'prekey_id' => $this->faker->optional()->numberBetween(1, 999999),
            'signed_prekey_id' => $this->faker->optional()->numberBetween(1, 999999),
            'base_key' => base64_encode($this->faker->randomBytes(32)),
            'identity_key' => base64_encode($this->faker->randomBytes(65)),
            'ratchet_message' => $this->generateRatchetMessage(),
            'delivery_options' => ['forward_secrecy' => true, 'break_in_recovery' => true],
            'delivery_status' => $this->faker->randomElement(['pending', 'delivered', 'failed']),
            'sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'delivered_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'quantum_ciphertext' => base64_encode($this->faker->randomBytes(256)),
            'quantum_algorithm' => $this->faker->randomElement(['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024']),
            'is_quantum_resistant' => $this->faker->boolean(70),
            'quantum_version' => $this->faker->randomElement([2, 3]),
            'quantum_key_id' => 'qkey_' . $this->faker->uuid(),
        ];
    }

    /**
     * Indicate that the message is a prekey message.
     */
    public function prekey(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'prekey',
            'prekey_id' => $this->faker->numberBetween(1, 999999),
            'signed_prekey_id' => $this->faker->numberBetween(1, 999999),
        ]);
    }

    /**
     * Indicate that the message is a normal message.
     */
    public function normal(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'normal',
            'prekey_id' => null,
            'signed_prekey_id' => null,
        ]);
    }

    /**
     * Indicate that the message is quantum resistant.
     */
    public function quantumResistant(string $algorithm = 'ML-KEM-768'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_resistant' => true,
            'quantum_algorithm' => $algorithm,
            'quantum_version' => 3,
            'protocol_version' => 3,
            'quantum_ciphertext' => base64_encode($this->faker->randomBytes($this->getCiphertextSize($algorithm))),
        ]);
    }

    /**
     * Indicate that the message is classical only.
     */
    public function classicalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantum_resistant' => false,
            'quantum_algorithm' => null,
            'quantum_version' => null,
            'quantum_ciphertext' => null,
            'protocol_version' => 2,
        ]);
    }

    /**
     * Indicate that the message is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_status' => 'delivered',
            'delivered_at' => $this->faker->dateTimeBetween($attributes['sent_at'] ?? '-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the message failed delivery.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_status' => 'failed',
            'delivered_at' => null,
        ]);
    }

    /**
     * Generate a mock ratchet message structure.
     */
    private function generateRatchetMessage(): array
    {
        return [
            'header' => [
                'sender_chain_key' => base64_encode($this->faker->randomBytes(32)),
                'previous_counter' => $this->faker->numberBetween(0, 100),
                'ratchet_key' => base64_encode($this->faker->randomBytes(32)),
            ],
            'ciphertext' => base64_encode($this->faker->randomBytes(128)),
            'isQuantumEncrypted' => $this->faker->boolean(70),
            'quantumAlgorithm' => $this->faker->randomElement(['ML-KEM-768', 'ML-KEM-512']),
            'quantumCiphertext' => base64_encode($this->faker->randomBytes(256)),
            'quantumIv' => base64_encode($this->faker->randomBytes(12)),
        ];
    }

    /**
     * Get the ciphertext size for different quantum algorithms.
     */
    private function getCiphertextSize(string $algorithm): int
    {
        return match ($algorithm) {
            'ML-KEM-512' => 768,   // ML-KEM-512 ciphertext size
            'ML-KEM-768' => 1088,  // ML-KEM-768 ciphertext size
            'ML-KEM-1024' => 1568, // ML-KEM-1024 ciphertext size
            default => 1088,       // Default to ML-KEM-768
        };
    }
}