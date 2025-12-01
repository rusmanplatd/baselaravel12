<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['direct', 'group', 'channel']);
        
        return [
            'name' => $type === 'direct' ? null : $this->faker->words(2, true) . ' Chat',
            'type' => $type,
            'description' => $type === 'channel' ? $this->faker->sentence() : null,
            'avatar_url' => $this->faker->optional()->imageUrl(100, 100),
            'created_by_user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'is_active' => true,
            'last_activity_at' => $this->faker->optional()->dateTimeBetween('-7 days', 'now') ?? now(),
            'settings' => $this->faker->optional()->passthrough([
                'max_participants' => $this->faker->numberBetween(10, 1000),
                'auto_delete_after' => $this->faker->optional()->numberBetween(1, 365),
                'welcome_message' => $this->faker->optional()->sentence(),
                'encryption_algorithm' => $this->faker->randomElement(['AES-256-GCM', 'ChaCha20-Poly1305', 'ML-KEM-768']),
                'key_strength' => $this->faker->randomElement([256, 384, 768]),
                'invite_only' => $this->faker->boolean(),
                'message_history_visible' => $this->faker->boolean(80),
                'allow_guest_access' => $this->faker->boolean(20),
                'version' => $this->faker->numberBetween(2, 3),
                'key_rotation_enabled' => $this->faker->boolean(70),
                'last_key_rotation' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            ]),
        ];
    }

    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'direct',
            'name' => null,
            'description' => null,
            'settings' => null,
        ]);
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'group',
            'name' => $this->faker->words(2, true) . ' Group',
            'description' => $this->faker->optional()->sentence(),
        ]);
    }

    public function channel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'channel',
            'name' => '#' . $this->faker->word(),
            'description' => $this->faker->sentence(),
            'settings' => array_merge($attributes['settings'] ?? [], [
                'invite_only' => false,
                'message_history_visible' => true,
                'allow_guest_access' => true,
            ]),
        ]);
    }

    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'encryption_algorithm' => 'AES-256-GCM',
                'key_strength' => 256,
                'version' => 3,
                'key_rotation_enabled' => true,
                'last_key_rotation' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ]),
        ]);
    }

    public function quantum(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'encryption_algorithm' => 'ML-KEM-768',
                'key_strength' => 768,
                'version' => 3,
                'quantum_resistant' => true,
                'key_rotation_enabled' => true,
                'last_key_rotation' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}