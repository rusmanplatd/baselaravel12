<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationPositionLevel>
 */
class OrganizationPositionLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{2}'),
            'name' => $this->faker->jobTitle(),
            'description' => $this->faker->sentence(),
            'hierarchy_level' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
