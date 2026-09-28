# Notification System Documentation

## Overview

SmartClinic now has a comprehensive notification system with OneSignal push notification integration and polymorphic relationships. This system allows sending notifications to any model (User, Patient, etc.) and tracking read/unread status.

## 🎯 Features

- ✅ **Polymorphic Notifications** - Send notifications to any model (User, Patient, etc.)
- ✅ **OneSignal Integration** - Push notifications via OneSignal
- ✅ **Read/Unread Status** - Track notification read status
- ✅ **Priority Levels** - Low, Medium, High, Urgent
- ✅ **Notification Types** - General, Appointment, Payment, Case, Reminder, Alert
- ✅ **Rich Data Support** - Store custom JSON data with notifications
- ✅ **Action URLs** - Deep linking support
- ✅ **Statistics & Analytics** - Track notification metrics
- ✅ **Clean Architecture** - Service layer, traits, and proper separation of concerns

## 📋 Database Structure

### Notifications Table

```sql
- id (primary key)
- notifiable_type (morphable - User, Patient, etc.)
- notifiable_id (morphable ID)
- title (string)
- body (text)
- type (enum: general, appointment, payment, case, reminder, alert)
- data (json - additional custom data)
- onesignal_notification_id (string, nullable)
- onesignal_status (enum: pending, sent, failed)
- onesignal_error (text, nullable)
- is_read (boolean, default: false)
- read_at (timestamp, nullable)
- sender_id (foreign key to users)
- priority (enum: low, medium, high, urgent)
- action_url (string, nullable)
- created_at, updated_at, deleted_at
- Indexes on: notifiable_type+id, is_read+created_at, type+created_at
```

### Users Table Addition

```sql
- onesignal_player_id (string, nullable, indexed)
```

## 🏗️ Architecture

### 1. Models

#### Notification Model (`app/Models/Notification.php`)

```php
// Constants for types
Notification::TYPE_GENERAL
Notification::TYPE_APPOINTMENT
Notification::TYPE_PAYMENT
Notification::TYPE_CASE
Notification::TYPE_REMINDER
Notification::TYPE_ALERT

// Constants for priority
Notification::PRIORITY_LOW
Notification::PRIORITY_MEDIUM
Notification::PRIORITY_HIGH
Notification::PRIORITY_URGENT

// Relationships
$notification->notifiable()  // Get the owner (User, Patient, etc.)
$notification->sender()      // Get who sent it

// Scopes
Notification::unread()
Notification::read()
Notification::ofType('appointment')
Notification::ofPriority('high')
Notification::sentViaOneSignal()

// Methods
$notification->markAsRead()
$notification->markAsUnread()
$notification->isRead()
$notification->isSentViaOneSignal()
```

### 2. Traits

#### HasNotifications Trait (`app/Traits/HasNotifications.php`)

Add this trait to any model that should receive notifications:

```php
use App\Traits\HasNotifications;

class User extends Authenticatable {
    use HasNotifications;
}

class Patient extends Model {
    use HasNotifications;
}
```

**Available Methods:**

```php
// Get notifications
$user->notifications()              // Relationship
$user->unreadNotifications()        // Collection of unread
$user->readNotifications()          // Collection of read
$user->unreadNotificationsCount()   // Count of unread

// Mark as read
$user->markAllNotificationsAsRead()

// Delete
$user->deleteAllNotifications()

// Filter
$user->notificationsByType('appointment')
$user->notificationsByPriority('high')

// Check
$user->hasUnreadNotifications()

// OneSignal Player ID
$user->getOneSignalPlayerId()
$user->setOneSignalPlayerId($playerId)
```

### 3. Service Layer

#### NotificationService (`app/Services/NotificationService.php`)

The service handles all business logic for notifications:

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Send to single user
$notification = $notificationService->send(
    $user,                    // The notifiable model
    'Appointment Reminder',   // Title
    'Your appointment is tomorrow', // Body
    [
        'type' => Notification::TYPE_APPOINTMENT,
        'priority' => Notification::PRIORITY_HIGH,
        'data' => ['appointment_id' => 123],
        'action_url' => '/appointments/123',
        'sender_id' => auth()->id(),
    ]
);

// Send to multiple users
$notifications = $notificationService->sendToMultiple(
    [$user1, $user2, $user3],
    'New Feature',
    'Check out our new feature!'
);

// Send to all users with role
$notifications = $notificationService->sendToRole(
    'doctor',
    'System Update',
    'System maintenance scheduled for tonight'
);

// Send to all active users
$notifications = $notificationService->sendToAllUsers(
    'Important Notice',
    'Please read this important notice'
);

// Mark as read
$notificationService->markAsRead($notificationId);
$notificationService->markMultipleAsRead([1, 2, 3]);
$notificationService->markAllAsRead($user);

// Delete
$notificationService->delete($notificationId);

// Get notifications with filters
$notifications = $notificationService->getNotifications($user, [
    'type' => 'appointment',
    'is_read' => false,
    'priority' => 'high',
    'limit' => 20,
]);

// Helper methods for common notifications
$notificationService->sendAppointmentReminder($user, [
    'id' => 123,
    'date' => '2026-02-20',
    'time' => '10:00 AM',
]);

$notificationService->sendPaymentNotification($user, [
    'amount' => 1000,
    'status' => 'completed',
]);

$notificationService->sendCaseUpdateNotification($user, [
    'id' => 456,
    'title' => 'Dental Checkup',
]);
```

## 🔌 API Endpoints

All routes require JWT authentication (`middleware: jwt`)

### Get Notifications

```http
GET /api/notifications
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Query Parameters:
- type: Filter by type (general, appointment, payment, case, reminder, alert)
- is_read: Filter by read status (0 or 1)
- priority: Filter by priority (low, medium, high, urgent)
- limit: Limit results (default: 50)

Response:
{
    "success": true,
    "data": [...],
    "unread_count": 5
}
```

### Get Single Notification

```http
GET /api/notifications/{id}
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Appointment Reminder",
        "body": "Your appointment is tomorrow",
        "type": "appointment",
        "priority": "high",
        "is_read": false,
        "created_at": "2026-02-17T10:00:00Z"
    }
}
```

### Get Unread Count

```http
GET /api/notifications/unread-count
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "unread_count": 5
}
```

### Mark as Read

```http
PATCH /api/notifications/{id}/mark-read
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "message": "Notification marked as read"
}
```

### Mark Multiple as Read

```http
POST /api/notifications/mark-multiple-read
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json

{
    "notification_ids": [1, 2, 3, 4]
}

Response:
{
    "success": true,
    "message": "4 notifications marked as read",
    "count": 4
}
```

### Mark All as Read

```http
POST /api/notifications/mark-all-read
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "message": "All notifications marked as read",
    "count": 12
}
```

### Delete Notification

```http
DELETE /api/notifications/{id}
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "message": "Notification deleted successfully"
}
```

### Update OneSignal Player ID

```http
POST /api/notifications/player-id
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json

{
    "player_id": "abc123-player-id-from-onesignal"
}

Response:
{
    "success": true,
    "message": "Player ID updated successfully"
}
```

### Send Notification (Admin/Authorized Users)

```http
POST /api/notifications
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json

{
    "notifiable_type": "App\\Models\\User",
    "notifiable_id": 5,
    "title": "Important Notice",
    "body": "Please check this notification",
    "type": "general",
    "priority": "medium",
    "data": {
        "custom_field": "value"
    },
    "action_url": "/dashboard"
}

Response:
{
    "success": true,
    "message": "Notification sent successfully",
    "data": {...}
}
```

### Send Test Notification

```http
POST /api/notifications/test
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "message": "Test notification sent",
    "data": {...}
}
```

### Get Statistics

```http
GET /api/notifications/statistics
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

Response:
{
    "success": true,
    "statistics": {
        "total": 50,
        "unread": 12,
        "read": 38,
        "by_type": {
            "general": 20,
            "appointment": 15,
            "payment": 10,
            "case": 3,
            "reminder": 2,
            "alert": 0
        },
        "by_priority": {
            "low": 10,
            "medium": 25,
            "high": 12,
            "urgent": 3
        }
    }
}
```

## 🔔 OneSignal Integration

### Setup OneSignal

1. **Create OneSignal Account**
   - Go to https://onesignal.com
   - Create a new app
   - Get your App ID and REST API Key

2. **Configure Environment Variables**

Already configured in `.env`:

```env
ONESIGNAL_APP_ID=ce088147-a2b7-4de9-930f-d6d65d3a9458
ONESIGNAL_REST_API_KEY=os_v2_app_zyeicr5cw5g6teyp23lf2ouuld43w3zc33leacmo7bjsglttlv3w4d5yholgllmchptynwzjqnugxd2ucy5vhkypufqp4c2vuknnezi
ONESIGNAL_USER_AUTH_KEY=43w3zc33leacmo7bjsglttlv3
ONESIGNAL_API_URL=https://onesignal.com/api/v1/notifications
```

3. **Initialize OneSignal in Mobile/Web App**

When user logs in or starts the app:

```javascript
// Example: React Native / Mobile App
OneSignal.setAppId("ce088147-a2b7-4de9-930f-d6d65d3a9458");

OneSignal.setNotificationOpenedHandler((notification) => {
  console.log("Notification opened", notification);
});

// Get Player ID and send to backend
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

### How OneSignal Works in the System

1. **User registers OneSignal Player ID** via `/api/notifications/player-id`
2. **When notification is sent**, the system:
   - Creates a record in the `notifications` table
   - If user has a `onesignal_player_id`, sends push notification via OneSignal API
   - Updates notification record with OneSignal response (sent/failed status)
3. **User receives push notification** on their device
4. **User can view notification history** in the app via API endpoints

### Priority-Based Settings

The system automatically configures OneSignal notifications based on priority:

- **Urgent**: Priority 10, Android channel: "urgent-notifications"
- **High**: Priority 9, Android channel: "high-priority-notifications"
- **Medium**: Priority 5
- **Low**: Priority 3

## 💡 Usage Examples

### Example 1: Send Appointment Reminder

```php
use App\Services\NotificationService;

// In your appointment controller or service
public function sendReminder(Appointment $appointment)
{
    $notificationService = app(NotificationService::class);

    $notification = $notificationService->sendAppointmentReminder(
        $appointment->patient,
        [
            'id' => $appointment->id,
            'date' => $appointment->date,
            'time' => $appointment->time,
            'doctor' => $appointment->doctor->name,
        ]
    );

    return $notification;
}
```

### Example 2: Notify Multiple Doctors

```php
// Notify all doctors about a new patient
$doctors = User::role('doctor')->where('is_active', true)->get();

$notificationService->sendToMultiple(
    $doctors->all(),
    'New Patient Registration',
    'A new patient has registered: ' . $patient->name,
    [
        'type' => Notification::TYPE_ALERT,
        'priority' => Notification::PRIORITY_MEDIUM,
        'data' => ['patient_id' => $patient->id],
        'action_url' => "/patients/{$patient->id}",
    ]
);
```

### Example 3: Payment Confirmation

```php
// After successful payment
$notificationService->sendPaymentNotification(
    $user,
    [
        'amount' => $payment->amount,
        'status' => 'completed',
        'payment_id' => $payment->id,
        'method' => $payment->method,
    ]
);
```

### Example 4: Case Status Update

```php
// When case status changes
$notificationService->sendCaseUpdateNotification(
    $case->patient,
    [
        'id' => $case->id,
        'title' => $case->title,
        'status' => $case->status,
        'doctor' => $case->doctor->name,
    ]
);
```

### Example 5: Custom Notification with Data

```php
$notificationService->send(
    $user,
    'Lab Results Ready',
    'Your lab test results are now available',
    [
        'type' => Notification::TYPE_ALERT,
        'priority' => Notification::PRIORITY_HIGH,
        'data' => [
            'lab_test_id' => 789,
            'test_type' => 'Blood Test',
            'result_url' => 'https://example.com/results/789.pdf',
        ],
        'action_url' => '/lab-results/789',
    ]
);
```

## 🔄 Migration Instructions

### For Existing Tenants

Run migrations on all tenant databases:

```bash
php artisan tenants:migrate
```

Or migrate specific tenant:

```bash
php artisan tenants:run migrate --tenants=tenant_id
```

### For New Tenants

Migrations will run automatically during tenant creation.

## 🧪 Testing

### Test Notification System

```php
// Test sending notification
$user = User::first();
$notificationService = app(NotificationService::class);

$notification = $notificationService->send(
    $user,
    'Test Notification',
    'This is a test notification',
    ['type' => Notification::TYPE_GENERAL]
);

// Check if created
dd($notification);

// Check if user has notifications
dd($user->notifications()->count());
```

### Test API Endpoint

```bash
# Send test notification
curl -X POST http://127.0.0.1:8002/api/notifications/test \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"

# Get notifications
curl -X GET http://127.0.0.1:8002/api/notifications \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"

# Get unread count
curl -X GET http://127.0.0.1:8002/api/notifications/unread-count \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "X-Tenant-ID: YOUR_TENANT_ID"
```

## 🎨 Frontend Integration Examples

### React/React Native Example

```javascript
import axios from "axios";

const api = axios.create({
  baseURL: "http://127.0.0.1:8002/api",
  headers: {
    Authorization: `Bearer ${token}`,
    "X-Tenant-ID": tenantId,
  },
});

// Get notifications
const getNotifications = async (filters = {}) => {
  const response = await api.get("/notifications", { params: filters });
  return response.data;
};

// Mark as read
const markAsRead = async (notificationId) => {
  const response = await api.patch(
    `/notifications/${notificationId}/mark-read`,
  );
  return response.data;
};

// Mark all as read
const markAllAsRead = async () => {
  const response = await api.post("/notifications/mark-all-read");
  return response.data;
};

// Get unread count
const getUnreadCount = async () => {
  const response = await api.get("/notifications/unread-count");
  return response.data.unread_count;
};

// Update player ID (call when app starts)
const updatePlayerId = async (playerId) => {
  const response = await api.post("/notifications/player-id", {
    player_id: playerId,
  });
  return response.data;
};
```

### Vue.js Example

```javascript
export default {
  data() {
    return {
      notifications: [],
      unreadCount: 0,
    };
  },
  mounted() {
    this.fetchNotifications();
    this.fetchUnreadCount();

    // Poll for new notifications every 30 seconds
    setInterval(() => {
      this.fetchUnreadCount();
    }, 30000);
  },
  methods: {
    async fetchNotifications(filters = {}) {
      const response = await axios.get("/api/notifications", {
        params: filters,
        headers: {
          Authorization: `Bearer ${this.token}`,
          "X-Tenant-ID": this.tenantId,
        },
      });
      this.notifications = response.data.data;
      this.unreadCount = response.data.unread_count;
    },
    async markAsRead(notificationId) {
      await axios.patch(
        `/api/notifications/${notificationId}/mark-read`,
        {},
        {
          headers: {
            Authorization: `Bearer ${this.token}`,
            "X-Tenant-ID": this.tenantId,
          },
        },
      );
      await this.fetchNotifications();
    },
    async markAllAsRead() {
      await axios.post(
        "/api/notifications/mark-all-read",
        {},
        {
          headers: {
            Authorization: `Bearer ${this.token}`,
            "X-Tenant-ID": this.tenantId,
          },
        },
      );
      await this.fetchNotifications();
    },
    async fetchUnreadCount() {
      const response = await axios.get("/api/notifications/unread-count", {
        headers: {
          Authorization: `Bearer ${this.token}`,
          "X-Tenant-ID": this.tenantId,
        },
      });
      this.unreadCount = response.data.unread_count;
    },
  },
};
```

## 📊 Best Practices

1. **Always use NotificationService** for sending notifications, not direct model creation
2. **Set appropriate priority levels** - don't overuse urgent/high priority
3. **Include action_url** when notifications are actionable
4. **Store relevant data in JSON** for rich notification content
5. **Clean up old read notifications** periodically to maintain performance
6. **Test OneSignal integration** in development before production
7. **Handle notification permissions** properly in mobile apps
8. **Implement retry logic** for failed OneSignal deliveries if needed

## 🔐 Security Considerations

- Notifications are **tenant-isolated** (each tenant sees only their notifications)
- Users can only view/modify **their own notifications**
- Sending notifications requires **authentication**
- OneSignal API keys should be kept **secret**
- Validate all input data before creating notifications

## 🚀 Production Deployment

1. **Update .env** with production OneSignal credentials
2. **Run migrations** on all tenant databases
3. **Test OneSignal integration** thoroughly
4. **Monitor notification delivery rates**
5. **Set up logging** for failed notifications
6. **Configure notification cleanup** job/cron

## 📞 Support

For issues or questions:

- Check Laravel logs: `storage/logs/laravel.log`
- Check OneSignal dashboard for delivery status
- Use `/api/notifications/test` endpoint for testing
- Review notification statistics via `/api/notifications/statistics`

---

**Version:** 1.0.0  
**Last Updated:** February 17, 2026  
**Author:** SmartClinic Development Team
