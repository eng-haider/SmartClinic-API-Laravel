# 🔔 SmartClinic Notification System

## ✨ What's New

Your SmartClinic API now has a **complete notification system** with:

- ✅ **OneSignal Push Notifications** - Real-time mobile & web notifications
- ✅ **Polymorphic Notifications** - Send to any model (User, Patient, etc.)
- ✅ **Read/Unread Tracking** - Full notification status management
- ✅ **Priority Levels** - Low, Medium, High, Urgent
- ✅ **Rich Notifications** - Custom data, action URLs, types
- ✅ **RESTful API** - Complete CRUD operations
- ✅ **Clean Architecture** - Service layer, traits, SOLID principles

## 🚀 Quick Start (3 Steps)

### 1. Run Migrations

```bash
# Option A: Using the helper script (Recommended)
php migrate_notifications.php

# Option B: Using Artisan
php artisan tenants:migrate
```

### 2. Send Your First Notification

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

$notification = $notificationService->send(
    $user,
    'Welcome!',
    'Welcome to SmartClinic!'
);
```

### 3. Test via API

```bash
curl -X POST http://127.0.0.1:8002/api/notifications/test \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"
```

## 📚 Documentation

We've created comprehensive documentation for you:

1. **[Full Documentation](NOTIFICATION_SYSTEM_DOCUMENTATION.md)** - Complete guide with all features
2. **[Quick Reference](NOTIFICATION_QUICK_REFERENCE.md)** - Code snippets and quick examples
3. **[Implementation Summary](NOTIFICATION_IMPLEMENTATION_SUMMARY.md)** - What was built
4. **[Code Examples](app/Examples/NotificationExamples.php)** - 15 practical examples

## 🎯 Common Use Cases

### Send Appointment Reminder

```php
$notificationService->sendAppointmentReminder($patient, [
    'id' => 123,
    'date' => '2026-02-20',
    'time' => '10:00 AM',
]);
```

### Notify All Doctors

```php
$notificationService->sendToRole('doctor',
    'Important Notice',
    'Please read this important notice'
);
```

### Get Unread Count

```php
$count = $user->unreadNotificationsCount();
```

### Mark as Read

```php
$user->markAllNotificationsAsRead();
```

## 🔌 API Endpoints

All endpoints require JWT authentication:

```
GET    /api/notifications              # Get all notifications
GET    /api/notifications/unread-count # Get unread count
POST   /api/notifications/mark-all-read # Mark all as read
PATCH  /api/notifications/{id}/mark-read # Mark one as read
POST   /api/notifications/player-id    # Update OneSignal player ID
POST   /api/notifications/test         # Send test notification
GET    /api/notifications/statistics   # Get statistics
```

## 🔔 OneSignal Setup

### Already Configured! ✅

Your `.env` file already has OneSignal configured:

```env
ONESIGNAL_APP_ID=ce088147-a2b7-4de9-930f-d6d65d3a9458
ONESIGNAL_REST_API_KEY=os_v2_app_...
```

### Mobile App Integration

```javascript
// Initialize OneSignal in your mobile app
OneSignal.setAppId("ce088147-a2b7-4de9-930f-d6d65d3a9458");

// Get player ID and send to backend
OneSignal.getDeviceState().then((state) => {
  const playerId = state.userId;

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

## 📦 Files Created

### Core Files (10)

- `app/Models/Notification.php` - Notification model
- `app/Traits/HasNotifications.php` - Reusable trait
- `app/Services/NotificationService.php` - Business logic
- `app/Http/Controllers/NotificationController.php` - API controller
- `database/migrations/tenant/2026_02_17_000001_create_notifications_table.php`
- `database/migrations/tenant/2026_02_17_000002_add_onesignal_player_id_to_users_table.php`
- `config/onesignal.php` - OneSignal config
- `app/Examples/NotificationExamples.php` - 15 examples
- `migrate_notifications.php` - Migration helper
- `routes/api.php` - API routes (updated)

### Documentation (4)

- `NOTIFICATION_SYSTEM_DOCUMENTATION.md` - Full guide
- `NOTIFICATION_QUICK_REFERENCE.md` - Quick reference
- `NOTIFICATION_IMPLEMENTATION_SUMMARY.md` - Implementation details
- `NOTIFICATION_README.md` - This file

## 🎨 Architecture

```
┌─────────────────────────────────────────────────┐
│                  API Layer                      │
│         NotificationController                  │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│              Service Layer                      │
│         NotificationService                     │
│  (Business Logic + OneSignal Integration)       │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│              Model Layer                        │
│  Notification Model + HasNotifications Trait    │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│            Database Layer                       │
│  notifications + users (onesignal_player_id)    │
└─────────────────────────────────────────────────┘
```

## 💡 Features Highlight

### Polymorphic Design

Send notifications to **any model** - not just Users!

```php
// Add trait to any model
use App\Traits\HasNotifications;

class Patient extends Model {
    use HasNotifications;
}

// Now patients can receive notifications
$notificationService->send($patient, 'Title', 'Message');
```

### Rich Notifications

Store custom data in JSON format:

```php
$notificationService->send($user, 'Lab Results', 'Results ready', [
    'data' => [
        'test_type' => 'Blood Test',
        'result_url' => 'https://...',
        'lab_name' => 'Central Lab',
    ],
    'action_url' => '/lab-results/123',
]);
```

### Priority-Based Delivery

OneSignal automatically configures based on priority:

```php
Notification::PRIORITY_URGENT   // Priority 10, urgent channel
Notification::PRIORITY_HIGH     // Priority 9, high channel
Notification::PRIORITY_MEDIUM   // Priority 5
Notification::PRIORITY_LOW      // Priority 3
```

## 🧪 Testing

### Quick Test

```bash
# 1. Start your server
php artisan serve

# 2. Test notification endpoint
curl -X POST http://127.0.0.1:8002/api/notifications/test \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"

# 3. Get notifications
curl -X GET http://127.0.0.1:8002/api/notifications \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"
```

### Code Test

```php
$user = User::first();
$notificationService = app(NotificationService::class);

$notification = $notificationService->send(
    $user,
    'Test',
    'This is a test notification'
);

// Check result
dd([
    'notification' => $notification,
    'user_notifications' => $user->notifications,
    'unread_count' => $user->unreadNotificationsCount(),
]);
```

## 🎯 Next Steps

1. ✅ **Run Migrations** - `php migrate_notifications.php`
2. ✅ **Test the API** - Use curl or Postman
3. ✅ **Integrate in Mobile App** - Add OneSignal SDK
4. ✅ **Start Sending Notifications** - Use the service class
5. ✅ **Monitor Dashboard** - Check OneSignal dashboard

## 📞 Need Help?

- Check the **[Full Documentation](NOTIFICATION_SYSTEM_DOCUMENTATION.md)**
- Review **[Code Examples](app/Examples/NotificationExamples.php)**
- Test using `/api/notifications/test` endpoint
- Check logs in `storage/logs/laravel.log`

## ✨ Best Practices

1. **Always use NotificationService** - Don't create notifications directly
2. **Set appropriate priority** - Don't overuse urgent/high
3. **Include action URLs** - Make notifications actionable
4. **Test OneSignal** - Verify push notifications work
5. **Clean old notifications** - Delete old read notifications periodically

## 🎉 You're All Set!

The notification system is ready to use. Start sending notifications to your users right away!

```php
// Start using it now!
$notificationService = app(NotificationService::class);

$notification = $notificationService->send(
    $user,
    'Welcome to SmartClinic',
    'Your account has been successfully created!'
);
```

---

**Version:** 1.0.0  
**Date:** February 17, 2026  
**Status:** Production Ready ✅
