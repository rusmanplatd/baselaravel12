<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarBasicTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_calendar_for_user()
    {
        $user = User::factory()->create();

        $calendar = Calendar::create([
            'name' => 'My Personal Calendar',
            'description' => 'Test calendar',
            'color' => '#3498db',
            'timezone' => 'UTC',
            'calendarable_id' => $user->id,
            'calendarable_type' => User::class,
            'visibility' => 'private',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('calendars', [
            'name' => 'My Personal Calendar',
            'calendarable_id' => $user->id,
            'calendarable_type' => User::class,
        ]);

        $this->assertEquals($user->id, $calendar->calendarable_id);
        $this->assertEquals(User::class, $calendar->calendarable_type);
    }

    public function test_can_create_event_in_calendar()
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->create([
            'calendarable_id' => $user->id,
            'calendarable_type' => User::class,
        ]);

        $event = CalendarEvent::create([
            'calendar_id' => $calendar->id,
            'title' => 'Test Meeting',
            'description' => 'A test meeting',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'is_all_day' => false,
            'status' => 'confirmed',
            'visibility' => 'public',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'calendar_id' => $calendar->id,
            'title' => 'Test Meeting',
        ]);

        $this->assertEquals($calendar->id, $event->calendar_id);
        $this->assertEquals('Test Meeting', $event->title);
    }

    public function test_calendar_can_view_permissions()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $calendar = Calendar::factory()->create([
            'calendarable_id' => $user->id,
            'calendarable_type' => User::class,
            'visibility' => 'private',
            'created_by' => $user->id,
        ]);

        // Owner can view
        $this->assertTrue($calendar->canView($user));

        // Other user cannot view private calendar
        $this->assertFalse($calendar->canView($otherUser));

        // Share calendar with other user
        $calendar->shareWith($otherUser, 'read', $user);

        // Now other user can view
        $this->assertTrue($calendar->canView($otherUser));
    }

    public function test_can_retrieve_calendar_events()
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->create([
            'calendarable_id' => $user->id,
            'calendarable_type' => User::class,
        ]);

        // Create some events
        CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id,
            'title' => 'Event 1',
        ]);
        CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id,
            'title' => 'Event 2',
        ]);

        $events = $calendar->events;
        $this->assertCount(2, $events);
    }
}