<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_code' => $this->faker->unique()->lexify('???-???'),
            'name' => $this->faker->company(),
            'organization_type' => $this->faker->randomElement([
                'holding_company',
                'subsidiary',
                'division',
                'branch',
                'department',
                'unit',
            ]),
            'description' => $this->faker->paragraph(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'website' => $this->faker->url(),
            'is_active' => true,
            'establishment_date' => $this->faker->date(),
            'level' => 0,
            'path' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
