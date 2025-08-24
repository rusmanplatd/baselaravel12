<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deviceTypes = ['desktop', 'mobile', 'tablet'];
        $browsers = ['Chrome 120.0', 'Firefox 119.0', 'Safari 17.0', 'Edge 119.0'];
        $platforms = ['Windows 11', 'macOS 14.0', 'Ubuntu 22.04', 'iOS 17.0', 'Android 14'];

        return [
            'user_id' => \App\Models\User::factory(),
            'device_token' => $this->faker->sha256,
            'device_name' => $this->faker->randomElement([
                'iPhone 15',
                'MacBook Pro',
                'Windows PC',
                'Android Phone',
                'iPad',
                'Chrome Browser',
                'Home Computer',
            ]),
            'device_type' => $this->faker->randomElement($deviceTypes),
            'browser' => $this->faker->randomElement($browsers),
            'platform' => $this->faker->randomElement($platforms),
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'location' => $this->faker->optional(0.7)->city.', '.$this->faker->optional(0.7)->country,
            'last_used_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'expires_at' => $this->faker->dateTimeBetween('now', '+60 days'),
            'is_active' => true,
            'metadata' => [
                'created_via' => 'web_auth',
                'timezone' => $this->faker->timezone,
                'languages' => 'en-US,en;q=0.9',
            ],
        ];
    }
}
