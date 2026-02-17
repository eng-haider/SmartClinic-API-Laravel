<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all notifications for authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = [
            'type' => $request->input('type'),
            'is_read' => $request->input('is_read'),
            'priority' => $request->input('priority'),
            'limit' => $request->input('limit', 50),
        ];

        $notifications = $this->notificationService->getNotifications($user, array_filter($filters));

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $user->unreadNotificationsCount(),
        ]);
    }

    /**
     * Get a specific notification.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        // Check if user is authorized to view this notification
        $user = Auth::user();
        if ($notification->notifiable_id !== $user->id || 
            $notification->notifiable_type !== get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }

    /**
     * Send a notification (admin or authorized users only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notifiable_type' => 'required|string',
            'notifiable_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string|in:general,appointment,payment,case,reminder,alert',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'data' => 'nullable|array',
            'action_url' => 'nullable|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $notifiableClass = $request->input('notifiable_type');
            $notifiable = $notifiableClass::find($request->input('notifiable_id'));

            if (!$notifiable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifiable entity not found',
                ], 404);
            }

            $notification = $this->notificationService->send(
                $notifiable,
                $request->input('title'),
                $request->input('body'),
                [
                    'type' => $request->input('type', Notification::TYPE_GENERAL),
                    'priority' => $request->input('priority', Notification::PRIORITY_MEDIUM),
                    'data' => $request->input('data'),
                    'action_url' => $request->input('action_url'),
                    'sender_id' => Auth::id(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => $notification,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark notification as read.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        // Check if user is authorized
        $user = Auth::user();
        if ($notification->notifiable_id !== $user->id || 
            $notification->notifiable_type !== get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->notificationService->markAsRead($id);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark multiple notifications as read.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'integer|exists:notifications,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $count = $this->notificationService->markMultipleAsRead($request->input('notification_ids'));

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marked as read",
            'count' => $count,
        ]);
    }

    /**
     * Mark all notifications as read for authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->notificationService->markAllAsRead($user);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => $count,
        ]);
    }

    /**
     * Delete a notification.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        // Check if user is authorized
        $user = Auth::user();
        if ($notification->notifiable_id !== $user->id || 
            $notification->notifiable_type !== get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->notificationService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Get unread notifications count.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->unreadNotificationsCount();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Update OneSignal player ID for authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePlayerID(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $user->setOneSignalPlayerId($request->input('player_id'));

        return response()->json([
            'success' => true,
            'message' => 'Player ID updated successfully',
        ]);
    }

    /**
     * Send test notification (for testing purposes).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();

        $notification = $this->notificationService->send(
            $user,
            'Test Notification',
            'This is a test notification from SmartClinic',
            [
                'type' => Notification::TYPE_GENERAL,
                'priority' => Notification::PRIORITY_LOW,
                'data' => ['test' => true],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent',
            'data' => $notification,
        ]);
    }

    /**
     * Get notification statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'total' => $user->notifications()->count(),
            'unread' => $user->notifications()->unread()->count(),
            'read' => $user->notifications()->read()->count(),
            'by_type' => [
                'general' => $user->notifications()->ofType(Notification::TYPE_GENERAL)->count(),
                'appointment' => $user->notifications()->ofType(Notification::TYPE_APPOINTMENT)->count(),
                'payment' => $user->notifications()->ofType(Notification::TYPE_PAYMENT)->count(),
                'case' => $user->notifications()->ofType(Notification::TYPE_CASE)->count(),
                'reminder' => $user->notifications()->ofType(Notification::TYPE_REMINDER)->count(),
                'alert' => $user->notifications()->ofType(Notification::TYPE_ALERT)->count(),
            ],
            'by_priority' => [
                'low' => $user->notifications()->ofPriority(Notification::PRIORITY_LOW)->count(),
                'medium' => $user->notifications()->ofPriority(Notification::PRIORITY_MEDIUM)->count(),
                'high' => $user->notifications()->ofPriority(Notification::PRIORITY_HIGH)->count(),
                'urgent' => $user->notifications()->ofPriority(Notification::PRIORITY_URGENT)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }
}
