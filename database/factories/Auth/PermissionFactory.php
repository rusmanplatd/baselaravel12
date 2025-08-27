<?php

namespace Database\Factories\Auth;

use App\Models\Auth\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Auth\Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $resource = $this->faker->randomElement(['users', 'roles', 'permissions', 'organizations', 'posts', 'files']);
        $action = $this->faker->randomElement(['view', 'create', 'edit', 'delete']);

        return [
            'name' => "{$action} {$resource}",
            'guard_name' => 'web',
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Create a permission with a specific name
     */
    public function withName(string $name): static
    {
        return $this->state([
            'name' => $name,
        ]);
    }

    /**
     * Create a permission for API guard
     */
    public function forApi(): static
    {
        return $this->state([
            'guard_name' => 'api',
        ]);
    }

    /**
     * Create a permission with resource:action format
     */
    public function withResourceAction(string $resource, string $action): static
    {
        return $this->state([
            'name' => "{$action} {$resource}",
        ]);
    }

    /**
     * Create a management permission
     */
    public function forManagement(string $resource): static
    {
        return $this->state([
            'name' => "manage {$resource}",
        ]);
    }
}
