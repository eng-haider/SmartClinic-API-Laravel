<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Berkayk\OneSignal\OneSignalFacade as OneSignal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a user or model.
     *
     * @param Model $notifiable The model to notify (User, Patient, etc.)
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $options Additional options
     * @return Notification|null
     */
    public function send(
        Model $notifiable,
        string $title,
        string $body,
        array $options = []
    ): ?Notification {
        try {
            // Create notification in database
            $notification = $this->createNotification($notifiable, $title, $body, $options);

            // Send via OneSignal if player ID exists
            if ($notifiable->getOneSignalPlayerId()) {
                $this->sendViaOneSignal($notification, $notifiable);
            }

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'error' => $e->getMessage(),
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id,
            ]);
            return null;
        }
    }

    /**
     * Send notification to multiple recipients.
     *
     * @param array $notifiables Array of models to notify
     * @param string $title
     * @param string $body
     * @param array $options
     * @return array Array of created notifications
     */
    public function sendToMultiple(
        array $notifiables,
        string $title,
        string $body,
        array $options = []
    ): array {
        $notifications = [];

        foreach ($notifiables as $notifiable) {
            if ($notifiable instanceof Model) {
                $notification = $this->send($notifiable, $title, $body, $options);
                if ($notification) {
                    $notifications[] = $notification;
                }
            }
        }

        return $notifications;
    }

    /**
     * Send notification to all users with specific role.
     *
     * @param string $role Role name
     * @param string $title
     * @param string $body
     * @param array $options
     * @return array
     */
    public function sendToRole(
        string $role,
        string $title,
        string $body,
        array $options = []
    ): array {
        $users = User::role($role)->get();
        return $this->sendToMultiple($users->all(), $title, $body, $options);
    }

    /**
     * Send notification to all active users.
     *
     * @param string $title
     * @param string $body
     * @param array $options
     * @return array
     */
    public function sendToAllUsers(
        string $title,
        string $body,
        array $options = []
    ): array {
        $users = User::where('is_active', true)->get();
        return $this->sendToMultiple($users->all(), $title, $body, $options);
    }

    /**
     * Create notification record in database.
     *
     * @param Model $notifiable
     * @param string $title
     * @param string $body
     * @param array $options
     * @return Notification
     */
    protected function createNotification(
        Model $notifiable,
        string $title,
        string $body,
        array $options = []
    ): Notification {
        return Notification::create([
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'title' => $title,
            'body' => $body,
            'type' => $options['type'] ?? Notification::TYPE_GENERAL,
            'data' => $options['data'] ?? null,
            'sender_id' => $options['sender_id'] ?? (Auth::check() ? Auth::id() : null),
            'priority' => $options['priority'] ?? Notification::PRIORITY_MEDIUM,
            'action_url' => $options['action_url'] ?? null,
            'onesignal_status' => Notification::ONESIGNAL_PENDING,
        ]);
    }

    /**
     * Send notification via OneSignal.
     *
     * @param Notification $notification
     * @param Model $notifiable
     * @return void
     */
    protected function sendViaOneSignal(Notification $notification, Model $notifiable): void
    {
        try {
            $playerId = $notifiable->getOneSignalPlayerId();
            
            if (!$playerId) {
                return;
            }

            // Prepare OneSignal notification data
            $params = [
                'include_player_ids' => [$playerId],
                'headings' => ['en' => $notification->title],
                'contents' => ['en' => $notification->body],
                'data' => array_merge(
                    $notification->data ?? [],
                    [
                        'notification_id' => $notification->id,
                        'type' => $notification->type,
                        'action_url' => $notification->action_url,
                    ]
                ),
            ];

            // Add priority-based settings
            $params = $this->addPrioritySettings($params, $notification->priority);

            // Add action URL if exists
            if ($notification->action_url) {
                $params['url'] = $notification->action_url;
            }

            // Send via OneSignal
            $response = OneSignal::sendNotificationCustom($params);

            // Update notification with OneSignal response
            if (isset($response['id'])) {
                $notification->update([
                    'onesignal_notification_id' => $response['id'],
                    'onesignal_status' => Notification::ONESIGNAL_SENT,
                ]);
            } else {
                $notification->update([
                    'onesignal_status' => Notification::ONESIGNAL_FAILED,
                    'onesignal_error' => json_encode($response),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('OneSignal notification failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            $notification->update([
                'onesignal_status' => Notification::ONESIGNAL_FAILED,
                'onesignal_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add priority-based settings to OneSignal params.
     *
     * @param array $params
     * @param string $priority
     * @return array
     */
    protected function addPrioritySettings(array $params, string $priority): array
    {
        switch ($priority) {
            case Notification::PRIORITY_URGENT:
                $params['priority'] = 10;
                $params['android_channel_id'] = 'urgent-notifications';
                break;
            case Notification::PRIORITY_HIGH:
                $params['priority'] = 9;
                $params['android_channel_id'] = 'high-priority-notifications';
                break;
            case Notification::PRIORITY_MEDIUM:
                $params['priority'] = 5;
                break;
            case Notification::PRIORITY_LOW:
                $params['priority'] = 3;
                break;
        }

        return $params;
    }

    /**
     * Mark notification as read.
     *
     * @param int $notificationId
     * @return bool
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);
        return $notification ? $notification->markAsRead() : false;
    }

    /**
     * Mark multiple notifications as read.
     *
     * @param array $notificationIds
     * @return int Number of notifications marked as read
     */
    public function markMultipleAsRead(array $notificationIds): int
    {
        return Notification::whereIn('id', $notificationIds)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark all notifications for a user as read.
     *
     * @param Model $notifiable
     * @return int
     */
    public function markAllAsRead(Model $notifiable): int
    {
        return $notifiable->markAllNotificationsAsRead();
    }

    /**
     * Delete notification.
     *
     * @param int $notificationId
     * @return bool
     */
    public function delete(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);
        return $notification ? $notification->delete() : false;
    }

    /**
     * Get notifications for a model.
     *
     * @param Model $notifiable
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getNotifications(Model $notifiable, array $filters = [])
    {
        $query = $notifiable->notifications();

        // Apply filters
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['is_read'])) {
            $filters['is_read'] ? $query->read() : $query->unread();
        }

        if (isset($filters['priority'])) {
            $query->ofPriority($filters['priority']);
        }

        if (isset($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->get();
    }

    /**
     * Send appointment reminder notification.
     *
     * @param Model $notifiable
     * @param array $appointmentData
     * @return Notification|null
     */
    public function sendAppointmentReminder(Model $notifiable, array $appointmentData): ?Notification
    {
        return $this->send(
            $notifiable,
            'Appointment Reminder',
            "You have an appointment on {$appointmentData['date']} at {$appointmentData['time']}",
            [
                'type' => Notification::TYPE_REMINDER,
                'priority' => Notification::PRIORITY_HIGH,
                'data' => $appointmentData,
                'action_url' => "/appointments/{$appointmentData['id']}",
            ]
        );
    }

    /**
     * Send payment notification.
     *
     * @param Model $notifiable
     * @param array $paymentData
     * @return Notification|null
     */
    public function sendPaymentNotification(Model $notifiable, array $paymentData): ?Notification
    {
        return $this->send(
            $notifiable,
            'Payment Notification',
            "Payment of {$paymentData['amount']} has been {$paymentData['status']}",
            [
                'type' => Notification::TYPE_PAYMENT,
                'priority' => Notification::PRIORITY_MEDIUM,
                'data' => $paymentData,
            ]
        );
    }

    /**
     * Send case update notification.
     *
     * @param Model $notifiable
     * @param array $caseData
     * @return Notification|null
     */
    public function sendCaseUpdateNotification(Model $notifiable, array $caseData): ?Notification
    {
        return $this->send(
            $notifiable,
            'Case Updated',
            "Your case '{$caseData['title']}' has been updated",
            [
                'type' => Notification::TYPE_CASE,
                'priority' => Notification::PRIORITY_MEDIUM,
                'data' => $caseData,
                'action_url' => "/cases/{$caseData['id']}",
            ]
        );
    }
}
