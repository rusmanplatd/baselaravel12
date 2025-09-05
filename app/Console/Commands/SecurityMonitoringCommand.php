<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:monitor 
                        {--days=1 : Number of days to analyze} 
                        {--organization= : Organization ID to filter}
                        {--high-risk-only : Show only high-risk events}
                        {--export= : Export report to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor security events and generate security reports';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\SecurityAuditService $securityAuditService)
    {
        $days = $this->option('days');
        $organizationId = $this->option('organization');
        $highRiskOnly = $this->option('high-risk-only');
        $exportFile = $this->option('export');
        
        $this->info("🔍 Security Monitoring Report");
        $this->info("Analyzing security events from the last {$days} day(s)");
        
        if ($organizationId) {
            $this->info("Organization filter: {$organizationId}");
        }
        
        $this->newLine();
        
        // Get security events
        $query = \App\Models\SecurityAuditLog::whereBetween('created_at', [
            now()->subDays($days),
            now()
        ]);
        
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }
        
        if ($highRiskOnly) {
            $query->where('risk_score', '>=', 8);
        }
        
        $events = $query->with(['user:id,name', 'device:id,device_name'])
            ->orderByDesc('risk_score')
            ->orderByDesc('created_at')
            ->get();
        
        if ($events->isEmpty()) {
            $this->info("✅ No security events found for the specified criteria.");
            return 0;
        }
        
        // Summary statistics
        $this->displaySummary($events);
        $this->newLine();
        
        // Risk distribution
        $this->displayRiskDistribution($events);
        $this->newLine();
        
        // High-risk events
        $this->displayHighRiskEvents($events);
        $this->newLine();
        
        // Event type breakdown
        $this->displayEventBreakdown($events);
        $this->newLine();
        
        // Top users by risk
        $this->displayTopUsersByRisk($events);
        
        // Export if requested
        if ($exportFile) {
            $this->exportReport($events, $exportFile, $days, $organizationId);
        }
        
        return 0;
    }
    
    private function displaySummary($events)
    {
        $this->info("📊 SUMMARY");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Events', number_format($events->count())],
                ['Critical Events', number_format($events->where('severity', 'critical')->count())],
                ['High Risk Events', number_format($events->where('risk_score', '>=', 8)->count())],
                ['Unresolved Events', number_format($events->where('status', 'pending')->count())],
                ['Average Risk Score', number_format($events->avg('risk_score'), 2)],
                ['Unique Users Affected', number_format($events->whereNotNull('user_id')->pluck('user_id')->unique()->count())],
            ]
        );
    }
    
    private function displayRiskDistribution($events)
    {
        $this->info("⚠️  RISK DISTRIBUTION");
        $distribution = [
            ['Critical (9-10)', $events->where('risk_score', '>=', 9)->count(), '🔴'],
            ['High (7-8)', $events->whereBetween('risk_score', [7, 8])->count(), '🟠'],
            ['Medium (4-6)', $events->whereBetween('risk_score', [4, 6])->count(), '🟡'],
            ['Low (1-3)', $events->whereBetween('risk_score', [1, 3])->count(), '🟢'],
            ['Info (0)', $events->where('risk_score', 0)->count(), '⚪'],
        ];
        
        $this->table(['Risk Level', 'Count', ''], $distribution);
    }
    
    private function displayHighRiskEvents($events)
    {
        $highRiskEvents = $events->where('risk_score', '>=', 8)->take(10);
        
        if ($highRiskEvents->isEmpty()) {
            return;
        }
        
        $this->error("🚨 HIGH RISK EVENTS (Top 10)");
        $tableData = $highRiskEvents->map(function ($event) {
            return [
                $event->created_at->format('Y-m-d H:i'),
                $event->event_type,
                $event->risk_score,
                $event->user ? $event->user->name : 'System',
                $event->device ? $event->device->device_name : '-',
                $event->status,
            ];
        })->toArray();
        
        $this->table(
            ['Time', 'Event Type', 'Risk', 'User', 'Device', 'Status'],
            $tableData
        );
    }
    
    private function displayEventBreakdown($events)
    {
        $this->info("📋 EVENT TYPE BREAKDOWN");
        $breakdown = $events->groupBy('event_type')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'avg_risk' => round($group->avg('risk_score'), 2),
                    'max_risk' => $group->max('risk_score'),
                ];
            })
            ->sortByDesc('count')
            ->take(10);
        
        $tableData = $breakdown->map(function ($data, $eventType) {
            return [
                $eventType,
                $data['count'],
                $data['avg_risk'],
                $data['max_risk'],
            ];
        })->toArray();
        
        $this->table(
            ['Event Type', 'Count', 'Avg Risk', 'Max Risk'],
            $tableData
        );
    }
    
    private function displayTopUsersByRisk($events)
    {
        $this->info("👥 TOP USERS BY RISK");
        $userRisks = $events->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(function ($userEvents) {
                return [
                    'name' => $userEvents->first()->user->name ?? 'Unknown',
                    'event_count' => $userEvents->count(),
                    'total_risk' => $userEvents->sum('risk_score'),
                    'avg_risk' => round($userEvents->avg('risk_score'), 2),
                    'max_risk' => $userEvents->max('risk_score'),
                ];
            })
            ->sortByDesc('total_risk')
            ->take(10);
        
        $tableData = $userRisks->map(function ($data) {
            return [
                $data['name'],
                $data['event_count'],
                $data['total_risk'],
                $data['avg_risk'],
                $data['max_risk'],
            ];
        })->toArray();
        
        $this->table(
            ['User', 'Events', 'Total Risk', 'Avg Risk', 'Max Risk'],
            $tableData
        );
    }
    
    private function exportReport($events, $filename, $days, $organizationId)
    {
        $report = [
            'generated_at' => now()->toISOString(),
            'period_days' => $days,
            'organization_id' => $organizationId,
            'summary' => [
                'total_events' => $events->count(),
                'critical_events' => $events->where('severity', 'critical')->count(),
                'high_risk_events' => $events->where('risk_score', '>=', 8)->count(),
                'unresolved_events' => $events->where('status', 'pending')->count(),
                'average_risk_score' => $events->avg('risk_score'),
            ],
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'severity' => $event->severity,
                    'risk_score' => $event->risk_score,
                    'user_name' => $event->user?->name,
                    'device_name' => $event->device?->device_name,
                    'status' => $event->status,
                    'created_at' => $event->created_at->toISOString(),
                    'metadata' => $event->metadata,
                ];
            })->toArray(),
        ];
        
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("📁 Report exported to: {$filename}");
    }
}
