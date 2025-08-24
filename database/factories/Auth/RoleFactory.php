<?php

namespace Database\Factories\Auth;

use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Auth\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word().'-role',
            'guard_name' => 'web',
        ];
    }

    /**
     * Create a role with a specific name
     */
    public function withName(string $name): static
    {
        return $this->state([
            'name' => $name,
        ]);
    }

    /**
     * Create a role for API guard
     */
    public function forApi(): static
    {
        return $this->state([
            'guard_name' => 'api',
        ]);
    }

    /**
     * Create a role with team/organization context
     */
    public function forTeam(string $teamId): static
    {
        return $this->state([
            'team_id' => $teamId,
        ]);
    }
}
