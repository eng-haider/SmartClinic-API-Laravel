# Clinic Settings System - Complete Architecture & API Documentation

## 🏗️ Architecture Overview

### Database Design

The clinic settings system uses a **two-database architecture**:

```
┌─────────────────────────────────────────────────────────────┐
│                    CENTRAL DATABASE                          │
│  (smartclinic_central or u876784197_smartclinic)            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  📋 setting_definitions                                      │
│  ├── Master list of all available settings                  │
│  ├── Managed by Super Admin only                            │
│  └── Defines: key, type, default_value, category, etc.      │
│                                                              │
│  🏥 clinics                                                  │
│  └── Central clinic registry                                │
│                                                              │
│  👤 users (central)                                          │
│  └── Central user accounts linked to clinics                │
│                                                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   TENANT DATABASE (per clinic)               │
│  (u876784197_tenant_haider, tenant_amal, etc.)              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ⚙️ clinic_settings                                          │
│  ├── Actual values for THIS clinic                          │
│  ├── Managed by clinic doctors                              │
│  └── Stores: setting_key, setting_value, is_active          │
│                                                              │
│  👤 users (tenant)                                           │
│  📋 patients                                                 │
│  📝 cases                                                    │
│  💰 bills                                                    │
│  └── All clinic-specific data                               │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Why Two Tables?

#### **`setting_definitions`** (Central Database)
- **Purpose**: Master catalog of ALL available settings
- **Who manages**: Super Admin only
- **When created**: Once globally
- **Contains**: Setting key, type, default value, description, category
- **Example**: "clinic_name", "appointment_duration", "enable_whatsapp"

#### **`clinic_settings`** (Tenant Database)
- **Purpose**: Actual setting values for THIS specific clinic
- **Who manages**: Clinic doctors (clinic_super_doctor, doctor roles)
- **When created**: When tenant is created (via seeder)
- **Contains**: Setting key, actual value, is_active
- **Example**: `clinic_name = "Al-Amal Dental Clinic"`

### Data Flow

```
1. Super Admin creates a setting definition in central DB
   └── setting_definitions: { key: "max_appointments_per_day", default: "20" }

2. New clinic "Haider" is created
   └── TenantController@store
       ├── Creates tenant record
       ├── Runs migrations on tenant DB
       └── Runs TenantDatabaseSeeder
           └── Calls TenantClinicSettingsSeeder
               └── Creates clinic_settings records with defaults

3. Clinic "Haider" has its own settings in tenant DB
   └── clinic_settings: { key: "max_appointments_per_day", value: "20" }

4. Doctor updates the setting via API
   └── PUT /api/tenant/clinic-settings/max_appointments_per_day
       └── Updates value to "30" in tenant DB
```

---

## 🔧 Technical Issues & Solutions

### ❌ Problem 1: `clinic_settings` Empty on Tenant Creation

**Issue**: When creating a new tenant, the `clinic_settings` table exists but has no data.

**Root Cause**: `TenantDatabaseSeeder` was not seeding default clinic settings.

**Solution**: ✅ Created `TenantClinicSettingsSeeder.php`
- Seeds 30 default settings when tenant is created
- Called automatically by `TenantDatabaseSeeder`
- No longer depends on central DB's `setting_definitions`

**File Created**: `database/seeders/TenantClinicSettingsSeeder.php`

**File Updated**: `database/seeders/TenantDatabaseSeeder.php`

```php
public function run(): void
{
    $this->call([
        TenantRolesAndPermissionsSeeder::class,
        TenantStatusesSeeder::class,
        TenantCaseCategoriesSeeder::class,
        TenantClinicSettingsSeeder::class, // ← NEW
    ]);
}
```

### ❌ Problem 2: `setting_definitions` Table Not Found

**Issue**: Tenant database looking for `setting_definitions` table but it doesn't exist.

**Root Cause**: `setting_definitions` is in the **central database**, not tenant database.

**Why This Happens**:
- `ClinicSetting` model has: `belongsTo(SettingDefinition::class)`
- When queried, Eloquent tries to join with `setting_definitions`
- But tenant DB doesn't have this table

**Solution Options**:

#### Option 1: Remove the Relationship (Recommended for Tenants)
The `definition()` relationship on `ClinicSetting` is optional. Tenant operations don't need it.

#### Option 2: Use Central Connection for Definitions
```php
public function definition()
{
    return $this->belongsTo(SettingDefinition::class, 'setting_key', 'setting_key')
        ->setConnection('mysql'); // Use central DB
}
```

#### Option 3: Eager Load Only When Needed
```php
// ClinicSettingRepository.php
$settings = $this->query()->get(); // Don't eager load definition
```

**Current Status**: ✅ The system works without the relationship for tenant operations.

---

## 📡 API Endpoints

### Base URL

```
Production: https://api.smartclinic.software
Local: http://localhost:8000
```

### Authentication

All endpoints require JWT authentication:

```http
Authorization: Bearer {your_jwt_token}
X-Tenant-ID: {tenant_id}  # e.g., _haider, _amal
```

---

## 📚 API Reference

### 1. Get All Clinic Settings

Retrieve all settings for the authenticated clinic.

```http
GET /api/tenant/clinic-settings
```

**Headers:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
X-Tenant-ID: _haider
Content-Type: application/json
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Clinic settings retrieved successfully",
  "data": {
    "general": {
      "label": "General Information",
      "settings": [
        {
          "id": 1,
          "setting_key": "clinic_name",
          "setting_value": "عيادة حيدر للأسنان",
          "setting_value_raw": "عيادة حيدر للأسنان",
          "setting_type": "string",
          "description": "Official clinic name",
          "is_required": true,
          "display_order": 1,
          "is_active": true,
          "updated_at": "2026-02-10 14:30:00"
        },
        {
          "id": 2,
          "setting_key": "phone",
          "setting_value": "07700281899",
          "setting_value_raw": "07700281899",
          "setting_type": "string",
          "description": "Primary contact phone number",
          "is_required": false,
          "display_order": 3,
          "is_active": true,
          "updated_at": "2026-02-10 14:30:00"
        }
      ]
    },
    "appointment": {
      "label": "Appointment Settings",
      "settings": [
        {
          "id": 10,
          "setting_key": "appointment_duration",
          "setting_value": 30,
          "setting_value_raw": "30",
          "setting_type": "integer",
          "description": "Default appointment duration in minutes",
          "is_required": false,
          "display_order": 1,
          "is_active": true,
          "updated_at": "2026-02-10 14:30:00"
        }
      ]
    },
    "notification": {
      "label": "Notification Settings",
      "settings": [...]
    },
    "financial": {
      "label": "Financial Settings",
      "settings": [...]
    },
    "display": {
      "label": "Display Settings",
      "settings": [...]
    },
    "social": {
      "label": "Social Media",
      "settings": [...]
    },
    "medical": {
      "label": "Medical/Dental Settings",
      "settings": [...]
    }
  }
}
```

---

### 2. Get Single Setting

Retrieve a specific setting by key.

```http
GET /api/tenant/clinic-settings/{key}
```

**Example:**
```bash
curl -X GET "https://api.smartclinic.software/api/tenant/clinic-settings/clinic_name" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: _haider"
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Clinic setting retrieved successfully",
  "data": {
    "id": 1,
    "setting_key": "clinic_name",
    "setting_value": "عيادة حيدر للأسنان",
    "setting_value_raw": "عيادة حيدر للأسنان",
    "setting_type": "string",
    "description": "Official clinic name",
    "is_active": true,
    "created_at": "2026-02-10 10:00:00",
    "updated_at": "2026-02-10 14:30:00"
  }
}
```

**Response (404 Not Found):**
```json
{
  "success": false,
  "message": "Setting not found"
}
```

---

### 3. Update Setting

Update the value of an existing setting.

```http
PUT /api/tenant/clinic-settings/{key}
```

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
X-Tenant-ID: _haider
Content-Type: application/json
```

**Request Body:**

#### String Setting:
```json
{
  "setting_value": "عيادة الأمل للأسنان"
}
```

#### Boolean Setting:
```json
{
  "setting_value": true
}
```

#### Integer Setting:
```json
{
  "setting_value": 45
}
```

#### JSON Setting (Working Hours):
```json
{
  "setting_value": {
    "sunday": "9:00 AM - 5:00 PM",
    "monday": "9:00 AM - 5:00 PM",
    "tuesday": "9:00 AM - 5:00 PM",
    "wednesday": "9:00 AM - 5:00 PM",
    "thursday": "9:00 AM - 1:00 PM",
    "friday": "Closed",
    "saturday": "Closed"
  }
}
```

#### JSON Setting (Tooth Colors):
```json
{
  "setting_value": {
    "healthy": "#FFFFFF",
    "cavity": "#FF6B6B",
    "filling": "#4ECDC4",
    "crown": "#FFD93D",
    "missing": "#95A5A6"
  }
}
```

**Example (Bash):**
```bash
curl -X PUT "https://api.smartclinic.software/api/tenant/clinic-settings/clinic_name" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: _haider" \
  -H "Content-Type: application/json" \
  -d '{
    "setting_value": "عيادة حيدر المتقدمة للأسنان"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Clinic setting updated successfully",
  "data": {
    "id": 1,
    "setting_key": "clinic_name",
    "setting_value": "عيادة حيدر المتقدمة للأسنان",
    "setting_type": "string",
    "description": "Official clinic name",
    "is_active": true,
    "updated_at": "2026-02-10 15:45:00"
  }
}
```

**Response (404 Not Found):**
```json
{
  "success": false,
  "message": "Setting not found. Only Super Admin can create new setting keys."
}
```

---

### 4. Bulk Update Settings

Update multiple settings at once.

```http
POST /api/tenant/clinic-settings/bulk-update
```

**Request Body:**
```json
{
  "settings": [
    {
      "setting_key": "clinic_name",
      "setting_value": "عيادة الأمل المتقدمة"
    },
    {
      "setting_key": "phone",
      "setting_value": "07701234567"
    },
    {
      "setting_key": "email",
      "setting_value": "info@amal-clinic.com"
    },
    {
      "setting_key": "appointment_duration",
      "setting_value": 45
    },
    {
      "setting_key": "enable_whatsapp",
      "setting_value": true
    }
  ]
}
```

**Example (Bash):**
```bash
curl -X POST "https://api.smartclinic.software/api/tenant/clinic-settings/bulk-update" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: _haider" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": [
      {"setting_key": "clinic_name", "setting_value": "عيادة الأمل"},
      {"setting_key": "appointment_duration", "setting_value": 45}
    ]
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "2 settings updated successfully",
  "data": {
    "updated_count": 2,
    "settings": [
      {
        "setting_key": "clinic_name",
        "setting_value": "عيادة الأمل",
        "updated_at": "2026-02-10 15:50:00"
      },
      {
        "setting_key": "appointment_duration",
        "setting_value": 45,
        "updated_at": "2026-02-10 15:50:00"
      }
    ]
  }
}
```

---

## 🔐 Permissions

### Required Permissions

| Action | Permission | Roles with Access |
|--------|-----------|-------------------|
| View settings | `view-clinic-settings` | super_admin, clinic_super_doctor, doctor |
| Edit settings | `edit-clinic-settings` | super_admin, clinic_super_doctor |

### Permission Check Example

```php
// In your controller or service
if (!auth()->user()->can('edit-clinic-settings')) {
    abort(403, 'You do not have permission to edit clinic settings.');
}
```

---

## 📋 Available Settings

### General Information (Category: `general`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `clinic_name` | string | "" | Official clinic name |
| `logo` | string | "" | Clinic logo image path |
| `phone` | string | "" | Primary contact phone |
| `email` | string | "" | Primary contact email |
| `address` | string | "" | Physical address |
| `clinic_reg_num` | string | "" | Registration/license number |
| `timezone` | string | "Asia/Baghdad" | Clinic timezone |
| `language` | string | "ar" | Default language (ar/en) |
| `currency` | string | "IQD" | Currency code |

### Appointment Settings (Category: `appointment`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `appointment_duration` | integer | 30 | Duration in minutes |
| `working_hours` | json | {...} | Weekly schedule |
| `enable_online_booking` | boolean | false | Online booking toggle |
| `max_appointments_per_day` | integer | 20 | Daily appointment limit |

### Notification Settings (Category: `notification`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enable_sms` | boolean | false | SMS notifications |
| `enable_email` | boolean | false | Email notifications |
| `enable_whatsapp` | boolean | false | WhatsApp notifications |
| `whatsapp_number` | string | "" | WhatsApp business number |
| `reminder_before_hours` | integer | 24 | Reminder timing |

### Financial Settings (Category: `financial`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `tax_rate` | integer | 0 | Tax percentage |
| `enable_invoicing` | boolean | true | Invoice generation |
| `default_payment_method` | string | "cash" | Default payment method |

### Display Settings (Category: `display`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `show_image_case` | boolean | true | Show case images |
| `show_rx_id` | boolean | true | Show prescription ID |
| `teeth_v2` | boolean | true | Use v2 teeth diagram |
| `tooth_colors` | json | {...} | Dental chart colors |

### Social Media (Category: `social`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `facebook_url` | string | "" | Facebook page |
| `instagram_url` | string | "" | Instagram profile |
| `twitter_url` | string | "" | Twitter/X profile |

### Medical/Dental Settings (Category: `medical`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `specializations` | json | [...] | Available specializations |

---

## 🛠️ Developer Guide

### Adding New Settings

#### Step 1: Add to Central Database (Super Admin)
```sql
-- Add to setting_definitions table in central DB
INSERT INTO setting_definitions (setting_key, setting_type, default_value, description, category, display_order)
VALUES ('new_setting', 'string', 'default value', 'Description', 'general', 100);
```

#### Step 2: Add to Tenant Seeder
Update `TenantClinicSettingsSeeder.php`:
```php
[
    'setting_key' => 'new_setting',
    'setting_value' => 'default value',
    'setting_type' => 'string',
    'description' => 'Description',
    'is_active' => true,
],
```

#### Step 3: For Existing Tenants
Run a migration script to add to all existing tenant databases.

### Working with Settings in Code

#### Get a Setting Value
```php
use App\Repositories\ClinicSettingRepository;

$repository = app(ClinicSettingRepository::class);

// Get setting
$setting = $repository->getByKey('clinic_name');
$value = $setting ? $setting->getValue() : null;
```

#### Update a Setting
```php
$repository->updateValue('clinic_name', 'New Clinic Name');
```

#### Check Boolean Setting
```php
$showImages = $repository->getByKey('show_image_case')?->getValue();
if ($showImages) {
    // Show images
}
```

---

## 🧪 Testing

### Test New Tenant Creation

```bash
# Create a test tenant
curl -X POST "https://api.smartclinic.software/api/tenants" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test_clinic",
    "address": "Test Address",
    "user_name": "Test User",
    "user_phone": "07700000000",
    "user_password": "password123"
  }'
```

### Verify Settings Were Created

```bash
# Login as the new clinic
curl -X POST "https://api.smartclinic.software/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07700000000",
    "password": "password123"
  }'

# Get settings
curl -X GET "https://api.smartclinic.software/api/tenant/clinic-settings" \
  -H "Authorization: Bearer NEW_CLINIC_TOKEN" \
  -H "X-Tenant-ID: _test_clinic"
```

### Expected Result
Should return 30 default settings grouped by category.

---

## 🐛 Troubleshooting

### Issue: "Setting not found" when updating

**Cause**: Setting key doesn't exist in tenant's `clinic_settings` table.

**Solution**: 
1. Check if setting exists: `GET /api/tenant/clinic-settings/{key}`
2. If missing, run tenant seeder again or manually insert

### Issue: "Table setting_definitions not found"

**Cause**: Code is looking for `setting_definitions` in tenant DB.

**Solution**: Remove the eager loading of `definition` relationship in tenant context:
```php
// Instead of:
$settings = ClinicSetting::with('definition')->get();

// Use:
$settings = ClinicSetting::get();
```

### Issue: Settings not created for new tenant

**Cause**: `TenantClinicSettingsSeeder` not called.

**Solution**: Check `TenantDatabaseSeeder.php` includes the seeder:
```php
$this->call([
    TenantClinicSettingsSeeder::class,
]);
```

---

## 📝 Summary

✅ **Fixed Issues:**
1. Created `TenantClinicSettingsSeeder` to initialize settings on tenant creation
2. Updated `TenantDatabaseSeeder` to call the new seeder
3. Documented complete architecture and API

✅ **Key Points:**
- `setting_definitions` → Central DB (managed by Super Admin)
- `clinic_settings` → Tenant DB (managed by clinic doctors)
- Settings auto-initialized when creating new tenants
- 30 default settings across 7 categories
- Full CRUD API with permissions

✅ **API Endpoints:**
- `GET /api/tenant/clinic-settings` - Get all settings
- `GET /api/tenant/clinic-settings/{key}` - Get single setting
- `PUT /api/tenant/clinic-settings/{key}` - Update setting
- `POST /api/tenant/clinic-settings/bulk-update` - Bulk update

---

## 📞 Support

For questions or issues, contact the development team.

**Last Updated**: February 10, 2026
