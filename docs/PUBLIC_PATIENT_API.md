# Public Patient Profile API Documentation

This document describes the Public Patient Profile API, which allows patients to share their medical information via QR codes.

## Overview

The Public Patient Profile feature allows clinics to generate QR codes for patients. When scanned, these QR codes redirect to a public API endpoint that displays the patient's medical information (cases, images, reservations) without requiring authentication.

### Security Features

- **UUID Token**: Each patient has a unique `public_token` (UUID v4) that cannot be guessed or enumerated
- **Opt-in System**: Public profile must be explicitly enabled for each patient
- **Token Regeneration**: Tokens can be regenerated to invalidate old QR codes
- **Limited Data Exposure**: Only non-sensitive medical data is exposed publicly

---

## API Endpoints

### Public Endpoints (No Authentication Required)

These endpoints are accessed via QR code scanning.

#### Get Patient Public Profile

```
GET /api/public/patients/{token}
```

Returns the patient's public profile with all their cases, images, and upcoming reservations.

**Response:**

```json
{
    "success": true,
    "data": {
        "name": "John Doe",
        "age": 35,
        "sex": 1,
        "sex_label": "Male",
        "birth_date": "1991-05-15",
        "systemic_conditions": "None",
        "tooth_details": {...},
        "clinic": {
            "name": "Smart Clinic",
            "address": "123 Main St",
            "phone": "+1234567890"
        },
        "doctor": {
            "name": "Dr. Smith"
        },
        "cases": [
            {
                "id": 1,
                "tooth_num": "14",
                "notes": "Root canal treatment",
                "category": {
                    "name": "Endodontics",
                    "name_en": "Endodontics",
                    "name_ar": "علاج العصب"
                },
                "status": {
                    "name_en": "Completed",
                    "name_ar": "مكتمل",
                    "color": "#28a745"
                },
                "created_at": "2026-01-15"
            }
        ],
        "images": [
            {
                "id": 1,
                "url": "https://example.com/storage/images/xray-001.jpg",
                "type": "xray",
                "alt_text": "X-ray of tooth 14",
                "created_at": "2026-01-15"
            }
        ],
        "upcoming_reservations": [
            {
                "date": "2026-02-15",
                "time": "10:00:00",
                "status": "confirmed",
                "notes": "Follow-up appointment",
                "doctor": {
                    "name": "Dr. Smith"
                }
            }
        ],
        "cases_count": 5,
        "images_count": 12,
        "member_since": "2025-06-01"
    }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Patient profile not found or not publicly accessible."
}
```

#### Get Patient Cases Only

```
GET /api/public/patients/{token}/cases
```

Returns only the patient's cases.

#### Get Patient Images Only

```
GET /api/public/patients/{token}/images
```

Returns only the patient's images.

#### Get Patient Reservations Only

```
GET /api/public/patients/{token}/reservations
```

Returns only the patient's upcoming reservations.

---

### Protected Endpoints (Authentication Required)

These endpoints are used by clinic staff to manage patient public profiles.

#### Get Public Profile Settings

```
GET /api/patients/{id}/public-profile
```

**Headers:**

```
Authorization: Bearer {jwt_token}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "patient_id": 123,
    "patient_name": "John Doe",
    "public_token": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "is_public_profile_enabled": true,
    "public_profile_url": "https://your-domain.com/api/public/patients/a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "qr_code_content": "https://your-domain.com/api/public/patients/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  }
}
```

#### Enable Public Profile

```
POST /api/patients/{id}/public-profile/enable
```

Enables the public profile for a patient, making it accessible via QR code.

**Response:**

```json
{
  "success": true,
  "message": "Public profile enabled successfully.",
  "data": {
    "patient_id": 123,
    "public_token": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "is_public_profile_enabled": true,
    "public_profile_url": "https://your-domain.com/api/public/patients/a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "qr_code_content": "https://your-domain.com/api/public/patients/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  }
}
```

#### Disable Public Profile

```
POST /api/patients/{id}/public-profile/disable
```

Disables the public profile. The QR code will no longer work until re-enabled.

**Response:**

```json
{
  "success": true,
  "message": "Public profile disabled successfully.",
  "data": {
    "patient_id": 123,
    "is_public_profile_enabled": false
  }
}
```

#### Regenerate Public Token

```
POST /api/patients/{id}/public-profile/regenerate-token
```

Generates a new public token. **Warning:** This invalidates all existing QR codes for this patient.

**Response:**

```json
{
  "success": true,
  "message": "Public token regenerated successfully. Old QR codes will no longer work.",
  "data": {
    "patient_id": 123,
    "public_token": "new-uuid-token-here",
    "public_profile_url": "https://your-domain.com/api/public/patients/new-uuid-token-here",
    "qr_code_content": "https://your-domain.com/api/public/patients/new-uuid-token-here"
  }
}
```

#### Get QR Code Data

```
GET /api/patients/{id}/qr-code
```

Returns the data needed to generate a QR code for the patient.

**Response:**

```json
{
  "success": true,
  "data": {
    "patient_id": 123,
    "patient_name": "John Doe",
    "qr_code_content": "https://your-domain.com/api/public/patients/a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "public_token": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "instructions": "Use this URL to generate a QR code. When scanned, it will redirect to the patient's public profile."
  }
}
```

**Error Response (400) - Public profile not enabled:**

```json
{
  "success": false,
  "message": "Public profile is not enabled for this patient. Enable it first to generate QR code."
}
```

---

## Database Changes

### New Columns in `patients` Table

| Column                      | Type    | Description                                           |
| --------------------------- | ------- | ----------------------------------------------------- |
| `public_token`              | UUID    | Unique token for public access (auto-generated)       |
| `is_public_profile_enabled` | Boolean | Whether public profile is accessible (default: false) |

### Migration

Run the migration to add the new columns:

```bash
php artisan migrate
```

---

## Usage Flow

### For Clinic Staff

1. **Enable Public Profile**: Call `POST /api/patients/{id}/public-profile/enable`
2. **Get QR Code Data**: Call `GET /api/patients/{id}/qr-code` to get the URL
3. **Generate QR Code**: Use any QR code library to encode the `qr_code_content` URL
4. **Print/Share**: Print the QR code or share it with the patient

### For Patients

1. **Scan QR Code**: Use any QR code scanner app
2. **View Profile**: The scanner opens the public profile URL
3. **See Medical Data**: View cases, images, and upcoming appointments

---

## Security Considerations

1. **UUID Tokens**: Using UUIDs (v4) makes tokens virtually impossible to guess
2. **No Patient ID Exposure**: The patient's database ID is never exposed publicly
3. **Opt-in Only**: Profiles are disabled by default
4. **Token Regeneration**: If a QR code is compromised, generate a new token
5. **Limited Data**: Financial information (bills, prices) is never exposed publicly
6. **No Phone/Address**: Personal contact information is hidden in public view

---

## QR Code Generation (Frontend Example)

Use any QR code library. Here's an example using JavaScript:

```javascript
// Using qrcode.js library
const qrCodeUrl = response.data.qr_code_content;
QRCode.toCanvas(document.getElementById("qr-canvas"), qrCodeUrl, {
  width: 256,
  margin: 2,
});
```

Or using PHP (if generating on backend):

```php
// Using simple-qrcode package
use SimpleSoftwareIO\QrCode\Facades\QrCode;

$qrCode = QrCode::size(256)->generate($patient->public_profile_url);
```

---

## Best Practices

1. **Always Enable Before Sharing**: Ensure the profile is enabled before printing QR codes
2. **Regenerate on Request**: If a patient requests a new QR code, regenerate the token
3. **Inform Patients**: Let patients know what information will be publicly visible
4. **Regular Audits**: Periodically review which patients have public profiles enabled
