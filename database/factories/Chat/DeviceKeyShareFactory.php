<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Conversation;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\DeviceKeyShare>
 */
class DeviceKeyShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        $fromDevice = UserDevice::factory()->trusted()->create(['user_id' => $user->id]);
        $toDevice = UserDevice::factory()->untrusted()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create();

        return [
            'from_device_id' => $fromDevice->id,
            'to_device_id' => $toDevice->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'encrypted_symmetric_key' => $this->faker->sha256(),
            'from_device_public_key' => $fromDevice->public_key,
            'to_device_public_key' => $toDevice->public_key,
            'key_version' => $this->faker->numberBetween(1, 10),
            'share_method' => $this->faker->randomElement(['device_to_device', 'backup_restore', 'manual_transfer']),
            'is_accepted' => false,
            'is_active' => true,
            'expires_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'accepted_at' => null,
        ];
    }

    /**
     * Indicate that the key share is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_accepted' => true,
            'accepted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the key share is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_accepted' => false,
            'accepted_at' => null,
            'expires_at' => $this->faker->dateTimeBetween('now', '+7 days'),
        ]);
    }

    /**
     * Indicate that the key share is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_accepted' => false,
            'expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the key share is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
