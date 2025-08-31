<?php

namespace Database\Factories\Master\Geo;

use App\Models\Master\Geo\District;
use App\Models\Master\Geo\Village;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Geo\Village>
 */
class VillageFactory extends Factory
{
    protected $model = Village::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => $this->faker->unique()->cityPrefix().' '.$this->faker->city(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
