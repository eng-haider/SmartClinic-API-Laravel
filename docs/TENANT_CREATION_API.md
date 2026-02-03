# Tenant Creation API - إنشاء العيادة

## Overview - نظرة عامة

This API creates a new tenant (clinic) with:

- ✅ Tenant record in central database
- ✅ Clinic record in central database
- ✅ Admin user in central database (for authentication)
- ✅ New tenant database
- ✅ Admin user in tenant database (with `clinic_super_doctor` role)
- ✅ Roles and permissions seeded

---

## Create Tenant - إنشاء عيادة جديدة

```http
POST /api/tenants
Content-Type: application/json
```

### Request Body:

```json
{
  "id": "_my_clinic",
  "name": "عيادة النور",
  "address": "بغداد، الكرادة",
  "whatsapp_phone": "07901234567",
  "logo": "https://example.com/logo.png",
  "user_name": "د. أحمد علي",
  "user_phone": "07901234567",
  "user_email": "ahmed@clinic.com",
  "user_password": "password123"
}
```

### Request Parameters:

| Parameter               | Type   | Required | Description                                |
| ----------------------- | ------ | -------- | ------------------------------------------ |
| `id`                    | string | No       | Tenant ID (auto-generated if not provided) |
| `name`                  | string | **Yes**  | Clinic name                                |
| `address`               | string | No       | Clinic address                             |
| `rx_img`                | string | No       | Prescription image URL                     |
| `whatsapp_template_sid` | string | No       | WhatsApp template SID                      |
| `whatsapp_phone`        | string | No       | WhatsApp phone number                      |
| `logo`                  | string | No       | Clinic logo URL                            |
| `user_name`             | string | **Yes**  | Admin user name                            |
| `user_phone`            | string | **Yes**  | Admin user phone (unique)                  |
| `user_email`            | string | No       | Admin user email (unique)                  |
| `user_password`         | string | **Yes**  | Admin user password (min: 6)               |

---

### Success Response (201):

```json
{
  "success": true,
  "message": "Tenant, clinic and user created successfully. Database has been provisioned.",
  "message_ar": "تم إنشاء العيادة والمستخدم بنجاح. تم إعداد قاعدة البيانات.",
  "data": {
    "tenant": {
      "id": "_my_clinic",
      "name": "عيادة النور",
      "address": "بغداد، الكرادة",
      "whatsapp_phone": "07901234567",
      "logo": "https://example.com/logo.png",
      "created_at": "2026-02-03T10:30:00.000000Z",
      "updated_at": "2026-02-03T10:30:00.000000Z"
    },
    "clinic": {
      "id": 1,
      "name": "عيادة النور",
      "address": "بغداد، الكرادة",
      "whatsapp_phone": "07901234567",
      "logo": "https://example.com/logo.png",
      "created_at": "2026-02-03T10:30:00.000000Z",
      "updated_at": "2026-02-03T10:30:00.000000Z"
    },
    "user": {
      "id": 1,
      "name": "د. أحمد علي",
      "phone": "07901234567",
      "email": "ahmed@clinic.com"
    }
  }
}
```

---

### Error Response (500):

```json
{
  "success": false,
  "message": "Failed to create tenant: [error details]",
  "message_ar": "فشل في إنشاء العيادة: [تفاصيل الخطأ]"
}
```

---

## What Happens Behind the Scenes

### Step 1: Create Clinic in Central Database

- Creates a `clinics` record in the central database

### Step 2: Create User in Central Database

- Creates a `users` record in the central database
- Links user to the clinic via `clinic_id`
- Used for the two-step authentication (`POST /api/auth/check-credentials`)

### Step 3: Create Tenant Record

- Creates a `tenants` record with the tenant ID

### Step 4: Create Tenant Database

- Creates a new MySQL database: `tenant_{id}`
- Example: `tenant__my_clinic`

### Step 5: Run Migrations

- Runs all tenant migrations
- Creates tables: `users`, `patients`, `cases`, `bills`, etc.

### Step 6: Seed Roles & Permissions

- Seeds roles: `clinic_super_doctor`, `doctor`, `secretary`
- Seeds permissions for each role

### Step 7: Create User in Tenant Database

- Creates the same user in the tenant database
- Assigns the `clinic_super_doctor` role
- This user can log in via `POST /api/tenant/auth/login`

---

## Complete Example with cURL

```bash
curl -X POST "http://localhost:8000/api/tenants" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "عيادة النور",
    "address": "بغداد، الكرادة",
    "whatsapp_phone": "07901234567",
    "user_name": "د. أحمد علي",
    "user_phone": "07901234567",
    "user_email": "ahmed@clinic.com",
    "user_password": "password123"
  }'
```

---

## After Creation: Login Flow

Once the tenant is created, the user can log in using the two-step authentication:

### Step 1: Check Credentials

```bash
curl -X POST "http://localhost:8000/api/auth/check-credentials" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07901234567",
    "password": "password123"
  }'
```

**Response:**

```json
{
  "success": true,
  "data": {
    "tenant_id": "_my_clinic",
    "clinic_name": "عيادة النور",
    "user_name": "د. أحمد علي"
  }
}
```

### Step 2: Login with Tenant

```bash
curl -X POST "http://localhost:8000/api/tenant/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: _my_clinic" \
  -d '{
    "phone": "07901234567",
    "password": "password123"
  }'
```

**Response:**

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "د. أحمد علي",
      "phone": "07901234567",
      "email": "ahmed@clinic.com",
      "roles": ["clinic_super_doctor"]
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

---

## Database Structure

### Central Database (`smartclinic_central`)

```
clinics
├── id
├── name
├── address
└── ...

users (linked to clinics)
├── id
├── name
├── phone
├── email
├── password
├── clinic_id → clinics.id
└── ...

tenants
├── id
├── name
└── ...
```

### Tenant Database (`tenant__my_clinic`)

```
users (independent)
├── id
├── name
├── phone
├── email
├── password
└── ...

patients
cases
bills
reservations
...
```

---

## Notes

- The `user_phone` must be unique across all users in the central database
- The `user_email` (if provided) must be unique across all users
- The tenant ID is auto-generated from the clinic name if not provided
- The user will have the `clinic_super_doctor` role in the tenant database
- The same user credentials work for both central and tenant databases
