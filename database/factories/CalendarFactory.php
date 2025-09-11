<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'timezone' => fake()->timezone(),
            'calendarable_id' => \App\Models\User::factory(),
            'calendarable_type' => \App\Models\User::class,
            'visibility' => fake()->randomElement(['public', 'private', 'shared']),
            'settings' => null,
            'is_active' => true,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => null,
        ];
    }
}
