<?php

namespace Database\Factories\Master\Geo;

use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Geo\Province>
 */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'name' => $this->faker->unique()->state(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
