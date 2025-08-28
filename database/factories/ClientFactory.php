<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->ulid(),
            'owner_id' => User::factory(),
            'owner_type' => User::class,
            'name' => $this->faker->company().' App',
            'secret' => Str::random(40),
            'provider' => null,
            'redirect' => ['https://example.com/callback'],
            'grant_types' => json_encode(['authorization_code', 'refresh_token']),
            'revoked' => false,
            'organization_id' => Organization::factory(),
            'allowed_scopes' => json_encode(['openid', 'profile', 'email']),
            'client_type' => 'public',
            'user_access_scope' => 'organization_members',  // Default to most restrictive
            'user_access_rules' => null,
            'last_used_at' => null,
        ];
    }

    /**
     * Indicate that the client allows all users.
     */
    public function allUsers(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_access_scope' => 'all_users',
        ]);
    }

    /**
     * Indicate that the client is for organization members only.
     */
    public function organizationMembersOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_access_scope' => 'organization_members',
        ]);
    }

    /**
     * Indicate that the client uses custom access rules.
     */
    public function withCustomRules(array $rules): static
    {
        return $this->state(fn (array $attributes) => [
            'user_access_scope' => 'custom',
            'user_access_rules' => $rules,
        ]);
    }

    /**
     * Indicate that the client is confidential.
     */
    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => 'confidential',
            'secret' => Str::random(40),
        ]);
    }

    /**
     * Indicate that the client is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked' => true,
        ]);
    }
}
