<?php

namespace App\Services;

use App\Models\MeetingCalendarIntegration;
use App\Models\MeetingAttendee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MeetingAnalyticsService
{
    public function __construct(
        protected LiveKitService $liveKitService
    ) {}

    /**
     * Generate comprehensive meeting analytics report
     */
    public function generateMeetingReport(MeetingCalendarIntegration $meeting): array
    {
        try {
            $roomName = $meeting->integration_metadata['livekit_room_name'] ?? null;
            $liveKitAnalytics = $roomName ? $this->liveKitService->getRoomAnalytics($roomName) : null;
            $qualityMetrics = $roomName ? $this->liveKitService->getRoomQualityMetrics($roomName) : null;

            $report = [
                'meeting_id' => $meeting->id,
                'meeting_info' => $meeting->getMeetingInfo(),
                'summary' => $this->generateMeetingSummary($meeting),
                'attendance' => $this->generateAttendanceReport($meeting),
                'engagement' => $this->generateEngagementMetrics($meeting),
                'quality' => $qualityMetrics,
                'livekit_analytics' => $liveKitAnalytics,
                'generated_at' => now()->toISOString()
            ];

            // Cache the report for 1 hour
            Cache::put("meeting_report_{$meeting->id}", $report, 3600);

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate meeting report', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate meeting summary statistics
     */
    public function generateMeetingSummary(MeetingCalendarIntegration $meeting): array
    {
        $duration = $meeting->getMeetingDuration();
        $attendeeCount = $meeting->getAttendeeCount();
        $joinedCount = $meeting->attendees()->where('attendance_status', 'joined')->count();
        $leftCount = $meeting->attendees()->where('attendance_status', 'left')->count();

        return [
            'total_duration_minutes' => $duration,
            'scheduled_duration_minutes' => $meeting->calendarEvent->getDurationInMinutes(),
            'total_attendees' => $attendeeCount,
            'joined_attendees' => $joinedCount,
            'left_attendees' => $leftCount,
            'attendance_rate' => $attendeeCount > 0 ? round(($joinedCount / $attendeeCount) * 100, 2) : 0,
            'completion_rate' => $duration && $meeting->calendarEvent->getDurationInMinutes() 
                ? round(($duration / $meeting->calendarEvent->getDurationInMinutes()) * 100, 2) 
                : 0,
            'status' => $meeting->status,
            'e2ee_enabled' => $meeting->e2ee_enabled,
            'recording_enabled' => $meeting->recording_enabled
        ];
    }

    /**
     * Generate detailed attendance report
     */
    public function generateAttendanceReport(MeetingCalendarIntegration $meeting): array
    {
        $attendees = $meeting->attendees()->with('user')->get();
        
        $attendanceData = [
            'by_status' => [],
            'by_role' => [],
            'duration_stats' => [],
            'late_joiners' => [],
            'early_leavers' => [],
            'no_shows' => []
        ];

        // Group by status
        foreach ($attendees as $attendee) {
            $status = $attendee->attendance_status;
            $attendanceData['by_status'][$status] = ($attendanceData['by_status'][$status] ?? 0) + 1;

            $role = $attendee->role;
            $attendanceData['by_role'][$role] = ($attendanceData['by_role'][$role] ?? 0) + 1;

            // Analyze join/leave patterns
            if ($attendee->joined_at && $meeting->meeting_started_at) {
                $joinDelay = Carbon::parse($attendee->joined_at)
                    ->diffInMinutes(Carbon::parse($meeting->meeting_started_at));
                
                if ($joinDelay > 5) { // More than 5 minutes late
                    $attendanceData['late_joiners'][] = [
                        'name' => $attendee->getDisplayName(),
                        'delay_minutes' => $joinDelay
                    ];
                }
            }

            // Track early leavers
            if ($attendee->left_at && $meeting->meeting_ended_at && $meeting->status === 'ended') {
                $leftEarly = Carbon::parse($meeting->meeting_ended_at)
                    ->diffInMinutes(Carbon::parse($attendee->left_at));
                
                if ($leftEarly > 10) { // Left more than 10 minutes early
                    $attendanceData['early_leavers'][] = [
                        'name' => $attendee->getDisplayName(),
                        'left_early_minutes' => $leftEarly
                    ];
                }
            }

            // Track no-shows
            if ($attendee->attendance_status === 'not_joined') {
                $attendanceData['no_shows'][] = [
                    'name' => $attendee->getDisplayName(),
                    'email' => $attendee->email,
                    'role' => $attendee->role
                ];
            }

            // Duration statistics
            if ($attendee->duration_minutes) {
                $attendanceData['duration_stats'][] = $attendee->duration_minutes;
            }
        }

        // Calculate duration statistics
        if (!empty($attendanceData['duration_stats'])) {
            $durations = $attendanceData['duration_stats'];
            $attendanceData['duration_analysis'] = [
                'average_minutes' => round(array_sum($durations) / count($durations), 2),
                'max_minutes' => max($durations),
                'min_minutes' => min($durations),
                'median_minutes' => $this->calculateMedian($durations)
            ];
        }

        return $attendanceData;
    }

    /**
     * Generate engagement metrics
     */
    public function generateEngagementMetrics(MeetingCalendarIntegration $meeting): array
    {
        $roomName = $meeting->integration_metadata['livekit_room_name'] ?? null;
        if (!$roomName) {
            return ['error' => 'No LiveKit room data available'];
        }

        try {
            $analytics = $this->liveKitService->getRoomAnalytics($roomName);
            $qualityMetrics = $this->liveKitService->getRoomQualityMetrics($roomName);

            $engagement = [
                'participation_rate' => 0,
                'active_speakers' => 0,
                'screen_shares' => 0,
                'video_enabled_participants' => 0,
                'audio_enabled_participants' => 0,
                'chat_activity' => 0, // This would need to be tracked separately
                'device_types' => []
            ];

            foreach ($analytics['participants'] ?? [] as $participant) {
                $tracks = $participant['tracks'] ?? [];
                $hasAudio = false;
                $hasVideo = false;
                $hasScreen = false;

                foreach ($tracks as $track) {
                    $type = $track['type'] ?? '';
                    $source = $track['source'] ?? '';
                    
                    if ($type === 'audio') $hasAudio = true;
                    if ($type === 'video' && $source === 'camera') $hasVideo = true;
                    if ($type === 'video' && $source === 'screen_share') $hasScreen = true;
                }

                if ($hasAudio) $engagement['audio_enabled_participants']++;
                if ($hasVideo) $engagement['video_enabled_participants']++;
                if ($hasScreen) $engagement['screen_shares']++;

                // Extract device type from metadata
                $metadata = $participant['metadata'] ?? [];
                if (isset($metadata['device_type'])) {
                    $deviceType = $metadata['device_type'];
                    $engagement['device_types'][$deviceType] = 
                        ($engagement['device_types'][$deviceType] ?? 0) + 1;
                }
            }

            $totalParticipants = count($analytics['participants'] ?? []);
            if ($totalParticipants > 0) {
                $engagement['participation_rate'] = round(
                    ($engagement['audio_enabled_participants'] / $totalParticipants) * 100, 2
                );
            }

            return $engagement;
        } catch (\Exception $e) {
            Log::error('Failed to generate engagement metrics', [
                'meeting_id' => $meeting->id,
                'room_name' => $roomName,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get organization-wide meeting analytics
     */
    public function getOrganizationAnalytics(
        ?string $organizationId = null,
        Carbon $startDate = null,
        Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = MeetingCalendarIntegration::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($organizationId) {
            $query->whereHas('calendarEvent.calendar.organization', function ($q) use ($organizationId) {
                $q->where('id', $organizationId);
            });
        }

        $meetings = $query->with(['attendees', 'calendarEvent'])->get();

        $analytics = [
            'period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString()
            ],
            'overview' => [
                'total_meetings' => $meetings->count(),
                'total_participants' => $meetings->sum(fn($m) => $m->getAttendeeCount()),
                'average_duration_minutes' => $meetings->avg(fn($m) => $m->getMeetingDuration()),
                'total_duration_hours' => round($meetings->sum(fn($m) => $m->getMeetingDuration()) / 60, 2)
            ],
            'by_status' => $meetings->groupBy('status')->map->count(),
            'by_day' => [],
            'by_hour' => [],
            'popular_durations' => [],
            'usage_trends' => []
        ];

        // Group by day
        foreach ($meetings as $meeting) {
            $day = $meeting->created_at->format('Y-m-d');
            $analytics['by_day'][$day] = ($analytics['by_day'][$day] ?? 0) + 1;
        }

        // Group by hour of day
        foreach ($meetings as $meeting) {
            $hour = $meeting->calendarEvent->starts_at->format('H');
            $analytics['by_hour'][$hour] = ($analytics['by_hour'][$hour] ?? 0) + 1;
        }

        // Popular meeting durations
        $durations = $meetings->map(fn($m) => $m->calendarEvent->getDurationInMinutes())
            ->filter()
            ->groupBy(fn($duration) => $this->getDurationCategory($duration))
            ->map->count();
        
        $analytics['popular_durations'] = $durations->toArray();

        return $analytics;
    }

    /**
     * Track real-time meeting quality metrics
     */
    public function trackMeetingQuality(MeetingCalendarIntegration $meeting): array
    {
        $roomName = $meeting->integration_metadata['livekit_room_name'] ?? null;
        if (!$roomName) {
            return ['error' => 'No LiveKit room data available'];
        }

        try {
            $qualityMetrics = $this->liveKitService->getRoomQualityMetrics($roomName);
            
            // Store quality snapshot in database
            $qualitySnapshot = [
                'meeting_id' => $meeting->id,
                'timestamp' => now(),
                'participant_count' => $qualityMetrics['participant_count'],
                'total_tracks' => $qualityMetrics['total_tracks'],
                'audio_tracks' => $qualityMetrics['audio_tracks'],
                'video_tracks' => $qualityMetrics['video_tracks'],
                'quality_data' => $qualityMetrics
            ];

            // Cache for real-time dashboard
            Cache::put(
                "meeting_quality_{$meeting->id}",
                $qualitySnapshot,
                300 // 5 minutes
            );

            return $qualitySnapshot;
        } catch (\Exception $e) {
            Log::error('Failed to track meeting quality', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate meeting satisfaction survey data
     */
    public function generateSatisfactionReport(MeetingCalendarIntegration $meeting): array
    {
        // This would integrate with a survey system
        // For now, return a placeholder structure
        return [
            'meeting_id' => $meeting->id,
            'survey_sent' => false,
            'responses' => 0,
            'average_rating' => null,
            'feedback_categories' => [
                'audio_quality' => [],
                'video_quality' => [],
                'ease_of_use' => [],
                'overall_experience' => []
            ],
            'recommendations' => []
        ];
    }

    /**
     * Export meeting data for analysis
     */
    public function exportMeetingData(array $meetingIds, string $format = 'json'): array
    {
        $meetings = MeetingCalendarIntegration::whereIn('id', $meetingIds)
            ->with(['attendees.user', 'calendarEvent'])
            ->get();

        $exportData = [];

        foreach ($meetings as $meeting) {
            $report = $this->generateMeetingReport($meeting);
            
            switch ($format) {
                case 'csv':
                    $exportData[] = $this->flattenForCsv($report);
                    break;
                case 'json':
                default:
                    $exportData[] = $report;
                    break;
            }
        }

        return [
            'format' => $format,
            'count' => count($exportData),
            'data' => $exportData,
            'exported_at' => now()->toISOString()
        ];
    }

    /**
     * Get meeting performance insights
     */
    public function getMeetingInsights(array $meetingIds = []): array
    {
        $query = MeetingCalendarIntegration::query();
        
        if (!empty($meetingIds)) {
            $query->whereIn('id', $meetingIds);
        } else {
            // Last 100 meetings by default
            $query->latest()->limit(100);
        }

        $meetings = $query->with(['attendees', 'calendarEvent'])->get();

        $insights = [
            'optimal_meeting_duration' => $this->findOptimalDuration($meetings),
            'best_meeting_times' => $this->findBestMeetingTimes($meetings),
            'attendance_patterns' => $this->analyzeAttendancePatterns($meetings),
            'quality_recommendations' => $this->generateQualityRecommendations($meetings),
            'usage_efficiency' => $this->calculateUsageEfficiency($meetings)
        ];

        return $insights;
    }

    // =================== PRIVATE HELPER METHODS ===================

    private function calculateMedian(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        
        if ($count % 2 === 0) {
            return ($numbers[$count / 2 - 1] + $numbers[$count / 2]) / 2;
        } else {
            return $numbers[floor($count / 2)];
        }
    }

    private function getDurationCategory(int $minutes): string
    {
        if ($minutes <= 15) return 'Quick (≤15min)';
        if ($minutes <= 30) return 'Short (16-30min)';
        if ($minutes <= 60) return 'Medium (31-60min)';
        if ($minutes <= 120) return 'Long (61-120min)';
        return 'Extended (>120min)';
    }

    private function flattenForCsv(array $data): array
    {
        $flattened = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flattened[$key] = json_encode($value);
            } else {
                $flattened[$key] = $value;
            }
        }
        
        return $flattened;
    }

    private function findOptimalDuration($meetings): array
    {
        $durationData = $meetings->map(function ($meeting) {
            return [
                'scheduled' => $meeting->calendarEvent->getDurationInMinutes(),
                'actual' => $meeting->getMeetingDuration(),
                'attendance_rate' => $meeting->getAttendeeCount() > 0 
                    ? ($meeting->attendees()->where('attendance_status', 'joined')->count() / $meeting->getAttendeeCount()) 
                    : 0
            ];
        })->filter(fn($data) => $data['actual'] && $data['scheduled']);

        $avgScheduled = $durationData->avg('scheduled');
        $avgActual = $durationData->avg('actual');
        $avgAttendanceRate = $durationData->avg('attendance_rate');

        return [
            'average_scheduled_minutes' => round($avgScheduled, 2),
            'average_actual_minutes' => round($avgActual, 2),
            'efficiency_ratio' => $avgScheduled > 0 ? round(($avgActual / $avgScheduled) * 100, 2) : 0,
            'optimal_duration_range' => [
                'min_minutes' => round($avgActual * 0.8, 2),
                'max_minutes' => round($avgActual * 1.2, 2)
            ],
            'attendance_correlation' => round($avgAttendanceRate * 100, 2)
        ];
    }

    private function findBestMeetingTimes($meetings): array
    {
        $timeData = $meetings->groupBy(function ($meeting) {
            return $meeting->calendarEvent->starts_at->format('H');
        })->map(function ($group) {
            $attendanceRates = $group->map(function ($meeting) {
                $total = $meeting->getAttendeeCount();
                $joined = $meeting->attendees()->where('attendance_status', 'joined')->count();
                return $total > 0 ? ($joined / $total) : 0;
            });

            return [
                'meeting_count' => $group->count(),
                'average_attendance_rate' => $attendanceRates->avg(),
                'total_participants' => $group->sum(fn($m) => $m->getAttendeeCount())
            ];
        });

        return $timeData->sortByDesc('average_attendance_rate')->take(5)->toArray();
    }

    private function analyzeAttendancePatterns($meetings): array
    {
        $patterns = [
            'by_day_of_week' => [],
            'by_meeting_size' => [],
            'by_advance_notice' => []
        ];

        foreach ($meetings as $meeting) {
            $dayOfWeek = $meeting->calendarEvent->starts_at->format('l');
            $attendanceRate = $meeting->getAttendeeCount() > 0 
                ? ($meeting->attendees()->where('attendance_status', 'joined')->count() / $meeting->getAttendeeCount())
                : 0;

            $patterns['by_day_of_week'][$dayOfWeek][] = $attendanceRate;
            
            $sizeCategory = $this->getMeetingSizeCategory($meeting->getAttendeeCount());
            $patterns['by_meeting_size'][$sizeCategory][] = $attendanceRate;
        }

        // Calculate averages
        foreach ($patterns as $category => &$data) {
            foreach ($data as $key => &$rates) {
                $rates = round(array_sum($rates) / count($rates) * 100, 2);
            }
        }

        return $patterns;
    }

    private function generateQualityRecommendations($meetings): array
    {
        // Analyze common quality issues and generate recommendations
        return [
            'audio_recommendations' => [
                'Use headphones to reduce echo',
                'Ensure stable internet connection',
                'Mute when not speaking'
            ],
            'video_recommendations' => [
                'Ensure good lighting',
                'Position camera at eye level',
                'Use virtual backgrounds if needed'
            ],
            'general_recommendations' => [
                'Test audio/video before important meetings',
                'Join 5 minutes early',
                'Have backup communication method ready'
            ]
        ];
    }

    private function calculateUsageEfficiency($meetings): array
    {
        $efficiency = [
            'room_utilization' => 0,
            'time_efficiency' => 0,
            'resource_optimization' => 0
        ];

        $totalScheduledMinutes = $meetings->sum(fn($m) => $m->calendarEvent->getDurationInMinutes());
        $totalActualMinutes = $meetings->sum(fn($m) => $m->getMeetingDuration());

        if ($totalScheduledMinutes > 0) {
            $efficiency['time_efficiency'] = round(($totalActualMinutes / $totalScheduledMinutes) * 100, 2);
        }

        $totalCapacity = $meetings->sum(fn($m) => $m->getAttendeeCount());
        $totalActualAttendees = $meetings->sum(fn($m) => $m->attendees()->where('attendance_status', 'joined')->count());

        if ($totalCapacity > 0) {
            $efficiency['room_utilization'] = round(($totalActualAttendees / $totalCapacity) * 100, 2);
        }

        $efficiency['resource_optimization'] = round(($efficiency['time_efficiency'] + $efficiency['room_utilization']) / 2, 2);

        return $efficiency;
    }

    private function getMeetingSizeCategory(int $attendeeCount): string
    {
        if ($attendeeCount <= 2) return 'Small (1-2)';
        if ($attendeeCount <= 5) return 'Medium (3-5)';
        if ($attendeeCount <= 15) return 'Large (6-15)';
        return 'Very Large (15+)';
    }
}