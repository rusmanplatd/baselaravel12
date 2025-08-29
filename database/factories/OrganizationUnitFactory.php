<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
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
            'unit_code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement(['Department', 'Division', 'Team']),
            'unit_type' => $this->faker->randomElement([
                'board_of_commissioners',
                'board_of_directors',
                'executive_committee',
                'audit_committee',
                'risk_committee',
                'nomination_committee',
                'remuneration_committee',
                'division',
                'department',
                'section',
                'team',
                'branch_office',
                'representative_office',
            ]),
            'description' => $this->faker->paragraph(),
            'parent_unit_id' => null,
            'responsibilities' => json_encode([
                $this->faker->sentence(),
                $this->faker->sentence(),
            ]),
            'authorities' => json_encode([
                $this->faker->sentence(),
                $this->faker->sentence(),
            ]),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
