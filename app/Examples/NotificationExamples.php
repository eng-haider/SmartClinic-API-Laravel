<?php

/**
 * Notification System Usage Examples
 * 
 * This file contains practical examples of how to use the notification system
 * in your SmartClinic application.
 */

namespace App\Examples;

use App\Models\User;
use App\Models\Patient;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationExamples
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Example 1: Send a simple notification to a user
     */
    public function sendSimpleNotification()
    {
        $user = User::first();

        $notification = $this->notificationService->send(
            $user,
            'Welcome to SmartClinic',
            'Thank you for joining our clinic management system!',
            [
                'type' => Notification::TYPE_GENERAL,
                'priority' => Notification::PRIORITY_MEDIUM,
            ]
        );

        return $notification;
    }

    /**
     * Example 2: Send appointment reminder with custom data
     */
    public function sendAppointmentReminder()
    {
        $user = User::first();

        $notification = $this->notificationService->sendAppointmentReminder(
            $user,
            [
                'id' => 123,
                'date' => '2026-02-20',
                'time' => '10:00 AM',
                'doctor' => 'Dr. Smith',
                'location' => 'Room 101',
            ]
        );

        return $notification;
    }

    /**
     * Example 3: Send payment notification
     */
    public function sendPaymentNotification()
    {
        $user = User::first();

        $notification = $this->notificationService->sendPaymentNotification(
            $user,
            [
                'amount' => 5000,
                'currency' => 'IQD',
                'status' => 'completed',
                'payment_id' => 'PAY-123456',
                'method' => 'Cash',
            ]
        );

        return $notification;
    }

    /**
     * Example 4: Send notification to multiple users
     */
    public function sendToMultipleUsers()
    {
        $users = User::where('is_active', true)->limit(5)->get();

        $notifications = $this->notificationService->sendToMultiple(
            $users->all(),
            'System Maintenance',
            'System will be down for maintenance tonight at 11 PM',
            [
                'type' => Notification::TYPE_ALERT,
                'priority' => Notification::PRIORITY_HIGH,
                'data' => [
                    'start_time' => '2026-02-20 23:00:00',
                    'end_time' => '2026-02-21 02:00:00',
                    'estimated_duration' => '3 hours',
                ],
            ]
        );

        return $notifications;
    }

    /**
     * Example 5: Send notification to all doctors
     */
    public function sendToAllDoctors()
    {
        $notifications = $this->notificationService->sendToRole(
            'doctor',
            'New Patient Policy',
            'Please review the new patient intake policy in the dashboard',
            [
                'type' => Notification::TYPE_ALERT,
                'priority' => Notification::PRIORITY_MEDIUM,
                'action_url' => '/policies/patient-intake',
            ]
        );

        return $notifications;
    }

    /**
     * Example 6: Send urgent alert to all users
     */
    public function sendUrgentAlert()
    {
        $notifications = $this->notificationService->sendToAllUsers(
            'Emergency Alert',
            'Emergency protocol has been activated. Please check your email.',
            [
                'type' => Notification::TYPE_ALERT,
                'priority' => Notification::PRIORITY_URGENT,
            ]
        );

        return $notifications;
    }

    /**
     * Example 7: Get user's unread notifications
     */
    public function getUserUnreadNotifications()
    {
        $user = User::first();

        // Method 1: Using trait
        $unread = $user->unreadNotifications();
        $count = $user->unreadNotificationsCount();

        // Method 2: Using service
        $unreadViaService = $this->notificationService->getNotifications($user, [
            'is_read' => false,
        ]);

        return [
            'notifications' => $unread,
            'count' => $count,
        ];
    }

    /**
     * Example 8: Mark notifications as read
     */
    public function markNotificationsAsRead()
    {
        $user = User::first();

        // Mark single notification as read
        $notification = $user->notifications()->first();
        if ($notification) {
            $notification->markAsRead();
            // OR
            $this->notificationService->markAsRead($notification->id);
        }

        // Mark multiple notifications as read
        $notificationIds = [1, 2, 3, 4];
        $this->notificationService->markMultipleAsRead($notificationIds);

        // Mark all user's notifications as read
        $count = $user->markAllNotificationsAsRead();
        // OR
        $count = $this->notificationService->markAllAsRead($user);

        return $count;
    }

    /**
     * Example 9: Get notifications with filters
     */
    public function getFilteredNotifications()
    {
        $user = User::first();

        // Get appointment notifications only
        $appointments = $user->notificationsByType(Notification::TYPE_APPOINTMENT);

        // Get high priority notifications
        $highPriority = $user->notificationsByPriority(Notification::PRIORITY_HIGH);

        // Get unread appointment notifications (using query)
        $unreadAppointments = $user->notifications()
            ->unread()
            ->ofType(Notification::TYPE_APPOINTMENT)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get notifications from last 7 days
        $recent = $user->notifications()
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        return [
            'appointments' => $appointments,
            'high_priority' => $highPriority,
            'unread_appointments' => $unreadAppointments,
            'recent' => $recent,
        ];
    }

    /**
     * Example 10: Custom notification with rich data
     */
    public function sendCustomNotification()
    {
        $user = User::first();

        $notification = $this->notificationService->send(
            $user,
            'Lab Results Available',
            'Your blood test results are now ready to view',
            [
                'type' => Notification::TYPE_ALERT,
                'priority' => Notification::PRIORITY_HIGH,
                'data' => [
                    'test_type' => 'Complete Blood Count',
                    'test_date' => '2026-02-15',
                    'lab_name' => 'Central Medical Lab',
                    'result_id' => 'LAB-2026-001234',
                    'doctor_notes' => 'Please schedule follow-up',
                ],
                'action_url' => '/lab-results/LAB-2026-001234',
            ]
        );

        return $notification;
    }

    /**
     * Example 11: Send notification to patient model
     */
    public function sendNotificationToPatient()
    {
        // First, add HasNotifications trait to Patient model
        $patient = Patient::first();

        $notification = $this->notificationService->send(
            $patient,
            'Appointment Confirmed',
            'Your appointment has been confirmed for tomorrow at 10:00 AM',
            [
                'type' => Notification::TYPE_APPOINTMENT,
                'priority' => Notification::PRIORITY_MEDIUM,
                'data' => [
                    'appointment_id' => 123,
                    'doctor_name' => 'Dr. Ahmed',
                    'clinic_location' => 'Main Clinic',
                ],
                'action_url' => '/appointments/123',
            ]
        );

        return $notification;
    }

    /**
     * Example 12: Case status update notification
     */
    public function sendCaseStatusUpdate()
    {
        $user = User::first();

        $notification = $this->notificationService->sendCaseUpdateNotification(
            $user,
            [
                'id' => 789,
                'title' => 'Root Canal Treatment',
                'status' => 'Completed',
                'doctor' => 'Dr. Sarah',
                'completion_date' => '2026-02-17',
                'next_steps' => 'Follow-up appointment in 2 weeks',
            ]
        );

        return $notification;
    }

    /**
     * Example 13: Delete old notifications
     */
    public function deleteOldNotifications()
    {
        $user = User::first();

        // Delete all user's notifications
        $user->deleteAllNotifications();

        // Delete specific notification
        $notification = $user->notifications()->first();
        if ($notification) {
            $this->notificationService->delete($notification->id);
        }

        // Delete old read notifications (90 days)
        $deleted = Notification::where('is_read', true)
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        return $deleted;
    }

    /**
     * Example 14: Check notification status
     */
    public function checkNotificationStatus()
    {
        $user = User::first();

        // Check if user has unread notifications
        $hasUnread = $user->hasUnreadNotifications();

        // Get counts
        $totalCount = $user->notifications()->count();
        $unreadCount = $user->unreadNotificationsCount();
        $readCount = $user->notifications()->read()->count();

        // Get notification statistics
        $stats = [
            'total' => $totalCount,
            'unread' => $unreadCount,
            'read' => $readCount,
            'has_unread' => $hasUnread,
        ];

        return $stats;
    }

    /**
     * Example 15: Update OneSignal Player ID
     */
    public function updateOneSignalPlayerId()
    {
        $user = User::first();
        $playerId = 'onesignal-player-id-from-device';

        // Update player ID
        $user->setOneSignalPlayerId($playerId);

        // Get player ID
        $currentPlayerId = $user->getOneSignalPlayerId();

        return $currentPlayerId;
    }
}
