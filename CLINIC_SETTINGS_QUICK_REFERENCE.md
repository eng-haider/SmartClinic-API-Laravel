# Clinic Settings - Quick Reference Guide

## Overview

API for doctors to manage clinic settings like name, logo, contact info, and custom configurations.

## 🔑 Authentication

All endpoints require JWT token:

```
Authorization: Bearer {your_token}
```

## 🎯 Quick Endpoints

| Method | Endpoint                           | Description              |
| ------ | ---------------------------------- | ------------------------ |
| GET    | `/api/clinic-settings`             | Get all settings         |
| GET    | `/api/clinic-settings/{key}`       | Get single setting       |
| PUT    | `/api/clinic-settings/{key}`       | Update/create setting    |
| POST   | `/api/clinic-settings/bulk-update` | Update multiple settings |
| POST   | `/api/clinic-settings/upload-logo` | Upload clinic logo       |
| DELETE | `/api/clinic-settings/{key}`       | Delete setting           |

## 📋 Common Setting Keys

### Basic Info

- `clinic_name` - Clinic name
- `clinic_address` - Full address
- `phone` - Contact phone
- `email` - Contact email
- `logo` - Logo file path

### Features

- `enable_online_booking` (boolean)
- `enable_sms` (boolean)
- `enable_email` (boolean)
- `appointment_duration` (integer)

### Display

- `theme_color` (string)
- `language` (string)
- `currency` (string)

## 🚀 Quick Examples

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
  -d '{
    "setting_value": "My Clinic Name",
    "setting_type": "string",
    "description": "Official clinic name"
  }'
```

### Bulk Update

```bash
curl -X POST http://localhost:8000/api/clinic-settings/bulk-update \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": [
      {"key": "clinic_name", "value": "SmartClinic", "type": "string"},
      {"key": "phone", "value": "+1234567890", "type": "string"},
      {"key": "enable_sms", "value": true, "type": "boolean"}
    ]
  }'
```

### Upload Logo

```bash
curl -X POST http://localhost:8000/api/clinic-settings/upload-logo \
  -H "Authorization: Bearer {token}" \
  -F "logo=@/path/to/logo.png"
```

## 📊 Setting Types

| Type      | Example Value      | Description   |
| --------- | ------------------ | ------------- |
| `string`  | `"SmartClinic"`    | Text value    |
| `boolean` | `true` or `false`  | Yes/No flag   |
| `integer` | `30`               | Numeric value |
| `json`    | `{"key": "value"}` | Complex data  |

## ✅ Response Format

### Success Response

```json
{
  "success": true,
  "message": "Clinic setting updated successfully",
  "data": {
    "id": 1,
    "clinic_id": 5,
    "setting_key": "clinic_name",
    "setting_value": "SmartClinic",
    "setting_type": "string",
    "description": "Official clinic name",
    "is_active": true,
    "logo_url": null,
    "created_at": "2026-01-31 10:30:00",
    "updated_at": "2026-01-31 10:30:00"
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Setting not found"
}
```

## 🔐 Permissions

| Role                | View | Edit |
| ------------------- | ---- | ---- |
| super_admin         | ✅   | ✅   |
| clinic_super_doctor | ✅   | ✅   |
| doctor              | ✅   | ❌   |
| secretary           | ❌   | ❌   |

## 💡 Tips

1. **Logo Upload**: Automatically creates/updates "logo" setting
2. **Auto-Deletion**: Old logo files are deleted on new upload
3. **Type Conversion**: API auto-converts values based on type
4. **Clinic Isolation**: Doctors only access their own clinic settings
5. **Unique Keys**: One setting per key per clinic

## 🎨 Recommended Settings Structure

```json
{
  "settings": [
    { "key": "clinic_name", "value": "SmartClinic Dental", "type": "string" },
    { "key": "phone", "value": "+1234567890", "type": "string" },
    { "key": "email", "value": "info@clinic.com", "type": "string" },
    { "key": "address", "value": "123 Main St", "type": "string" },
    { "key": "enable_online_booking", "value": true, "type": "boolean" },
    { "key": "appointment_duration", "value": 30, "type": "integer" },
    { "key": "currency", "value": "USD", "type": "string" },
    {
      "key": "working_hours",
      "value": {
        "monday": "9:00 AM - 5:00 PM",
        "tuesday": "9:00 AM - 5:00 PM",
        "wednesday": "9:00 AM - 5:00 PM"
      },
      "type": "json"
    }
  ]
}
```

## 📝 Testing Checklist

- [ ] Get all settings
- [ ] Get single setting by key
- [ ] Create new setting
- [ ] Update existing setting
- [ ] Bulk update multiple settings
- [ ] Upload logo image
- [ ] Delete setting
- [ ] Test with different setting types (string, boolean, integer, json)
- [ ] Verify permissions work correctly
- [ ] Check logo URL is generated properly

## 🐛 Common Issues

### Issue: "Setting not found"

- **Solution**: Use PUT to create setting first, or check if key exists

### Issue: Logo not displaying

- **Solution**: Run `php artisan storage:link` to create symbolic link

### Issue: "Unauthorized"

- **Solution**: Check JWT token is valid and not expired

### Issue: Validation error

- **Solution**: Ensure `setting_value` is provided and `setting_type` is valid

## 🔗 Related Documentation

- Full API Documentation: `docs/CLINIC_SETTINGS_API.md`
- Postman Collection: `docs/POSTMAN_COLLECTION.json`
