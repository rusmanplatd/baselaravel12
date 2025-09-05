<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\AbuseReport;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AbuseReportController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reported_user_id' => 'nullable|string|exists:sys_users,id',
            'conversation_id' => 'nullable|string|exists:chat_conversations,id',
            'message_id' => 'nullable|string|exists:chat_messages,id',
            'abuse_type' => 'required|string|in:spam,harassment,inappropriate,malware,impersonation,phishing',
            'description' => 'nullable|string|max:1000',
            'evidence' => 'nullable|array',
            'evidence.*.type' => 'required_with:evidence|string|in:screenshot,text,url,file',
            'evidence.*.content' => 'required_with:evidence|string',
            'evidence.*.description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $data = $validator->validated();

        // Validate relationships if provided
        if (isset($data['conversation_id'])) {
            $conversation = Conversation::find($data['conversation_id']);
            if (! $conversation || ! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'error' => 'You are not a participant in this conversation',
                ], 403);
            }
        }

        if (isset($data['message_id'])) {
            $message = Message::find($data['message_id']);
            if (! $message) {
                return response()->json([
                    'error' => 'Message not found',
                ], 404);
            }

            // Auto-set conversation and reported user from message
            $data['conversation_id'] = $message->conversation_id;
            $data['reported_user_id'] = $message->user_id;
        }

        // Create the abuse report
        $report = AbuseReport::create([
            'reporter_user_id' => $user->id,
            'reported_user_id' => $data['reported_user_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'abuse_type' => $data['abuse_type'],
            'description' => $data['description'] ?? null,
            'evidence' => $data['evidence'] ?? null,
            'status' => 'pending',
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Abuse report submitted successfully',
            'report_id' => $report->id,
            'status' => $report->status,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only allow users to see their own reports unless they're admin/moderator
        $query = AbuseReport::where('reporter_user_id', $user->id);

        // Admin users can see all reports
        if ($user->can('moderate_chat')) {
            $query = AbuseReport::query();

            // Add filters for admin view
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('abuse_type')) {
                $query->where('abuse_type', $request->input('abuse_type'));
            }

            if ($request->has('reported_user_id')) {
                $query->where('reported_user_id', $request->input('reported_user_id'));
            }
        }

        $reports = $query->with(['reportedUser', 'conversation', 'message', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'reports' => $reports->items(),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    public function show(string $reportId): JsonResponse
    {
        $user = Auth::user();

        $report = AbuseReport::with(['reportedUser', 'reporter', 'conversation', 'message', 'reviewer'])
            ->find($reportId);

        if (! $report) {
            return response()->json(['error' => 'Report not found'], 404);
        }

        // Users can only view their own reports unless they're admin/moderator
        if ($report->reporter_user_id !== $user->id && ! $user->can('moderate_chat')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['report' => $report]);
    }

    public function review(Request $request, string $reportId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('moderate_chat')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:reviewed,resolved,dismissed',
            'resolution_notes' => 'nullable|string|max:1000',
            'is_false_positive' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $report = AbuseReport::find($reportId);
        if (! $report) {
            return response()->json(['error' => 'Report not found'], 404);
        }

        $data = $validator->validated();

        $report->update([
            'status' => $data['status'],
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // If marked as false positive, update the flag
        if (isset($data['is_false_positive'])) {
            $report->update(['is_false_positive' => $data['is_false_positive']]);
        }

        return response()->json([
            'message' => 'Report reviewed successfully',
            'report' => $report->fresh(['reviewer']),
        ]);
    }

    public function getStats(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->can('moderate_chat')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_reports' => AbuseReport::count(),
            'pending_reports' => AbuseReport::pending()->count(),
            'resolved_reports' => AbuseReport::resolved()->count(),
            'dismissed_reports' => AbuseReport::dismissed()->count(),
            'false_positives' => AbuseReport::where('is_false_positive', true)->count(),
            'reports_by_type' => AbuseReport::selectRaw('abuse_type, count(*) as count')
                ->groupBy('abuse_type')
                ->pluck('count', 'abuse_type'),
            'reports_last_24h' => AbuseReport::where('created_at', '>=', now()->subDay())->count(),
            'urgent_reports' => AbuseReport::where('abuse_type', 'malware')
                ->orWhere('abuse_type', 'harassment')
                ->where('status', 'pending')
                ->count(),
        ];

        return response()->json(['stats' => $stats]);
    }
}
