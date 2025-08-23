<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'membership_type' => $this->faker->randomElement([
                'employee',
                'board_member',
                'consultant',
                'contractor',
                'intern',
            ]),
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'status' => 'active',
            'additional_roles' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
