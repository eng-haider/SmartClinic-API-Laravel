# Messaging Automation System — Documentation

> Multi-tenant automation engine for sending WhatsApp (and future channel) messages based on clinic events, schedules, and periodic rules.

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Database Schema](#database-schema)
4. [Models](#models)
5. [Services](#services)
6. [Automation Engine](#automation-engine)
7. [Jobs & Queue](#jobs--queue)
8. [Event System](#event-system)
9. [Webhook Integration](#webhook-integration)
10. [API Reference](#api-reference)
11. [Scheduler Setup](#scheduler-setup)
12. [Adding New Channels](#adding-new-channels)
13. [Adding New Triggers](#adding-new-triggers)

---

## Overview

This system lets clinics define automation rules that automatically send messages to patients based on:

- A **case being created or completed**
- A **custom scheduled date/time**
- A **delay** (e.g. 3 days after case creation)
- A **periodic interval** (e.g. every 7 days)
- A **manual trigger** via API

All data lives inside the **tenant database** — no `clinic_id` column, fully isolated per clinic using Stancl/Tenancy.

---

## Architecture

```
Trigger (Event / API / Scheduler)
        │
        ▼
AutomationEngine
  • Finds matching active AutomationRules
  • Evaluates conditions_json filters
  • Calculates scheduled_for time
  • Creates AutomationTarget records
        │
        ▼ (via cron every minute)
automation:process command
  • Loops all tenants
  • Dispatches ProcessAutomationTargetsJob per tenant
        │
        ▼
ProcessAutomationTargetsJob (runs inside tenant context)
  • Fetches pending targets where scheduled_for <= now
  • Dispatches SendAutomationMessageJob per target
  • Generates next occurrences for periodic rules
        │
        ▼
SendAutomationMessageJob
  • Loads target with rule + patient
  • Calls MessageService::sendForTarget()
        │
        ▼
MessageService
  • Renders body via TemplateEngine
  • Gets/creates Conversation
  • Creates Message record (status: queued)
  • Calls ChannelManager → WhatsAppChannel
  • Updates Message + AutomationTarget status
```

---

## Database Schema

### `messaging_settings`

Stores provider credentials per tenant.

| Column                          | Type               | Description                     |
| ------------------------------- | ------------------ | ------------------------------- |
| `id`                            | bigint             | Primary key                     |
| `provider`                      | string             | `whatsapp`, `email`, `push`     |
| `whatsapp_phone_number_id`      | string             | Meta phone number ID            |
| `whatsapp_access_token`         | string (encrypted) | Meta access token               |
| `whatsapp_business_account_id`  | string             | Meta WABA ID                    |
| `whatsapp_webhook_verify_token` | string             | Token for webhook verification  |
| `is_active`                     | boolean            | Whether this provider is active |
| `meta`                          | json               | Extra provider config           |

---

### `automation_rules`

Defines when and how to send messages.

| Column                   | Type               | Description                           |
| ------------------------ | ------------------ | ------------------------------------- |
| `id`                     | bigint             | Primary key                           |
| `name`                   | string             | Human-readable rule name              |
| `is_active`              | boolean            | Toggle rule on/off                    |
| `trigger_type`           | string             | See [Trigger Types](#trigger-types)   |
| `delay_minutes`          | int nullable       | Minutes to wait after trigger         |
| `delay_days`             | int nullable       | Days to wait after trigger            |
| `exact_datetime`         | timestamp nullable | Fire at exact date/time               |
| `is_periodic`            | boolean            | Whether rule repeats                  |
| `periodic_interval_days` | int nullable       | Repeat every N days                   |
| `template_key`           | string             | Key of the `message_templates` record |
| `channel`                | string             | `whatsapp`, `email`, `push`           |
| `conditions_json`        | json nullable      | Conditions that must match context    |
| `created_by`             | int nullable       | User who created the rule             |

**Trigger Types:**

| Value            | When it fires                                       |
| ---------------- | --------------------------------------------------- |
| `case_created`   | When a new case is saved                            |
| `case_completed` | When a case status changes to completed             |
| `manual`         | Via `POST /messaging/automation-rules/{id}/trigger` |
| `custom_date`    | At `exact_datetime`                                 |
| `periodic`       | Every `periodic_interval_days` days                 |

**`conditions_json` example:**

```json
{ "status_id": 3, "category_id": 7 }
```

The rule only fires if the trigger context matches all conditions.

---

### `automation_targets`

Each row is a scheduled message execution.

| Column               | Type          | Description                              |
| -------------------- | ------------- | ---------------------------------------- |
| `id`                 | bigint        | Primary key                              |
| `automation_rule_id` | FK            | Which rule spawned this                  |
| `patient_id`         | FK            | Target patient                           |
| `case_id`            | FK nullable   | Related case (if any)                    |
| `scheduled_for`      | timestamp     | When to send                             |
| `status`             | string        | `pending`, `sent`, `failed`, `cancelled` |
| `message_id`         | FK nullable   | Linked message after sending             |
| `error_message`      | text nullable | Error details on failure                 |
| `attempt_count`      | int           | How many send attempts made              |

---

### `conversations`

One conversation per patient per channel.

| Column            | Type      | Description                 |
| ----------------- | --------- | --------------------------- |
| `id`              | bigint    | Primary key                 |
| `patient_id`      | FK        | Unique per channel          |
| `channel`         | string    | `whatsapp`, `email`, `push` |
| `phone_number`    | string    | Patient phone               |
| `status`          | string    | `open`, `closed`            |
| `last_message_at` | timestamp | Updated on each message     |

---

### `messages`

All inbound and outbound messages.

| Column            | Type            | Description                                     |
| ----------------- | --------------- | ----------------------------------------------- |
| `id`              | bigint          | Primary key                                     |
| `conversation_id` | FK nullable     | Parent conversation                             |
| `direction`       | string          | `inbound`, `outbound`                           |
| `channel`         | string          | Channel used                                    |
| `from_number`     | string nullable | Sender number                                   |
| `to_number`       | string nullable | Recipient number                                |
| `body`            | text nullable   | Rendered message text                           |
| `template_key`    | string nullable | Template used                                   |
| `template_params` | json nullable   | Variables at send time                          |
| `external_id`     | string nullable | WhatsApp message ID                             |
| `status`          | string          | `queued`, `sent`, `delivered`, `read`, `failed` |
| `error_message`   | text nullable   | Error on failure                                |
| `meta`            | json nullable   | Raw API response or extra data                  |

---

### `message_templates`

Reusable message bodies with variable placeholders.

| Column      | Type            | Description                                    |
| ----------- | --------------- | ---------------------------------------------- |
| `id`        | bigint          | Primary key                                    |
| `key`       | string (unique) | Identifier referenced by rules                 |
| `name`      | string          | Display name                                   |
| `channel`   | string          | `whatsapp`, `email`, `push`                    |
| `body`      | text            | Template body with `{{variable}}` placeholders |
| `language`  | string          | `ar`, `en`, etc.                               |
| `is_active` | boolean         | Toggle on/off                                  |
| `variables` | json nullable   | List of variables used                         |

**Supported variables:**

| Variable            | Value                  |
| ------------------- | ---------------------- |
| `{{patient_name}}`  | Patient's full name    |
| `{{patient_phone}}` | Patient's phone number |
| `{{doctor_name}}`   | Assigned doctor's name |
| `{{case_name}}`     | Case category name     |
| `{{case_date}}`     | Case date (Y-m-d)      |
| `{{case_notes}}`    | Case notes             |
| `{{clinic_name}}`   | Tenant name            |

**Example template body:**

```
مرحباً {{patient_name}}،
نود تذكيركم بمتابعة حالتكم {{case_name}} مع الدكتور {{doctor_name}}.
عيادة {{clinic_name}}
```

---

### `webhook_logs`

Raw storage for every incoming webhook.

| Column          | Type          | Description                       |
| --------------- | ------------- | --------------------------------- |
| `id`            | bigint        | Primary key                       |
| `source`        | string        | `whatsapp`                        |
| `event_type`    | string        | `message`, `status`, `unknown`    |
| `payload`       | json          | Full raw payload                  |
| `status`        | string        | `received`, `processed`, `failed` |
| `error_message` | text nullable | Processing error                  |

---

## Models

| Model              | File                              | Key methods / scopes                                                               |
| ------------------ | --------------------------------- | ---------------------------------------------------------------------------------- |
| `AutomationRule`   | `app/Models/AutomationRule.php`   | `scopeActive()`, `scopeForTrigger()`, `matchesConditions()`, `getDelayInMinutes()` |
| `AutomationTarget` | `app/Models/AutomationTarget.php` | `scopeReady()`, `markSent()`, `markFailed()`, `cancel()`                           |
| `Message`          | `app/Models/Message.php`          | `updateStatus()`, `scopeInbound()`, `scopeOutbound()`                              |
| `Conversation`     | `app/Models/Conversation.php`     | `findOrCreateForPatient()`                                                         |
| `MessageTemplate`  | `app/Models/MessageTemplate.php`  | `render(array $variables)`                                                         |
| `MessagingSetting` | `app/Models/MessagingSetting.php` | `scopeActive()`, `scopeForProvider()`                                              |
| `WebhookLog`       | `app/Models/WebhookLog.php`       | `markProcessed()`, `markFailed()`                                                  |

---

## Services

All services live in `app/Services/Messaging/`.

### `ChannelManager`

Resolves and caches channel driver instances.

```php
$driver = app(ChannelManager::class)->driver('whatsapp');
$result = $driver->send($phone, $body, $options);
// $result = ['success' => true, 'external_id' => 'wamid.xxx', 'error' => null]
```

### `WhatsAppChannel`

Implements `ChannelDriver`. Sends text and template messages via Meta Graph API v21.0.

- Reads `MessagingSetting` for credentials at runtime (tenant-aware)
- Normalizes phone numbers (removes `+`, spaces, etc.)
- Distinguishes text send vs. template send based on `options['template_name']`

### `TemplateEngine`

Builds variable maps from context and renders templates.

```php
$engine = app(TemplateEngine::class);

// Render from an AutomationTarget
$body = $engine->renderForTarget($target);

// Render by key with custom variables
$body = $engine->render('follow_up_reminder', ['patient_name' => 'Ahmed']);

// Admin preview with sample data
$preview = $engine->preview('follow_up_reminder');
```

### `MessageService`

Orchestrates sending — creates DB records, calls channel, updates statuses.

```php
$service = app(MessageService::class);

// Send via automation target
$message = $service->sendForTarget($target);

// Send direct (not tied to automation)
$message = $service->sendDirect($phone, $body, 'whatsapp', $conversationId);

// Record an inbound message
$message = $service->recordInbound([
    'from_number' => '9647801234567',
    'body' => 'مرحبا',
    'external_id' => 'wamid.xxx',
]);
```

### `AutomationEngine`

Core scheduling logic.

```php
$engine = app(AutomationEngine::class);

// Fire an event trigger (called by event listeners)
$engine->fireTrigger('case_created', [
    'patient_id' => 5,
    'case_id' => 12,
    'status_id' => 1,
    'category_id' => 3,
]);

// Manual trigger for a patient
$target = $engine->triggerManual($ruleId, $patientId, $caseId, $scheduledAt);

// Schedule at exact time
$target = $engine->scheduleAt($ruleId, $patientId, Carbon::parse('2026-04-10 09:00:00'));

// Cancel all pending for a patient
$engine->cancelPendingForPatient($patientId);

// Cancel all pending for a case
$engine->cancelPendingForCase($caseId);
```

### `WebhookService`

Processes incoming WhatsApp webhooks.

- `verifyWebhook()` — handles GET verification from Meta
- `handleWhatsApp()` — resolves tenant from `phone_number_id`, processes inbound messages and status updates, logs everything in `webhook_logs`

---

## Automation Engine

### Scheduling Logic

When `fireTrigger()` is called, for each matching active rule:

1. `exact_datetime` set → use that timestamp
2. `delay_days` or `delay_minutes` set → `now() + delay`
3. Neither → send immediately (`now()`)

### Periodic Rules

`ProcessAutomationTargetsJob` calls `generatePeriodicTargets()` after each batch:

- For each periodic rule, finds the last `sent` or `pending` target per patient
- Calculates `last_scheduled + periodic_interval_days`
- Creates a new pending target if it doesn't exist yet and is in the future

### Conditions Filtering

`conditions_json` is a flat key-value map matched against the trigger context:

```json
// Rule conditions
{ "category_id": 5 }

// Trigger context
{ "patient_id": 10, "case_id": 7, "category_id": 5, "status_id": 1 }

// ✓ Matches — category_id equals 5
```

---

## Jobs & Queue

### `SendAutomationMessageJob`

- **Tries:** 3
- **Backoff:** 60 seconds between retries
- Guards: skips if target is not `pending`, rule is inactive, or patient has no phone

```php
SendAutomationMessageJob::dispatch($targetId, $tenantId);
```

### `ProcessAutomationTargetsJob`

- Fetches up to 100 ready targets at a time
- Dispatches `SendAutomationMessageJob` for each
- Generates next periodic targets

```php
ProcessAutomationTargetsJob::dispatch($tenantId);
```

---

## Event System

### Events

| Event           | File                           | Fired by                                                                             |
| --------------- | ------------------------------ | ------------------------------------------------------------------------------------ |
| `CaseCreated`   | `app/Events/CaseCreated.php`   | `CaseModel::booted()` on `created`                                                   |
| `CaseCompleted` | `app/Events/CaseCompleted.php` | `CaseModel::booted()` on `updated` when `status_id` changes to `COMPLETED_STATUS_ID` |

### Listeners

| Listener                        | File                                              | Event           |
| ------------------------------- | ------------------------------------------------- | --------------- |
| `HandleCaseCreatedAutomation`   | `app/Listeners/HandleCaseCreatedAutomation.php`   | `CaseCreated`   |
| `HandleCaseCompletedAutomation` | `app/Listeners/HandleCaseCompletedAutomation.php` | `CaseCompleted` |

Both listeners call `AutomationEngine::fireTrigger()` with case context.

### Completed Status ID

`CaseModel::COMPLETED_STATUS_ID = 3` — change this constant if your clinic uses a different status ID for completed cases.

---

## Webhook Integration

### Verification (GET)

```
GET /api/webhooks/whatsapp
    ?hub.mode=subscribe
    &hub.verify_token=YOUR_TOKEN
    &hub.challenge=CHALLENGE_STRING
```

The system looks up the `verify_token` across all tenant `messaging_settings`. If found and mode is `subscribe`, it echoes back the challenge.

### Incoming Messages (POST)

```
POST /api/webhooks/whatsapp
Content-Type: application/json
```

Payload is the standard Meta webhook format. The system:

1. Extracts `phone_number_id` from `entry.changes.value.metadata`
2. Resolves the correct tenant by matching it in `messaging_settings`
3. Runs inside that tenant's DB context
4. Logs the raw payload to `webhook_logs`
5. Processes inbound messages → creates `Message` records
6. Processes status updates → updates `Message.status`

### Configuring in Meta Dashboard

Set the callback URL to:

```
https://yourdomain.com/api/webhooks/whatsapp
```

Set the verify token to the value stored in `messaging_settings.whatsapp_webhook_verify_token` for the tenant.

---

## API Reference

All tenant routes require header `X-Tenant-ID: {clinic_id}` and `Authorization: Bearer {jwt_token}`.

### Messaging Settings

| Method | Endpoint                         | Description                              |
| ------ | -------------------------------- | ---------------------------------------- |
| `GET`  | `/api/tenant/messaging/settings` | List settings                            |
| `POST` | `/api/tenant/messaging/settings` | Create or update settings for a provider |

**POST body:**

```json
{
  "provider": "whatsapp",
  "whatsapp_phone_number_id": "1234567890",
  "whatsapp_access_token": "EAA...",
  "whatsapp_business_account_id": "9876543210",
  "whatsapp_webhook_verify_token": "my-secret-token",
  "is_active": true
}
```

---

### Message Templates

| Method   | Endpoint                                       | Description              |
| -------- | ---------------------------------------------- | ------------------------ |
| `GET`    | `/api/tenant/messaging/templates`              | List templates           |
| `POST`   | `/api/tenant/messaging/templates`              | Create template          |
| `GET`    | `/api/tenant/messaging/templates/{id}`         | Show template            |
| `PUT`    | `/api/tenant/messaging/templates/{id}`         | Update template          |
| `DELETE` | `/api/tenant/messaging/templates/{id}`         | Delete template          |
| `GET`    | `/api/tenant/messaging/templates/{id}/preview` | Preview with sample data |

**POST body:**

```json
{
  "key": "case_followup_3days",
  "name": "Follow-up after 3 days",
  "channel": "whatsapp",
  "body": "مرحباً {{patient_name}}، نود متابعة حالتكم {{case_name}} بعد 3 أيام.",
  "language": "ar",
  "is_active": true,
  "variables": ["patient_name", "case_name"]
}
```

---

### Automation Rules

| Method   | Endpoint                                              | Description                    |
| -------- | ----------------------------------------------------- | ------------------------------ |
| `GET`    | `/api/tenant/messaging/automation-rules`              | List rules                     |
| `POST`   | `/api/tenant/messaging/automation-rules`              | Create rule                    |
| `GET`    | `/api/tenant/messaging/automation-rules/{id}`         | Show rule                      |
| `PUT`    | `/api/tenant/messaging/automation-rules/{id}`         | Update rule                    |
| `DELETE` | `/api/tenant/messaging/automation-rules/{id}`         | Delete rule                    |
| `POST`   | `/api/tenant/messaging/automation-rules/{id}/trigger` | Manually trigger for a patient |

**Create rule — delay example:**

```json
{
  "name": "Follow-up 3 days after case creation",
  "trigger_type": "case_created",
  "delay_days": 3,
  "template_key": "case_followup_3days",
  "channel": "whatsapp",
  "is_active": true
}
```

**Create rule — fixed date:**

```json
{
  "name": "Ramadan reminder",
  "trigger_type": "custom_date",
  "exact_datetime": "2026-03-01 09:00:00",
  "template_key": "ramadan_greeting",
  "channel": "whatsapp"
}
```

**Create rule — periodic:**

```json
{
  "name": "Weekly follow-up",
  "trigger_type": "periodic",
  "is_periodic": true,
  "periodic_interval_days": 7,
  "template_key": "weekly_reminder",
  "channel": "whatsapp"
}
```

**Create rule — with conditions (only dental cases):**

```json
{
  "name": "Post-dental follow-up",
  "trigger_type": "case_completed",
  "delay_days": 1,
  "template_key": "dental_followup",
  "channel": "whatsapp",
  "conditions_json": { "category_id": 1 }
}
```

**Manual trigger:**

```json
{
  "patient_id": 42,
  "case_id": 15,
  "scheduled_for": "2026-04-10 10:00:00"
}
```

---

### Automation Targets

| Method | Endpoint                                               | Description                                                    |
| ------ | ------------------------------------------------------ | -------------------------------------------------------------- |
| `GET`  | `/api/tenant/messaging/automation-targets`             | List targets (filterable by `status`, `rule_id`, `patient_id`) |
| `GET`  | `/api/tenant/messaging/automation-targets/{id}`        | Show target                                                    |
| `POST` | `/api/tenant/messaging/automation-targets/{id}/cancel` | Cancel pending target                                          |

---

### Conversations

| Method | Endpoint                                        | Description         |
| ------ | ----------------------------------------------- | ------------------- |
| `GET`  | `/api/tenant/messaging/conversations`           | List conversations  |
| `GET`  | `/api/tenant/messaging/conversations/{id}`      | Show with messages  |
| `POST` | `/api/tenant/messaging/conversations/{id}/send` | Send direct message |

**Send direct message body:**

```json
{
  "body": "مرحباً، كيف حالك؟"
}
```

---

## Scheduler Setup

Add to your server crontab:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `automation:process` every minute. This command loops all tenants and dispatches `ProcessAutomationTargetsJob` for each one.

**Process a single tenant manually:**

```bash
php artisan automation:process --tenant=clinic_abc
```

**Queue worker (required for jobs to execute):**

```bash
php artisan queue:work --queue=default --tries=3
```

---

## Adding New Channels

1. Create a driver class implementing `ChannelDriver`:

```php
// app/Services/Messaging/Channels/SmsChannel.php
namespace App\Services\Messaging\Channels;

use App\Services\Messaging\Contracts\ChannelDriver;

class SmsChannel implements ChannelDriver
{
    public function channel(): string { return 'sms'; }

    public function send(string $to, string $body, array $options = []): array
    {
        // Integrate with Twilio, Vonage, etc.
        return ['success' => true, 'external_id' => null, 'error' => null];
    }
}
```

2. Register it in `ChannelManager::resolveDriver()`:

```php
'sms' => new SmsChannel(),
```

3. Add `sms` to the `in:` validation rules in the controllers.

That's all — no other changes needed.

---

## Adding New Triggers

1. Add a constant to `AutomationRule`:

```php
public const TRIGGER_TREATMENT_COMPLETED = 'treatment_completed';
```

2. Add it to the `TRIGGERS` array:

```php
public const TRIGGERS = [
    ...
    self::TRIGGER_TREATMENT_COMPLETED,
];
```

3. Create an Event:

```php
// app/Events/TreatmentCompleted.php
class TreatmentCompleted
{
    use Dispatchable, SerializesModels;
    public function __construct(public Treatment $treatment) {}
}
```

4. Create a Listener:

```php
// app/Listeners/HandleTreatmentCompletedAutomation.php
class HandleTreatmentCompletedAutomation
{
    public function handle(TreatmentCompleted $event): void
    {
        app(AutomationEngine::class)->fireTrigger(
            AutomationRule::TRIGGER_TREATMENT_COMPLETED,
            ['patient_id' => $event->treatment->patient_id]
        );
    }
}
```

5. Register in `MessagingServiceProvider::boot()`:

```php
Event::listen(TreatmentCompleted::class, HandleTreatmentCompletedAutomation::class);
```

6. Dispatch the event wherever the treatment is completed in your business logic.

---

## File Index

```
app/
├── Console/Commands/
│   └── ProcessAutomation.php           ← artisan automation:process
├── Events/
│   ├── CaseCreated.php
│   └── CaseCompleted.php
├── Http/Controllers/
│   ├── AutomationRuleController.php
│   ├── AutomationTargetController.php
│   ├── ConversationController.php
│   ├── MessageTemplateController.php
│   ├── MessagingSettingController.php
│   └── WebhookController.php
├── Jobs/
│   ├── ProcessAutomationTargetsJob.php
│   └── SendAutomationMessageJob.php
├── Listeners/
│   ├── HandleCaseCreatedAutomation.php
│   └── HandleCaseCompletedAutomation.php
├── Models/
│   ├── AutomationRule.php
│   ├── AutomationTarget.php
│   ├── Conversation.php
│   ├── Message.php
│   ├── MessageTemplate.php
│   ├── MessagingSetting.php
│   └── WebhookLog.php
├── Providers/
│   └── MessagingServiceProvider.php    ← registers all services + events
└── Services/Messaging/
    ├── AutomationEngine.php
    ├── ChannelManager.php
    ├── MessageService.php
    ├── TemplateEngine.php
    ├── WebhookService.php
    ├── Channels/
    │   ├── EmailChannel.php            ← stub (future)
    │   ├── PushChannel.php             ← stub (future)
    │   └── WhatsAppChannel.php         ← Meta Graph API v21.0
    └── Contracts/
        └── ChannelDriver.php           ← interface for all channels

database/migrations/tenant/
    ├── 2026_04_05_000001_create_messaging_settings_table.php
    ├── 2026_04_05_000002_create_automation_rules_table.php
    ├── 2026_04_05_000003_create_conversations_table.php
    ├── 2026_04_05_000004_create_messages_table.php
    ├── 2026_04_05_000005_create_message_templates_table.php
    ├── 2026_04_05_000006_create_automation_targets_table.php
    └── 2026_04_05_000007_create_webhook_logs_table.php
```
