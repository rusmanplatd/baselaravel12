<?php

namespace Database\Factories\Master\Geo;

use App\Models\Master\Geo\City;
use App\Models\Master\Geo\District;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Geo\District>
 */
class DistrictFactory extends Factory
{
    protected $model = District::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('?????')),
            'name' => $this->faker->unique()->citySuffix().' '.$this->faker->city(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
