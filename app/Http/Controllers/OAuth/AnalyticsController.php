<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:oauth.analytics.view');
    }

    public function dashboard()
    {
        $stats = $this->getOverallStats();
        $clientStats = $this->getClientStats();
        $recentActivity = $this->getRecentActivity();
        $errorRates = $this->getErrorRates();

        return Inertia::render('OAuth/Analytics', [
            'stats' => $stats,
            'clientStats' => $clientStats,
            'recentActivity' => $recentActivity,
            'errorRates' => $errorRates,
        ]);
    }

    protected function getOverallStats()
    {
        $today = now()->startOfDay();
        $lastWeek = now()->subWeek();

        return [
            'total_requests' => OAuthAuditLog::count(),
            'successful_requests' => OAuthAuditLog::successful()->count(),
            'failed_requests' => OAuthAuditLog::failed()->count(),
            'requests_today' => OAuthAuditLog::where('created_at', '>=', $today)->count(),
            'requests_this_week' => OAuthAuditLog::where('created_at', '>=', $lastWeek)->count(),
            'unique_clients' => OAuthAuditLog::distinct('client_id')->count('client_id'),
            'unique_users' => OAuthAuditLog::distinct('user_id')->count('user_id'),
            'success_rate' => $this->getSuccessRate(),
        ];
    }

    protected function getClientStats()
    {
        return OAuthAuditLog::select('client_id', DB::raw('count(*) as request_count'))
            ->with('client:id,name')
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'client_id' => $log->client_id,
                    'client_name' => $log->client->name ?? 'Unknown Client',
                    'request_count' => $log->request_count,
                    'success_rate' => $this->getClientSuccessRate($log->client_id),
                ];
            });
    }

    protected function getRecentActivity()
    {
        return OAuthAuditLog::with(['client:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'client_name' => $log->client->name ?? 'Unknown',
                    'user_name' => $log->user->name ?? 'Anonymous',
                    'success' => $log->success,
                    'error_code' => $log->error_code,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->toISOString(),
                ];
            });
    }

    protected function getErrorRates()
    {
        $last30Days = now()->subDays(30);

        return OAuthAuditLog::select(
            'event_type',
            DB::raw('count(*) as total_requests'),
            DB::raw('sum(case when success = false then 1 else 0 end) as failed_requests')
        )
            ->where('created_at', '>=', $last30Days)
            ->groupBy('event_type')
            ->get()
            ->map(function ($stat) {
                $errorRate = $stat->total_requests > 0
                    ? ($stat->failed_requests / $stat->total_requests) * 100
                    : 0;

                return [
                    'event_type' => $stat->event_type,
                    'total_requests' => $stat->total_requests,
                    'failed_requests' => $stat->failed_requests,
                    'error_rate' => round($errorRate, 2),
                ];
            });
    }

    protected function getSuccessRate()
    {
        $total = OAuthAuditLog::count();
        $successful = OAuthAuditLog::successful()->count();

        return $total > 0 ? round(($successful / $total) * 100, 2) : 100;
    }

    protected function getClientSuccessRate($clientId)
    {
        $total = OAuthAuditLog::where('client_id', $clientId)->count();
        $successful = OAuthAuditLog::where('client_id', $clientId)->successful()->count();

        return $total > 0 ? round(($successful / $total) * 100, 2) : 100;
    }

    public function chartData(Request $request)
    {
        $days = $request->query('days', 30);
        $startDate = now()->subDays($days)->startOfDay();

        $dailyStats = OAuthAuditLog::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as total'),
            DB::raw('sum(case when success = true then 1 else 0 end) as successful'),
            DB::raw('sum(case when success = false then 1 else 0 end) as failed')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'daily_stats' => $dailyStats,
            'event_breakdown' => $this->getEventBreakdown($startDate),
            'top_errors' => $this->getTopErrors($startDate),
        ]);
    }

    protected function getEventBreakdown($startDate)
    {
        return OAuthAuditLog::select('event_type', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get();
    }

    protected function getTopErrors($startDate)
    {
        return OAuthAuditLog::select('error_code', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('error_code')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }
}
