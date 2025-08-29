<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationPositionLevel;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationPosition>
 */
class OrganizationPositionFactory extends Factory
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
            'organization_id' => Organization::factory(),
            'organization_unit_id' => OrganizationUnit::factory(),
            'position_code' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{4}'),
            'organization_position_level_id' => OrganizationPositionLevel::factory(),
            'title' => $this->faker->jobTitle(),
            'job_description' => $this->faker->paragraph(),
            'qualifications' => json_encode([
                $this->faker->word() . ' degree',
                $this->faker->numberBetween(1, 10) . '+ years experience',
            ]),
            'responsibilities' => json_encode([
                $this->faker->sentence(),
                $this->faker->sentence(),
                $this->faker->sentence(),
            ]),
            'min_salary' => $this->faker->randomFloat(2, 30000, 50000),
            'max_salary' => $this->faker->randomFloat(2, 60000, 120000),
            'is_active' => true,
            'max_incumbents' => $this->faker->numberBetween(1, 5),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
