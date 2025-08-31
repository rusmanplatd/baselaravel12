<?php

namespace Database\Factories\Master\Geo;

use App\Models\Master\Geo\City;
use App\Models\Master\Geo\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Geo\City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->unique()->city(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
