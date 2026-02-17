# Notification System Quick Reference

## 🚀 Quick Start

### 1. Run Migrations

```bash
# Migrate all tenants
php artisan tenants:migrate
```

### 2. Update User Model (Already Done)

The User model already has the `HasNotifications` trait and `onesignal_player_id` field.

### 3. Send Your First Notification

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

$notification = $notificationService->send(
    $user,
    'Welcome!',
    'Welcome to SmartClinic',
    ['type' => Notification::TYPE_GENERAL]
);
```

## 📝 Common Code Snippets

### Send Appointment Reminder

```php
$notificationService->sendAppointmentReminder($patient, [
    'id' => 123,
    'date' => '2026-02-20',
    'time' => '10:00 AM',
]);
```

### Send to Multiple Users

```php
$users = User::where('is_active', true)->get();
$notificationService->sendToMultiple(
    $users->all(),
    'System Update',
    'System will be down for maintenance tonight'
);
```

### Send to Role

```php
$notificationService->sendToRole(
    'doctor',
    'New Policy',
    'Please review the new clinic policy'
);
```

### Get User Notifications

```php
// Get all notifications
$notifications = $user->notifications;

// Get unread only
$unread = $user->unreadNotifications();

// Get unread count
$count = $user->unreadNotificationsCount();

// Check if has unread
if ($user->hasUnreadNotifications()) {
    // ...
}
```

### Mark as Read

```php
// Single notification
$notification->markAsRead();

// All user notifications
$user->markAllNotificationsAsRead();

// Via service
$notificationService->markAsRead($notificationId);
$notificationService->markAllAsRead($user);
```

## 🔌 API Quick Reference

### Get Notifications

```
GET /api/notifications
GET /api/notifications?type=appointment&is_read=0
```

### Unread Count

```
GET /api/notifications/unread-count
```

### Mark as Read

```
PATCH /api/notifications/{id}/mark-read
POST /api/notifications/mark-all-read
```

### Update Player ID

```
POST /api/notifications/player-id
Body: { "player_id": "abc123" }
```

### Send Notification

```
POST /api/notifications
Body: {
    "notifiable_type": "App\\Models\\User",
    "notifiable_id": 5,
    "title": "Title",
    "body": "Body text",
    "type": "general",
    "priority": "medium"
}
```

### Statistics

```
GET /api/notifications/statistics
```

## 🎯 Notification Types

```php
Notification::TYPE_GENERAL      // 'general'
Notification::TYPE_APPOINTMENT  // 'appointment'
Notification::TYPE_PAYMENT      // 'payment'
Notification::TYPE_CASE         // 'case'
Notification::TYPE_REMINDER     // 'reminder'
Notification::TYPE_ALERT        // 'alert'
```

## 📊 Priority Levels

```php
Notification::PRIORITY_LOW      // 'low'
Notification::PRIORITY_MEDIUM   // 'medium'
Notification::PRIORITY_HIGH     // 'high'
Notification::PRIORITY_URGENT   // 'urgent'
```

## 🔔 OneSignal Setup

### Environment Variables (Already Configured)

```env
ONESIGNAL_APP_ID=ce088147-a2b7-4de9-930f-d6d65d3a9458
ONESIGNAL_REST_API_KEY=os_v2_app_...
```

### Mobile App Integration

```javascript
// Initialize OneSignal
OneSignal.setAppId("ce088147-a2b7-4de9-930f-d6d65d3a9458");

// Get Player ID
OneSignal.getDeviceState().then((state) => {
  const playerId = state.userId;

  // Send to backend
  fetch("/api/notifications/player-id", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "X-Tenant-ID": tenantId,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ player_id: playerId }),
  });
});
```

## 🛠️ Add Notifications to Other Models

```php
use App\Traits\HasNotifications;

class Patient extends Model
{
    use HasNotifications;
}

// Now patients can receive notifications
$notificationService->send(
    $patient,
    'Appointment Confirmed',
    'Your appointment has been confirmed'
);
```

## 📱 Testing

### Test via API

```bash
curl -X POST http://127.0.0.1:8002/api/notifications/test \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"
```

### Test in Code

```php
$user = User::first();
$notificationService = app(NotificationService::class);

$notification = $notificationService->send(
    $user,
    'Test',
    'This is a test',
    ['type' => Notification::TYPE_GENERAL]
);

dd($notification);
```

## 🎨 Frontend Integration

### React/React Native

```javascript
// Get notifications
const response = await api.get("/notifications");
const notifications = response.data.data;

// Mark as read
await api.patch(`/notifications/${id}/mark-read`);

// Get unread count
const count = await api.get("/notifications/unread-count");
```

### Vue.js

```javascript
async fetchNotifications() {
    const response = await axios.get('/api/notifications');
    this.notifications = response.data.data;
    this.unreadCount = response.data.unread_count;
}
```

## 🔍 Query Scopes

```php
// Get unread notifications
$unread = Notification::unread()->get();

// Get by type
$appointments = Notification::ofType('appointment')->get();

// Get by priority
$urgent = Notification::ofPriority('urgent')->get();

// Get sent via OneSignal
$sent = Notification::sentViaOneSignal()->get();

// Combine scopes
$notifications = Notification::unread()
    ->ofType('appointment')
    ->ofPriority('high')
    ->orderBy('created_at', 'desc')
    ->get();
```

## ⚡ Performance Tips

1. **Use eager loading** when loading notifications with relations:

```php
$users = User::with('notifications')->get();
```

2. **Limit results** when fetching notifications:

```php
$notifications = $user->notifications()->limit(20)->get();
```

3. **Use pagination** for large datasets:

```php
$notifications = $user->notifications()->paginate(20);
```

4. **Filter by date** to reduce query size:

```php
$notifications = $user->notifications()
    ->where('created_at', '>=', now()->subDays(30))
    ->get();
```

## 🧹 Cleanup Old Notifications

```php
// Delete read notifications older than 90 days
Notification::where('is_read', true)
    ->where('created_at', '<', now()->subDays(90))
    ->delete();

// Or soft delete
Notification::where('is_read', true)
    ->where('created_at', '<', now()->subDays(90))
    ->update(['deleted_at' => now()]);
```

## 📦 Files Created

- `app/Models/Notification.php` - Notification model
- `app/Traits/HasNotifications.php` - Trait for notifiable models
- `app/Services/NotificationService.php` - Service layer
- `app/Http/Controllers/NotificationController.php` - API controller
- `database/migrations/tenant/2026_02_17_000001_create_notifications_table.php` - Notifications table
- `database/migrations/tenant/2026_02_17_000002_add_onesignal_player_id_to_users_table.php` - User player ID
- `config/onesignal.php` - OneSignal configuration
- `routes/api.php` - API routes (updated)

## 🔗 Useful Links

- [Full Documentation](./NOTIFICATION_SYSTEM_DOCUMENTATION.md)
- [OneSignal Dashboard](https://onesignal.com/apps)
- [OneSignal Documentation](https://documentation.onesignal.com/)

---

**Need Help?** Check the full documentation or contact the development team.
