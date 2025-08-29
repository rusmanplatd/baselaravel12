<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'nickname' => fake()->optional(0.4)->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profile_url' => fake()->optional(0.2)->url(),
            'website' => fake()->optional(0.3)->url(),
            'gender' => fake()->optional(0.7)->randomElement(['male', 'female', 'other']),
            'birthdate' => fake()->optional(0.8)->passthrough(
                fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d')
            ),
            'zoneinfo' => fake()->timezone(),
            'locale' => fake()->randomElement(['en-US', 'en-GB', 'es-ES', 'fr-FR', 'de-DE', 'ja-JP', 'zh-CN']),
            'street_address' => fake()->streetAddress(),
            'locality' => fake()->city(),
            'region' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'phone_number' => fake()->phoneNumber(),
            'phone_verified_at' => fake()->optional(0.6)->dateTimeBetween('-1 year', 'now'),
            'profile_updated_at' => fake()->optional(0.7)->dateTimeBetween('-6 months', 'now'),
            'external_id' => fake()->optional(0.3)->uuid(),
            'social_links' => fake()->optional(0.5)->passthrough([
                'twitter' => fake()->optional(0.6)->userName(),
                'linkedin' => fake()->optional(0.4)->userName(),
                'github' => fake()->optional(0.3)->userName(),
                'instagram' => fake()->optional(0.5)->userName(),
            ]),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
