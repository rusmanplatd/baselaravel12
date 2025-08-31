<?php

namespace Database\Factories\Master\Geo;

use App\Models\Master\Geo\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Geo\Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'name' => $this->faker->unique()->country(),
            'iso_code' => strtoupper($this->faker->unique()->lexify('???')),
            'phone_code' => '+'.$this->faker->numberBetween(1, 999),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
