<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarEventController extends Controller
{
    public function index(Request $request, Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canView($user)) {
            return response()->json(['error' => 'Not authorized to view this calendar'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:confirmed,tentative,cancelled',
            'visibility' => 'nullable|in:public,private,confidential',
        ]);

        $query = $calendar->events()->with(['calendar', 'createdBy']);

        if ($request->start_date && $request->end_date) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->inDateRange($startDate, $endDate);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->visibility) {
            $query->where('visibility', $request->visibility);
        }

        $events = $query->orderBy('starts_at')->get();

        return response()->json([
            'events' => $events->map(function ($event) {
                return $this->formatEventResponse($event);
            }),
        ]);
    }

    public function store(Request $request, Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canEdit($user)) {
            return response()->json(['error' => 'Not authorized to create events in this calendar'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_all_day' => 'boolean',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'status' => 'nullable|in:confirmed,tentative,cancelled',
            'visibility' => 'nullable|in:public,private,confidential',
            'recurrence_rule' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*.email' => 'required_with:attendees|email',
            'attendees.*.name' => 'nullable|string',
            'reminders' => 'nullable|array',
            'reminders.*.minutes' => 'required_with:reminders|integer|min:0',
            'reminders.*.method' => 'required_with:reminders|in:popup,email',
            'meeting_url' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        $event = CalendarEvent::create([
            'calendar_id' => $calendar->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'is_all_day' => $validated['is_all_day'] ?? false,
            'location' => $validated['location'] ?? null,
            'color' => $validated['color'] ?? null,
            'status' => $validated['status'] ?? 'confirmed',
            'visibility' => $validated['visibility'] ?? 'public',
            'recurrence_rule' => $validated['recurrence_rule'] ?? null,
            'attendees' => $validated['attendees'] ?? null,
            'reminders' => $validated['reminders'] ?? null,
            'meeting_url' => $validated['meeting_url'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $event->load(['calendar', 'createdBy']);

        return response()->json([
            'event' => $this->formatEventResponse($event),
        ], 201);
    }

    public function show(Calendar $calendar, CalendarEvent $event): JsonResponse
    {
        $user = Auth::user();
        
        if (!$event->canView($user)) {
            return response()->json(['error' => 'Not authorized to view this event'], 403);
        }

        if ($event->calendar_id !== $calendar->id) {
            return response()->json(['error' => 'Event does not belong to this calendar'], 404);
        }

        $event->load(['calendar', 'createdBy', 'recurrenceParent', 'recurrenceInstances']);

        return response()->json([
            'event' => $this->formatEventResponse($event, true),
        ]);
    }

    public function update(Request $request, Calendar $calendar, CalendarEvent $event): JsonResponse
    {
        $user = Auth::user();
        
        if (!$event->canEdit($user)) {
            return response()->json(['error' => 'Not authorized to edit this event'], 403);
        }

        if ($event->calendar_id !== $calendar->id) {
            return response()->json(['error' => 'Event does not belong to this calendar'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_all_day' => 'boolean',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'status' => 'sometimes|in:confirmed,tentative,cancelled',
            'visibility' => 'sometimes|in:public,private,confidential',
            'recurrence_rule' => 'nullable|string',
            'attendees' => 'nullable|array',
            'reminders' => 'nullable|array',
            'meeting_url' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        $event->update(array_merge($validated, ['updated_by' => Auth::id()]));

        return response()->json([
            'event' => $this->formatEventResponse($event),
        ]);
    }

    public function destroy(Calendar $calendar, CalendarEvent $event): JsonResponse
    {
        $user = Auth::user();
        
        if (!$event->canEdit($user)) {
            return response()->json(['error' => 'Not authorized to delete this event'], 403);
        }

        if ($event->calendar_id !== $calendar->id) {
            return response()->json(['error' => 'Event does not belong to this calendar'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    public function getEventsInRange(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'calendar_ids' => 'nullable|array',
            'calendar_ids.*' => 'string|exists:calendars,id',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $query = CalendarEvent::with(['calendar'])
            ->inDateRange($startDate, $endDate)
            ->active();

        if (isset($validated['calendar_ids'])) {
            $query->whereIn('calendar_id', $validated['calendar_ids']);
        } else {
            // Get all calendars the user can view
            $visibleCalendarIds = Calendar::visible($user)->active()->pluck('id');
            $query->whereIn('calendar_id', $visibleCalendarIds);
        }

        $events = $query->orderBy('starts_at')->get();

        // Filter out events the user can't view
        $events = $events->filter(function ($event) use ($user) {
            return $event->canView($user);
        });

        return response()->json([
            'events' => $events->map(function ($event) {
                return $this->formatEventResponse($event);
            }),
            'period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
        ]);
    }

    public function updateAttendeeStatus(Request $request, Calendar $calendar, CalendarEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'status' => 'required|in:accepted,declined,tentative',
        ]);

        $attendees = $event->attendees ?? [];
        $found = false;

        foreach ($attendees as &$attendee) {
            if ($attendee['email'] === $validated['email']) {
                $attendee['status'] = $validated['status'];
                $attendee['responded_at'] = now()->toISOString();
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json(['error' => 'Attendee not found'], 404);
        }

        $event->update(['attendees' => $attendees]);

        return response()->json(['message' => 'Attendee status updated successfully']);
    }

    private function formatEventResponse(CalendarEvent $event, bool $detailed = false): array
    {
        $response = [
            'id' => $event->id,
            'calendar_id' => $event->calendar_id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $event->starts_at->toISOString(),
            'ends_at' => $event->ends_at?->toISOString(),
            'is_all_day' => $event->is_all_day,
            'location' => $event->location,
            'color' => $event->display_color,
            'status' => $event->status,
            'visibility' => $event->visibility,
            'is_recurring' => $event->isRecurring(),
            'duration_minutes' => $event->getDurationInMinutes(),
            'meeting_url' => $event->meeting_url,
            'created_at' => $event->created_at->toISOString(),
            'updated_at' => $event->updated_at->toISOString(),
        ];

        if ($detailed) {
            $response = array_merge($response, [
                'recurrence_rule' => $event->recurrence_rule,
                'recurrence_parent_id' => $event->recurrence_parent_id,
                'attendees' => $event->attendees,
                'reminders' => $event->reminders,
                'metadata' => $event->metadata,
                'creator' => $event->createdBy ? [
                    'id' => $event->createdBy->id,
                    'name' => $event->createdBy->name,
                ] : null,
                'calendar' => [
                    'id' => $event->calendar->id,
                    'name' => $event->calendar->name,
                    'color' => $event->calendar->color,
                ],
            ]);
        }

        return $response;
    }
}
