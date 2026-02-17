<?php

namespace App\Traits;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotifications
{
    /**
     * Get all of the model's notifications.
     *
     * @return MorphMany
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get unread notifications.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function unreadNotifications()
    {
        return $this->notifications()->unread()->get();
    }

    /**
     * Get read notifications.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function readNotifications()
    {
        return $this->notifications()->read()->get();
    }

    /**
     * Get unread notifications count.
     *
     * @return int
     */
    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Mark all notifications as read.
     *
     * @return int Number of notifications marked as read
     */
    public function markAllNotificationsAsRead(): int
    {
        return $this->notifications()->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Delete all notifications.
     *
     * @return bool|null
     */
    public function deleteAllNotifications(): ?bool
    {
        return $this->notifications()->delete();
    }

    /**
     * Get notifications by type.
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function notificationsByType(string $type)
    {
        return $this->notifications()->ofType($type)->get();
    }

    /**
     * Get notifications by priority.
     *
     * @param string $priority
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function notificationsByPriority(string $priority)
    {
        return $this->notifications()->ofPriority($priority)->get();
    }

    /**
     * Check if model has unread notifications.
     *
     * @return bool
     */
    public function hasUnreadNotifications(): bool
    {
        return $this->notifications()->unread()->exists();
    }

    /**
     * Get the OneSignal player ID for this model.
     * Override this method in your model if the column name is different.
     *
     * @return string|null
     */
    public function getOneSignalPlayerId(): ?string
    {
        return $this->onesignal_player_id ?? null;
    }

    /**
     * Set the OneSignal player ID for this model.
     * Override this method in your model if the column name is different.
     *
     * @param string|null $playerId
     * @return bool
     */
    public function setOneSignalPlayerId(?string $playerId): bool
    {
        if (property_exists($this, 'onesignal_player_id') || in_array('onesignal_player_id', $this->fillable)) {
            $this->onesignal_player_id = $playerId;
            return $this->save();
        }
        
        return false;
    }
}
