<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\ChatEncryptionService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserDevice>
 */
class UserDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $encryptionService = new ChatEncryptionService;
        $keyPair = $encryptionService->generateKeyPair();

        $deviceTypes = ['mobile', 'desktop', 'web', 'tablet'];
        $platforms = ['iOS', 'Android', 'Windows', 'macOS', 'Linux', 'Chrome', 'Firefox', 'Safari'];

        return [
            'user_id' => User::factory(),
            'device_name' => $this->faker->randomElement([
                'iPhone 15',
                'Samsung Galaxy S24',
                'MacBook Pro',
                'Dell XPS',
                'iPad Pro',
                'Chrome Browser',
                'Firefox Browser',
            ]),
            'device_type' => $this->faker->randomElement($deviceTypes),
            'public_key' => $keyPair['public_key'],
            'device_fingerprint' => 'device_'.$this->faker->unique()->sha256(),
            'platform' => $this->faker->randomElement($platforms),
            'user_agent' => $this->faker->userAgent(),
            'last_used_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'is_trusted' => $this->faker->boolean(70), // 70% chance of being trusted
            'is_active' => true,
            'verified_at' => function (array $attributes) {
                return $attributes['is_trusted'] ? $this->faker->dateTimeBetween('-30 days', 'now') : null;
            },
        ];
    }

    /**
     * Indicate that the device is trusted.
     */
    public function trusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_trusted' => true,
            'verified_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the device is not trusted.
     */
    public function untrusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_trusted' => false,
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a mobile device.
     */
    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'mobile',
            'device_name' => $this->faker->randomElement(['iPhone 15', 'Samsung Galaxy S24', 'Google Pixel 8']),
            'platform' => $this->faker->randomElement(['iOS', 'Android']),
        ]);
    }

    /**
     * Create a desktop device.
     */
    public function desktop(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'desktop',
            'device_name' => $this->faker->randomElement(['MacBook Pro', 'Dell XPS', 'Surface Laptop']),
            'platform' => $this->faker->randomElement(['macOS', 'Windows', 'Linux']),
        ]);
    }

    /**
     * Create a web browser device.
     */
    public function web(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'web',
            'device_name' => $this->faker->randomElement(['Chrome Browser', 'Firefox Browser', 'Safari Browser']),
            'platform' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
        ]);
    }
}
