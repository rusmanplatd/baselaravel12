<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+30 days');
        $endDate = fake()->dateTimeBetween($startDate, $startDate->format('Y-m-d H:i:s').' +2 hours');
        
        return [
            'calendar_id' => \App\Models\Calendar::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'is_all_day' => fake()->boolean(20), // 20% chance of being all day
            'location' => fake()->optional()->address(),
            'color' => fake()->optional()->hexColor(),
            'status' => fake()->randomElement(['confirmed', 'tentative', 'cancelled']),
            'visibility' => fake()->randomElement(['public', 'private', 'confidential']),
            'recurrence_rule' => null,
            'recurrence_parent_id' => null,
            'attendees' => null,
            'reminders' => null,
            'metadata' => null,
            'meeting_url' => fake()->optional()->url(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => null,
        ];
    }
}
