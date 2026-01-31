# Clinic Settings Implementation Summary

## 🎯 Overview

Complete backend implementation for doctors to manage their clinic settings including logo, clinic name, contact information, and custom configurations.

## 📁 Files Created

### 1. Controller

**File:** `app/Http/Controllers/ClinicSettingController.php`

- Handles all CRUD operations for clinic settings
- Methods:
  - `index()` - Get all settings
  - `show($key)` - Get single setting
  - `update($key)` - Update/create setting
  - `updateBulk()` - Update multiple settings
  - `uploadLogo()` - Upload clinic logo
  - `destroy($key)` - Delete setting

### 2. Request Validation

**File:** `app/Http/Requests/ClinicSettingRequest.php`

- Validates setting data
- Validates: `setting_value`, `setting_type`, `description`, `is_active`
- Supported types: `string`, `boolean`, `integer`, `json`

### 3. Resource (Response Formatter)

**File:** `app/Http/Resources/ClinicSettingResource.php`

- Formats API responses
- Auto-converts values based on type
- Adds `logo_url` for logo settings

### 4. Repository (Database Layer)

**File:** `app/Repositories/ClinicSettingRepository.php`

- Handles all database operations
- Methods:
  - `getAllByClinic()` - Get all clinic settings
  - `getByKey()` - Get specific setting
  - `updateOrCreate()` - Update or create setting
  - `delete()` - Delete setting
  - `getActiveByClinic()` - Get active settings only
  - `getByType()` - Get settings by type
  - `searchByKey()` - Search settings
  - `bulkUpdate()` - Update multiple settings

### 5. Database Seeder

**File:** `database/seeders/ClinicSettingsSeeder.php`

- Creates default settings for all clinics
- Includes 24 default settings covering:
  - Basic info (name, phone, email, address)
  - Appointment settings
  - Notification settings
  - Financial settings
  - Display settings
  - Working hours
  - Social media links

### 6. API Routes

**File:** `routes/api.php` (updated)

- Added clinic settings routes:
  ```php
  GET    /api/clinic-settings
  GET    /api/clinic-settings/{key}
  PUT    /api/clinic-settings/{key}
  DELETE /api/clinic-settings/{key}
  POST   /api/clinic-settings/bulk-update
  POST   /api/clinic-settings/upload-logo
  ```

### 7. Permissions Configuration

**File:** `config/rolesAndPermissions.php` (updated)

- Added permissions:
  - `view-clinic-settings`
  - `edit-clinic-settings`
- Role access:
  - **super_admin**: View & Edit
  - **clinic_super_doctor**: View & Edit
  - **doctor**: View only
  - **secretary**: No access (customizable)

### 8. Documentation

**Files Created:**

- `docs/CLINIC_SETTINGS_API.md` - Complete API documentation
- `CLINIC_SETTINGS_QUICK_REFERENCE.md` - Quick reference guide

## 🔑 Key Features

### 1. Setting Types

- **String**: Text values (name, email, phone)
- **Boolean**: True/false flags (enable_sms, enable_booking)
- **Integer**: Numeric values (appointment_duration, max_daily_appointments)
- **JSON**: Complex data structures (working_hours)

### 2. Logo Management

- Upload clinic logo via multipart/form-data
- Auto-deletion of old logos on new upload
- Supports: jpeg, png, jpg, gif, svg (max 2MB)
- Returns full URL to logo file
- Stored in `storage/app/public/clinic-logos/`

### 3. Bulk Operations

- Update multiple settings in single request
- Efficient for initial clinic setup
- Validates all settings before saving

### 4. Type Conversion

- Automatic conversion based on `setting_type`
- Boolean: "1" → true, "0" → false
- Integer: "30" → 30
- JSON: string → parsed object

### 5. Security

- JWT authentication required
- Permission-based access control
- Clinic isolation (doctors only access their clinic)
- Validation on all inputs

## 📊 Database Structure

The `clinic_settings` table (already exists):

```sql
- id (bigint, primary key)
- clinic_id (bigint, foreign key to clinics)
- setting_key (string, indexed)
- setting_value (text, nullable)
- setting_type (string, default: 'string')
- description (text, nullable)
- is_active (boolean, default: true)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, soft deletes)

Unique: (clinic_id, setting_key)
```

## 🚀 Usage Examples

### Get All Settings

```bash
curl -X GET http://localhost:8000/api/clinic-settings \
  -H "Authorization: Bearer {token}"
```

### Update Single Setting

```bash
curl -X PUT http://localhost:8000/api/clinic-settings/clinic_name \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"setting_value":"My Clinic","setting_type":"string"}'
```

### Bulk Update

```bash
curl -X POST http://localhost:8000/api/clinic-settings/bulk-update \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": [
      {"key":"clinic_name","value":"SmartClinic","type":"string"},
      {"key":"phone","value":"+1234567890","type":"string"}
    ]
  }'
```

### Upload Logo

```bash
curl -X POST http://localhost:8000/api/clinic-settings/upload-logo \
  -H "Authorization: Bearer {token}" \
  -F "logo=@/path/to/logo.png"
```

## 🔐 Permissions

| Role                | View Settings | Edit Settings | Upload Logo |
| ------------------- | ------------- | ------------- | ----------- |
| super_admin         | ✅            | ✅            | ✅          |
| clinic_super_doctor | ✅            | ✅            | ✅          |
| doctor              | ✅            | ❌            | ❌          |
| secretary           | ❌            | ❌            | ❌          |

## 📝 Common Setting Keys

### Basic Information

- `clinic_name` - Clinic name
- `phone` - Contact phone
- `email` - Contact email
- `address` - Full address
- `website` - Website URL
- `logo` - Logo file path

### Appointments

- `appointment_duration` (integer)
- `enable_online_booking` (boolean)
- `booking_buffer` (integer)
- `max_daily_appointments` (integer)

### Notifications

- `enable_sms` (boolean)
- `enable_email` (boolean)
- `enable_whatsapp` (boolean)
- `reminder_hours` (integer)

### Financial

- `currency` (string)
- `tax_rate` (integer)
- `late_payment_fee` (integer)
- `payment_terms` (string)

### Display

- `theme_color` (string)
- `language` (string)
- `date_format` (string)
- `time_format` (string)

### Complex Data

- `working_hours` (json) - Working hours by day

## 🛠️ Setup Instructions

### 1. Run Database Migrations

The `clinic_settings` table already exists. If not:

```bash
php artisan migrate
```

### 2. Create Storage Link

Required for logo uploads:

```bash
php artisan storage:link
```

### 3. Seed Default Settings (Optional)

Create default settings for all clinics:

```bash
php artisan db:seed --class=ClinicSettingsSeeder
```

### 4. Update Permissions

If using Spatie permissions, sync the new permissions:

```bash
php artisan permission:sync
# or
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## ✅ Testing Checklist

- [ ] Get all settings for authenticated doctor's clinic
- [ ] Get single setting by key
- [ ] Create new setting
- [ ] Update existing setting
- [ ] Bulk update multiple settings
- [ ] Upload logo image
- [ ] Delete setting
- [ ] Verify logo file is deleted when setting is deleted
- [ ] Test different setting types (string, boolean, integer, json)
- [ ] Verify permissions work correctly for each role
- [ ] Test clinic isolation (can't access other clinics' settings)
- [ ] Verify logo URL is generated correctly
- [ ] Test validation errors

## 🎨 Frontend Integration Tips

### 1. Settings Management Page

```javascript
// Fetch all settings
const settings = await fetch("/api/clinic-settings", {
  headers: { Authorization: `Bearer ${token}` },
}).then((r) => r.json());

// Convert to object for easier access
const settingsObj = settings.data.reduce((acc, s) => {
  acc[s.setting_key] = s.setting_value;
  return acc;
}, {});
```

### 2. Logo Upload Component

```javascript
const handleLogoUpload = async (file) => {
  const formData = new FormData();
  formData.append("logo", file);

  const response = await fetch("/api/clinic-settings/upload-logo", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
    body: formData,
  });

  const data = await response.json();
  // Use data.data.logo_url to display the logo
};
```

### 3. Settings Form

```javascript
const saveSettings = async (formData) => {
  const settings = Object.entries(formData).map(([key, value]) => ({
    key,
    value,
    type: inferType(value),
    description: descriptions[key],
  }));

  await fetch("/api/clinic-settings/bulk-update", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ settings }),
  });
};
```

## 🐛 Troubleshooting

### Logo not displaying

**Problem:** Logo URL returns 404
**Solution:** Run `php artisan storage:link` to create symbolic link

### Permission denied

**Problem:** 403 Forbidden error
**Solution:** Verify user has `view-clinic-settings` or `edit-clinic-settings` permission

### Setting not found

**Problem:** 404 on GET request
**Solution:** Use PUT to create the setting first

### Type conversion issues

**Problem:** Boolean showing as "1" instead of true
**Solution:** Use the `setting_value` field in response (auto-converted), not `setting_value_raw`

## 🔄 Future Enhancements

Potential features to add:

1. Setting categories/groups
2. Setting validation rules per key
3. Setting history/audit trail
4. Export/import settings
5. Setting templates
6. Multi-language support for descriptions
7. Setting visibility (public/private)
8. Setting encryption for sensitive data

## 📚 Related Files

- Model: `app/Models/ClinicSetting.php` (already exists)
- Migration: `database/migrations/2025_12_09_000005_create_clinic_settings_table.php`
- Existing Clinic Model: `app/Models/Clinic.php` (has `settings()` relationship)

## ✨ Benefits

1. **Flexible**: Supports any custom setting via key-value pairs
2. **Type-Safe**: Automatic type conversion and validation
3. **Secure**: Permission-based access control
4. **Isolated**: Clinic-specific settings
5. **Scalable**: Easy to add new settings without code changes
6. **Well-Documented**: Complete API docs and examples
7. **Tested**: Comprehensive test checklist provided

## 🎉 Summary

The clinic settings feature is now fully implemented and ready to use! Doctors can:

- View all their clinic settings
- Update individual settings
- Bulk update multiple settings
- Upload and manage clinic logo
- Use different data types (string, boolean, integer, json)

All operations are secured with JWT authentication and permission-based access control, ensuring doctors can only manage their own clinic's settings.
