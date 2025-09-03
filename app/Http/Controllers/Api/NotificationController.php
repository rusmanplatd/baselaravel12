<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user notifications
     */
    public function index(Request $request): JsonResponse
    {
        // Mock notifications for demonstration
        // In production, this would fetch from database
        $notifications = [
            [
                'id' => '1',
                'title' => 'Welcome to the platform!',
                'message' => 'Your account has been successfully created and verified.',
                'type' => 'success',
                'read' => false,
                'createdAt' => now()->subMinutes(5)->toISOString(),
                'actionUrl' => null,
            ],
            [
                'id' => '2',
                'title' => 'Security Update',
                'message' => 'Please review your security settings and enable two-factor authentication.',
                'type' => 'warning',
                'read' => false,
                'createdAt' => now()->subMinutes(30)->toISOString(),
                'actionUrl' => '/settings/security',
            ],
            [
                'id' => '3',
                'title' => 'New chat message',
                'message' => 'You have a new message from John Doe in the Project Alpha chat.',
                'type' => 'info',
                'read' => true,
                'createdAt' => now()->subHours(2)->toISOString(),
                'actionUrl' => '/chat',
            ],
        ];

        return response()->json([
            'data' => $notifications,
            'unreadCount' => collect($notifications)->where('read', false)->count(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'id' => 'required|string',
        ]);

        // In production, update the notification in database
        // For now, just return success
        
        return response()->json([
            'message' => 'Notification marked as read',
            'id' => $id,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        // In production, update all user notifications in database
        
        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // In production, delete the notification from database
        
        return response()->json([
            'message' => 'Notification deleted',
            'id' => $id,
        ]);
    }

    /**
     * Create a new notification (for testing)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:info,success,warning,error',
            'actionUrl' => 'nullable|url',
        ]);

        $notification = [
            'id' => (string) time(),
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type,
            'read' => false,
            'createdAt' => now()->toISOString(),
            'actionUrl' => $request->actionUrl,
        ];

        return response()->json([
            'message' => 'Notification created',
            'data' => $notification,
        ], 201);
    }
}