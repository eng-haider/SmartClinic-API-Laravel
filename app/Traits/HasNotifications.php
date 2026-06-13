<?php

namespace App\Traits;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotifications
{
    /**
     * Get all of the model's notifications.
     *
     * The notifiable_type column may hold either the morph-map alias (e.g.
     * "User", emitted by getMorphClass() once the map is registered) or the
     * full class name (e.g. "App\Models\User", used by rows created before the
     * map existed or by other code paths). The default morphMany only matches
     * the current alias, which silently hides legacy rows. Widen the type
     * constraint to match both representations so no notifications are lost.
     *
     * @return MorphMany
     */
    public function notifications(): MorphMany
    {
        $relation = $this->morphMany(Notification::class, 'notifiable');

        $types = array_values(array_unique([
            $this->getMorphClass(), // current alias, e.g. "User"
            static::class,           // full class name, e.g. "App\Models\User"
        ]));

        // morphMany pins notifiable_type to a single value (the current morph
        // alias) via an "=" where clause. When the column may also hold the
        // full class name, replace that constraint with an "IN (...)" so both
        // representations match instead of silently excluding one.
        if (count($types) > 1) {
            $this->replaceMorphTypeConstraint($relation, $types);
        }

        return $relation->orderBy('created_at', 'desc');
    }

    /**
     * Replace the morphMany "notifiable_type = ?" constraint with an
     * "IN (...)" over the given type values.
     *
     * @param MorphMany $relation
     * @param array $types
     * @return void
     */
    protected function replaceMorphTypeConstraint(MorphMany $relation, array $types): void
    {
        $query = $relation->getQuery()->getQuery();
        $morphType = $relation->getQualifiedMorphType();

        // Drop the auto-added "notifiable_type = <alias>" basic where + binding.
        $removedBindings = [];
        $query->wheres = array_values(array_filter(
            $query->wheres,
            function ($where) use ($morphType, &$removedBindings) {
                $isMorphType = ($where['type'] ?? null) === 'Basic'
                    && ($where['column'] ?? null) === $morphType;

                if ($isMorphType && array_key_exists('value', $where)) {
                    $removedBindings[] = $where['value'];
                }

                return !$isMorphType;
            }
        ));

        // Remove the matching where-bindings so they don't shift the IN values.
        if (!empty($removedBindings)) {
            $bindings = $query->getRawBindings()['where'] ?? [];
            foreach ($removedBindings as $value) {
                $pos = array_search($value, $bindings, true);
                if ($pos !== false) {
                    unset($bindings[$pos]);
                }
            }
            $query->setBindings(array_values($bindings), 'where');
        }

        $relation->whereIn($morphType, $types);
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
