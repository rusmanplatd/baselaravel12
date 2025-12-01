<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecurityAuditController extends Controller
{
    public function __construct(
        private SecurityAuditService $securityAuditService
    ) {
        $this->middleware('auth:api');
        $this->middleware('permission:security.audit.view')->except(['dashboard']);
        $this->middleware('permission:security.audit.manage')->only(['resolve', 'investigate']);
        $this->middleware('throttle:60,1');
    }

    /**
     * Get security dashboard overview
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        
        // Get recent security events
        $recentEvents = SecurityAuditLog::when($organizationId, 
                fn($query) => $query->where('organization_id', $organizationId)
            )
            ->when(!$user->can('security.audit.view'), 
                fn($query) => $query->where('user_id', $user->id)
            )
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Get security metrics
        $timeWindow = Carbon::now()->subDays(7);
        $metrics = [
            'total_events' => SecurityAuditLog::when($organizationId, 
                    fn($query) => $query->where('organization_id', $organizationId)
                )
                ->where('created_at', '>=', $timeWindow)
                ->count(),
            'high_risk_events' => SecurityAuditLog::when($organizationId,
                    fn($query) => $query->where('organization_id', $organizationId)
                )
                ->where('risk_score', '>=', 8)
                ->where('created_at', '>=', $timeWindow)
                ->count(),
            'unresolved_events' => SecurityAuditLog::when($organizationId,
                    fn($query) => $query->where('organization_id', $organizationId)
                )
                ->where('status', 'pending')
                ->count(),
        ];

        // Detect user anomalies
        $anomalies = $this->securityAuditService->detectAnomalies($user);

        return response()->json([
            'metrics' => $metrics,
            'recent_events' => $recentEvents,
            'user_anomalies' => $anomalies,
            'security_score' => $this->calculateSecurityScore($organizationId),
        ]);
    }

    /**
     * Get security audit logs
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'event_type' => 'string',
            'severity' => 'in:info,low,medium,high,critical',
            'status' => 'in:normal,pending,investigating,resolved,false_positive',
            'user_id' => 'exists:sys_users,id',
            'risk_score_min' => 'integer|min:0|max:10',
            'risk_score_max' => 'integer|min:0|max:10',
            'from' => 'date',
            'to' => 'date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        
        $query = SecurityAuditLog::with(['user:id,name', 'device:id,name', 'resolvedBy:id,name'])
            ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->when(!$user->can('security.audit.view'), fn($q) => $q->where('user_id', $user->id))
            ->when($request->event_type, fn($q) => $q->where('event_type', $request->event_type))
            ->when($request->severity, fn($q) => $q->where('severity', $request->severity))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->risk_score_min, fn($q) => $q->where('risk_score', '>=', $request->risk_score_min))
            ->when($request->risk_score_max, fn($q) => $q->where('risk_score', '<=', $request->risk_score_max))
            ->when($request->from, fn($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->where('created_at', '<=', $request->to))
            ->orderByDesc('created_at');

        $auditLogs = $query->paginate($request->input('limit', 50));

        return response()->json([
            'audit_logs' => $auditLogs->items(),
            'pagination' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
            ],
        ]);
    }

    /**
     * Get specific audit log details
     */
    public function show(Request $request, string $auditLogId): JsonResponse
    {
        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        
        $auditLog = SecurityAuditLog::with([
                'user:id,name,email', 
                'device:id,name,device_type', 
                'conversation:id,name,type',
                'resolvedBy:id,name'
            ])
            ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->when(!$user->can('security.audit.view'), fn($q) => $q->where('user_id', $user->id))
            ->findOrFail($auditLogId);

        return response()->json(['audit_log' => $auditLog]);
    }

    /**
     * Mark audit log as investigating
     */
    public function investigate(Request $request, string $auditLogId): JsonResponse
    {
        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        
        $auditLog = SecurityAuditLog::when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->findOrFail($auditLogId);

        $auditLog->update([
            'status' => 'investigating',
            'resolved_by' => $user->id,
        ]);

        return response()->json([
            'audit_log' => $auditLog,
            'message' => 'Audit log marked as investigating',
        ]);
    }

    /**
     * Resolve audit log
     */
    public function resolve(Request $request, string $auditLogId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution' => 'required|in:resolved,false_positive',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        
        $auditLog = SecurityAuditLog::when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->findOrFail($auditLogId);

        $auditLog->update([
            'status' => $request->resolution,
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'metadata' => array_merge($auditLog->metadata ?? [], [
                'resolution_notes' => $request->notes,
            ]),
        ]);

        return response()->json([
            'audit_log' => $auditLog,
            'message' => 'Audit log resolved successfully',
        ]);
    }

    /**
     * Get security report
     */
    public function report(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        if (!$organizationId) {
            return response()->json(['error' => 'Organization context required'], 400);
        }

        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $report = $this->securityAuditService->generateSecurityReport($organizationId, $from, $to);

        return response()->json([
            'report' => $report,
            'period' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
            ],
        ]);
    }

    /**
     * Get available event types
     */
    public function eventTypes(): JsonResponse
    {
        $eventTypes = collect([
            // Authentication events
            'auth.login.success',
            'auth.login.failed', 
            'auth.login.brute_force',
            'auth.logout',
            'auth.password.changed',
            'auth.mfa.enabled',
            'auth.mfa.disabled',
            'auth.mfa.failed',
            
            // Device events
            'device.registered',
            'device.trusted',
            'device.untrusted', 
            'device.revoked',
            'device.suspicious.access',
            'device.unknown.location',
            
            // E2EE events
            'e2ee.key.generated',
            'e2ee.key.rotated',
            'e2ee.key.compromised',
            'e2ee.encryption.failed',
            'e2ee.decryption.failed',
            'e2ee.algorithm.downgrade',
            
            // Chat security events
            'chat.conversation.created',
            'chat.participant.added',
            'chat.participant.removed', 
            'chat.message.blocked',
            'chat.file.blocked',
            'chat.suspicious.activity',
            
            // System events
            'system.backup.accessed',
            'system.admin.access',
            'system.config.changed',
            'system.vulnerability.detected',
            
            // API security events
            'api.rate_limit.exceeded',
            'api.unauthorized.access',
            'api.token.compromised',
            'api.suspicious.patterns',
        ])->map(function ($eventType) {
            [$category, $action, $detail] = array_pad(explode('.', $eventType), 3, null);
            
            return [
                'event_type' => $eventType,
                'category' => $category,
                'action' => $action,
                'detail' => $detail,
                'description' => $this->getEventDescription($eventType),
            ];
        })->groupBy('category');

        return response()->json(['event_types' => $eventTypes]);
    }

    /**
     * Calculate organization security score
     */
    private function calculateSecurityScore(?string $organizationId): int
    {
        $timeWindow = Carbon::now()->subDays(30);
        
        $recentEvents = SecurityAuditLog::when($organizationId, 
                fn($query) => $query->where('organization_id', $organizationId)
            )
            ->where('created_at', '>=', $timeWindow)
            ->get();

        if ($recentEvents->isEmpty()) {
            return 100; // Perfect score if no events
        }

        $totalRisk = $recentEvents->sum('risk_score');
        $averageRisk = $totalRisk / $recentEvents->count();
        
        // Calculate score (inverse of risk, scaled to 0-100)
        $score = max(0, 100 - ($averageRisk * 10));
        
        // Adjust for unresolved high-risk events
        $unresolvedHighRisk = $recentEvents->where('risk_score', '>=', 8)
            ->where('status', '!=', 'resolved')
            ->count();
        
        $score -= ($unresolvedHighRisk * 5);
        
        return max(0, min(100, (int) $score));
    }

    /**
     * Get event description
     */
    private function getEventDescription(string $eventType): string
    {
        $descriptions = [
            'auth.login.success' => 'Successful user authentication',
            'auth.login.failed' => 'Failed authentication attempt',
            'auth.login.brute_force' => 'Multiple failed login attempts detected',
            'device.registered' => 'New device registered to user account',
            'device.suspicious.access' => 'Access from suspicious or unknown device',
            'e2ee.key.compromised' => 'Encryption key potentially compromised',
            'api.rate_limit.exceeded' => 'API rate limit exceeded',
            'system.vulnerability.detected' => 'Security vulnerability detected',
        ];

        return $descriptions[$eventType] ?? 'Security event';
    }
}