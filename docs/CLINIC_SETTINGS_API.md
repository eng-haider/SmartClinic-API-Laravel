# Clinic Settings API Documentation

## Overview

The Clinic Settings API allows doctors to manage their clinic's settings such as clinic name, logo, contact information, and other customizable settings. Each setting is stored as a key-value pair with a specific type (string, boolean, integer, or json).

## Authentication

All endpoints require JWT authentication. Include the token in the Authorization header:

```
Authorization: Bearer {your_jwt_token}
```

## Permissions

- **view-clinic-settings**: Required to view clinic settings
- **edit-clinic-settings**: Required to create/update/delete clinic settings

### Role Access:

- **super_admin**: Full access (view and edit)
- **clinic_super_doctor**: Full access (view and edit)
- **doctor**: View only
- **secretary**: No access (can be customized)

---

## Endpoints

### 1. Get All Clinic Settings

Retrieve all settings for the authenticated doctor's clinic.

**Endpoint:** `GET /api/clinic-settings`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Response:** `200 OK`

```json
{
  "success": true,
  "message": "Clinic settings retrieved successfully",
  "data": [
    {
      "id": 1,
      "clinic_id": 5,
      "setting_key": "clinic_name",
      "setting_value": "SmartClinic Dental Center",
      "setting_value_raw": "SmartClinic Dental Center",
      "setting_type": "string",
      "description": "Official clinic name",
      "is_active": true,
      "logo_url": null,
      "created_at": "2026-01-31 10:30:00",
      "updated_at": "2026-01-31 10:30:00"
    },
    {
      "id": 2,
      "clinic_id": 5,
      "setting_key": "logo",
      "setting_value": "clinic-logos/logo-xyz123.png",
      "setting_value_raw": "clinic-logos/logo-xyz123.png",
      "setting_type": "string",
      "description": "Clinic logo image",
      "is_active": true,
      "logo_url": "http://localhost:8000/storage/clinic-logos/logo-xyz123.png",
      "created_at": "2026-01-31 10:35:00",
      "updated_at": "2026-01-31 10:35:00"
    },
    {
      "id": 3,
      "clinic_id": 5,
      "setting_key": "working_hours",
      "setting_value": {
        "monday": "9:00 AM - 5:00 PM",
        "tuesday": "9:00 AM - 5:00 PM",
        "wednesday": "9:00 AM - 5:00 PM"
      },
      "setting_value_raw": "{\"monday\":\"9:00 AM - 5:00 PM\",\"tuesday\":\"9:00 AM - 5:00 PM\",\"wednesday\":\"9:00 AM - 5:00 PM\"}",
      "setting_type": "json",
      "description": "Clinic working hours",
      "is_active": true,
      "logo_url": null,
      "created_at": "2026-01-31 10:40:00",
      "updated_at": "2026-01-31 10:40:00"
    },
    {
      "id": 4,
      "clinic_id": 5,
      "setting_key": "enable_online_booking",
      "setting_value": true,
      "setting_value_raw": "1",
      "setting_type": "boolean",
      "description": "Enable online booking feature",
      "is_active": true,
      "logo_url": null,
      "created_at": "2026-01-31 10:45:00",
      "updated_at": "2026-01-31 10:45:00"
    }
  ]
}
```

---

### 2. Get Single Setting by Key

Retrieve a specific setting by its key.

**Endpoint:** `GET /api/clinic-settings/{key}`

**Path Parameters:**

- `key` (string, required): The setting key (e.g., "clinic_name", "logo", "phone")

**Example:** `GET /api/clinic-settings/clinic_name`

**Response:** `200 OK`

```json
{
  "success": true,
  "message": "Clinic setting retrieved successfully",
  "data": {
    "id": 1,
    "clinic_id": 5,
    "setting_key": "clinic_name",
    "setting_value": "SmartClinic Dental Center",
    "setting_value_raw": "SmartClinic Dental Center",
    "setting_type": "string",
    "description": "Official clinic name",
    "is_active": true,
    "logo_url": null,
    "created_at": "2026-01-31 10:30:00",
    "updated_at": "2026-01-31 10:30:00"
  }
}
```

**Error Response:** `404 Not Found`

```json
{
  "success": false,
  "message": "Setting not found"
}
```

---

### 3. Update or Create a Setting

Update an existing setting or create a new one if it doesn't exist.

**Endpoint:** `PUT /api/clinic-settings/{key}`

**Path Parameters:**

- `key` (string, required): The setting key

**Request Body:**

```json
{
  "setting_value": "SmartClinic Premium Dental Center",
  "setting_type": "string",
  "description": "Official clinic name displayed on documents",
  "is_active": true
}
```

**Field Descriptions:**

- `setting_value` (required): The value to store (can be string, number, boolean, or object)
- `setting_type` (optional): One of: `string`, `boolean`, `integer`, `json` (default: `string`)
- `description` (optional): Description of what this setting is for
- `is_active` (optional): Whether the setting is active (default: `true`)

**Examples:**

**String Setting:**

```json
{
  "setting_value": "info@smartclinic.com",
  "setting_type": "string",
  "description": "Clinic email address"
}
```

**Boolean Setting:**

```json
{
  "setting_value": true,
  "setting_type": "boolean",
  "description": "Enable SMS notifications"
}
```

**Integer Setting:**

```json
{
  "setting_value": 30,
  "setting_type": "integer",
  "description": "Default appointment duration in minutes"
}
```

**JSON Setting:**

```json
{
  "setting_value": {
    "monday": "9:00 AM - 5:00 PM",
    "tuesday": "9:00 AM - 5:00 PM",
    "wednesday": "9:00 AM - 5:00 PM",
    "thursday": "9:00 AM - 5:00 PM",
    "friday": "9:00 AM - 3:00 PM"
  },
  "setting_type": "json",
  "description": "Clinic working hours"
}
```

**Response:** `200 OK`

```json
{
  "success": true,
  "message": "Clinic setting updated successfully",
  "data": {
    "id": 1,
    "clinic_id": 5,
    "setting_key": "clinic_name",
    "setting_value": "SmartClinic Premium Dental Center",
    "setting_value_raw": "SmartClinic Premium Dental Center",
    "setting_type": "string",
    "description": "Official clinic name displayed on documents",
    "is_active": true,
    "logo_url": null,
    "created_at": "2026-01-31 10:30:00",
    "updated_at": "2026-01-31 14:25:00"
  }
}
```

**Error Response:** `422 Unprocessable Entity`

```json
{
  "success": false,
  "message": "The setting value is required."
}
```

---

### 4. Bulk Update Settings

Update multiple settings in a single request.

**Endpoint:** `POST /api/clinic-settings/bulk-update`

**Request Body:**

```json
{
  "settings": [
    {
      "key": "clinic_name",
      "value": "SmartClinic Premium Dental Center",
      "type": "string",
      "description": "Official clinic name"
    },
    {
      "key": "phone",
      "value": "+1234567890",
      "type": "string",
      "description": "Clinic contact phone"
    },
    {
      "key": "email",
      "value": "info@smartclinic.com",
      "type": "string",
      "description": "Clinic email address"
    },
    {
      "key": "enable_sms",
      "value": true,
      "type": "boolean",
      "description": "Enable SMS notifications"
    },
    {
      "key": "appointment_duration",
      "value": 30,
      "type": "integer",
      "description": "Default appointment duration"
    }
  ]
}
```

**Response:** `200 OK`

```json
{
  "success": true,
  "message": "Clinic settings updated successfully",
  "data": [
    {
      "id": 1,
      "clinic_id": 5,
      "setting_key": "clinic_name",
      "setting_value": "SmartClinic Premium Dental Center",
      "setting_type": "string",
      "description": "Official clinic name",
      "is_active": true,
      "logo_url": null,
      "created_at": "2026-01-31 10:30:00",
      "updated_at": "2026-01-31 14:30:00"
    },
    {
      "id": 5,
      "clinic_id": 5,
      "setting_key": "phone",
      "setting_value": "+1234567890",
      "setting_type": "string",
      "description": "Clinic contact phone",
      "is_active": true,
      "logo_url": null,
      "created_at": "2026-01-31 14:30:00",
      "updated_at": "2026-01-31 14:30:00"
    }
    // ... other updated settings
  ]
}
```

---

### 5. Upload Clinic Logo

Upload a logo image for the clinic. This will automatically create/update the "logo" setting.

**Endpoint:** `POST /api/clinic-settings/upload-logo`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body (Form Data):**

- `logo` (file, required): Image file (jpeg, png, jpg, gif, svg) - Max size: 2MB

**Example using cURL:**

```bash
curl -X POST http://localhost:8000/api/clinic-settings/upload-logo \
  -H "Authorization: Bearer {token}" \
  -F "logo=@/path/to/logo.png"
```

**Example using JavaScript (FormData):**

```javascript
const formData = new FormData();
formData.append("logo", fileInput.files[0]);

fetch("http://localhost:8000/api/clinic-settings/upload-logo", {
  method: "POST",
  headers: {
    Authorization: `Bearer ${token}`,
  },
  body: formData,
});
```

**Response:** `200 OK`

```json
{
  "success": true,
  "message": "Logo uploaded successfully",
  "data": {
    "logo_url": "http://localhost:8000/storage/clinic-logos/logo-xyz123.png",
    "logo_path": "clinic-logos/logo-xyz123.png",
    "setting": {
      "id": 2,
      "clinic_id": 5,
      "setting_key": "logo",
      "setting_value": "clinic-logos/logo-xyz123.png",
      "setting_value_raw": "clinic-logos/logo-xyz123.png",
      "setting_type": "string",
      "description": "Clinic logo image",
      "is_active": true,
      "logo_url": "http://localhost:8000/storage/clinic-logos/logo-xyz123.png",
      "created_at": "2026-01-31 10:35:00",
      "updated_at": "2026-01-31 15:00:00"
    }
  }
}
```

**Error Response:** `422 Unprocessable Entity`

```json
{
  "success": false,
  "message": "The logo must be an image."
}
```

**Notes:**

- The old logo file will be automatically deleted when uploading a new one
- Supported formats: jpeg, png, jpg, gif, svg
- Maximum file size: 2MB
- Files are stored in `storage/app/public/clinic-logos/`

---

### 6. Delete a Setting

Delete a specific setting.

**Endpoint:** `DELETE /api/clinic-settings/{key}`

**Path Parameters:**

- `key` (string, required): The setting key to delete

**Example:** `DELETE /api/clinic-settings/temp_setting`

**Response:** `200 OK`

```json
{
  "success": true,
  "message": "Clinic setting deleted successfully"
}
```

**Error Response:** `404 Not Found`

```json
{
  "success": false,
  "message": "Setting not found"
}
```

**Notes:**

- If deleting the "logo" setting, the logo image file will also be deleted from storage
- Deleting a setting is permanent and cannot be undone

---

## Common Setting Keys

Here are some recommended setting keys for clinic management:

### Basic Information

- `clinic_name` (string): Official clinic name
- `clinic_address` (string): Full address
- `phone` (string): Contact phone number
- `email` (string): Contact email
- `website` (string): Clinic website URL
- `logo` (string): Clinic logo file path

### Contact & Social

- `whatsapp` (string): WhatsApp number
- `facebook` (string): Facebook page URL
- `instagram` (string): Instagram handle
- `twitter` (string): Twitter handle

### Operating Hours

- `working_hours` (json): Working hours per day
  ```json
  {
    "monday": "9:00 AM - 5:00 PM",
    "tuesday": "9:00 AM - 5:00 PM",
    "wednesday": "9:00 AM - 5:00 PM",
    "thursday": "9:00 AM - 5:00 PM",
    "friday": "9:00 AM - 3:00 PM",
    "saturday": "Closed",
    "sunday": "Closed"
  }
  ```

### Appointment Settings

- `appointment_duration` (integer): Default duration in minutes
- `enable_online_booking` (boolean): Allow online appointments
- `booking_buffer` (integer): Buffer time between appointments
- `max_daily_appointments` (integer): Maximum appointments per day

### Notification Settings

- `enable_sms` (boolean): Enable SMS notifications
- `enable_email` (boolean): Enable email notifications
- `enable_whatsapp` (boolean): Enable WhatsApp notifications
- `reminder_time` (integer): Hours before appointment to send reminder

### Financial Settings

- `currency` (string): Currency code (e.g., "USD", "EUR")
- `tax_rate` (integer): Tax percentage
- `late_payment_fee` (integer): Late payment fee amount
- `payment_terms` (string): Payment terms description

### Display Settings

- `theme_color` (string): Primary theme color (hex)
- `language` (string): Default language code
- `date_format` (string): Preferred date format
- `time_format` (string): 12-hour or 24-hour format

---

## Error Codes

| Status Code | Description                             |
| ----------- | --------------------------------------- |
| 200         | Success                                 |
| 201         | Created successfully                    |
| 401         | Unauthorized - Invalid or missing token |
| 403         | Forbidden - Insufficient permissions    |
| 404         | Not Found - Setting doesn't exist       |
| 422         | Validation Error - Invalid request data |
| 500         | Internal Server Error                   |

---

## Usage Examples

### Example 1: Set up basic clinic information

```javascript
// Update multiple settings at once
const setupClinic = async () => {
  const response = await fetch("/api/clinic-settings/bulk-update", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      settings: [
        {
          key: "clinic_name",
          value: "SmartClinic Dental Center",
          type: "string",
          description: "Official clinic name",
        },
        {
          key: "phone",
          value: "+1234567890",
          type: "string",
          description: "Primary contact number",
        },
        {
          key: "email",
          value: "contact@smartclinic.com",
          type: "string",
          description: "Primary contact email",
        },
        {
          key: "enable_online_booking",
          value: true,
          type: "boolean",
          description: "Allow patients to book online",
        },
      ],
    }),
  });

  const data = await response.json();
  console.log(data);
};
```

### Example 2: Upload clinic logo

```javascript
const uploadLogo = async (file) => {
  const formData = new FormData();
  formData.append("logo", file);

  const response = await fetch("/api/clinic-settings/upload-logo", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
    },
    body: formData,
  });

  const data = await response.json();
  console.log("Logo URL:", data.data.logo_url);
};
```

### Example 3: Get and display clinic settings

```javascript
const getClinicSettings = async () => {
  const response = await fetch("/api/clinic-settings", {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  const data = await response.json();

  // Convert array to object for easier access
  const settings = data.data.reduce((acc, setting) => {
    acc[setting.setting_key] = setting.setting_value;
    return acc;
  }, {});

  console.log("Clinic Name:", settings.clinic_name);
  console.log("Phone:", settings.phone);
  console.log("Email:", settings.email);
};
```

---

## Notes

1. **Automatic Type Conversion**: The API automatically converts values based on the `setting_type`:
   - `boolean`: Converts to true/false
   - `integer`: Converts to number
   - `json`: Parses JSON string to object
   - `string`: Returns as-is

2. **Clinic Isolation**: Each doctor can only access settings for their own clinic. The clinic_id is automatically determined from the authenticated user.

3. **Logo Storage**: Logo files are stored in `storage/app/public/clinic-logos/` and are accessible via the public URL.

4. **Soft Deletes**: The ClinicSetting model uses soft deletes, so deleted settings can be recovered if needed.

5. **Unique Keys**: Each setting key is unique per clinic, so updating with the same key will replace the existing value.

6. **Permissions**: Make sure users have the appropriate permissions. The `view-clinic-settings` permission is required for GET requests, and `edit-clinic-settings` is required for POST/PUT/DELETE requests.
