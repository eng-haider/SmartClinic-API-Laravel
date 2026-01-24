# Doctors API Documentation

## Overview

The Doctors API provides endpoints to manage doctors (users with doctor or clinic_super_doctor roles) in the SmartClinic system. This follows the clean architecture pattern used throughout the application.

## Files Created

### 1. Controller

- **File**: `app/Http/Controllers/DoctorController.php`
- **Purpose**: Handles HTTP requests for doctor management
- **Methods**: index, store, show, update, destroy, byClinic, active, searchByEmail, searchByPhone

### 2. Repository

- **File**: `app/Repositories/DoctorRepository.php`
- **Purpose**: Data access layer for doctors (users with doctor roles)
- **Features**:
  - Query filtering for doctors only
  - Clinic-based filtering
  - CRUD operations
  - Role management
  - Password hashing

### 3. Request Validation

- **File**: `app/Http/Requests/DoctorRequest.php`
- **Purpose**: Validates incoming doctor data
- **Validates**: name, email, phone, password, clinic_id, role, is_active

### 4. Routes

- **File**: `routes/api.php` (updated)
- **Base Path**: `/api/doctors`
- **Middleware**: JWT authentication required

## API Endpoints

### Standard REST Endpoints

| Method    | Endpoint            | Description                 |
| --------- | ------------------- | --------------------------- |
| GET       | `/api/doctors`      | Get all doctors (paginated) |
| POST      | `/api/doctors`      | Create a new doctor         |
| GET       | `/api/doctors/{id}` | Get a specific doctor       |
| PUT/PATCH | `/api/doctors/{id}` | Update a doctor             |
| DELETE    | `/api/doctors/{id}` | Delete a doctor             |

### Additional Endpoints

| Method | Endpoint                            | Description             |
| ------ | ----------------------------------- | ----------------------- |
| GET    | `/api/doctors-active`               | Get active doctors only |
| GET    | `/api/doctors/clinic/{clinicId}`    | Get doctors by clinic   |
| GET    | `/api/doctors/search/email/{email}` | Search doctor by email  |
| GET    | `/api/doctors/search/phone/{phone}` | Search doctor by phone  |

## Request Examples

### 1. Create a Doctor

```bash
POST /api/doctors
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Dr. John Smith",
  "email": "john.smith@example.com",
  "phone": "+1234567890",
  "password": "securePassword123",
  "clinic_id": 1,
  "role": "doctor",
  "is_active": true
}
```

### 2. Update a Doctor

```bash
PUT /api/doctors/1
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Dr. John Smith Jr.",
  "email": "john.smith@example.com",
  "phone": "+1234567890",
  "is_active": true
}
```

Note: Password is optional when updating.

### 3. Get All Doctors (with filters)

```bash
GET /api/doctors?search=john&per_page=20
Authorization: Bearer {token}
```

### 4. Get Active Doctors

```bash
GET /api/doctors-active
Authorization: Bearer {token}
```

### 5. Get Doctors by Clinic

```bash
GET /api/doctors/clinic/1
Authorization: Bearer {token}
```

## Response Format

### Success Response

```json
{
  "success": true,
  "message": "Doctor retrieved successfully",
  "data": {
    "id": 1,
    "name": "Dr. John Smith",
    "email": "john.smith@example.com",
    "phone": "+1234567890",
    "roles": ["doctor"],
    "permissions": ["view-patients", "create-patient"],
    "is_active": true,
    "created_at": "2026-01-20 10:30:00",
    "updated_at": "2026-01-20 10:30:00"
  }
}
```

### List Response (Paginated)

```json
{
  "success": true,
  "message": "Doctors retrieved successfully",
  "data": [...],
  "pagination": {
    "total": 50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 4,
    "from": 1,
    "to": 15
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Doctor not found"
}
```

## Query Parameters

### Filtering & Searching

- `search` - Search by name, email, or phone
- `filter[name]` - Filter by name
- `filter[email]` - Filter by email
- `filter[phone]` - Filter by phone
- `filter[is_active]` - Filter by active status

### Sorting

- `sort=name` - Sort by name (ascending)
- `sort=-name` - Sort by name (descending)
- `sort=created_at` - Sort by creation date
- `sort=-created_at` - Sort by creation date (newest first)

### Pagination

- `per_page=20` - Number of results per page (default: 15)

### Includes (Relations)

- `include=clinic` - Include clinic details
- `include=roles` - Include roles
- `include=permissions` - Include permissions

## Validation Rules

| Field     | Rules                                       | Notes                       |
| --------- | ------------------------------------------- | --------------------------- |
| name      | required, string, max:255                   | Doctor's full name          |
| email     | required, email, unique, max:255            | Must be unique              |
| phone     | nullable, string, unique, max:33            | Must be unique if provided  |
| password  | required (create), nullable (update), min:8 | Automatically hashed        |
| clinic_id | required, exists:clinics,id                 | Must exist in clinics table |
| role      | nullable, in:doctor,clinic_super_doctor     | Default: 'doctor'           |
| is_active | nullable, boolean                           | Default: true               |

## Permissions (When Enabled)

The controller has permission middleware commented out. Uncomment these lines to enable:

- `view-doctors` - View doctors list and details
- `create-doctor` - Create new doctors
- `edit-doctor` - Update existing doctors
- `delete-doctor` - Delete doctors

## Role-Based Access

### Super Admin

- Can view and manage doctors from all clinics
- No clinic filtering applied

### Other Roles (clinic_super_doctor, doctor, secretary)

- Can only view and manage doctors from their own clinic
- Automatic clinic filtering applied

## Features

### Security

- JWT authentication required
- Password hashing using bcrypt
- Email and phone uniqueness validation
- Role-based access control

### Filtering

- Doctors are automatically filtered by role (only users with 'doctor' or 'clinic_super_doctor' roles)
- Clinic-based filtering for non-super-admin users
- Search across name, email, and phone

### Data Management

- Soft deletes support (if enabled in User model)
- Automatic role assignment
- Default values for is_active and role

## Architecture

The Doctors module follows the clean architecture pattern:

```
Request → Controller → Repository → Model (User) → Database
Response ← Resource ← Repository ← Model
```

### Layers:

1. **Controller** - HTTP request handling
2. **Request** - Input validation
3. **Repository** - Data access and querying
4. **Resource** - Response formatting
5. **Model** - Eloquent ORM (User model with doctor roles)

## Notes

- Doctors are actually `User` model instances with specific roles
- The repository filters users to only include those with doctor-related roles
- Uses the existing `UserResource` for response formatting
- Password is optional when updating (only updated if provided)
- Role can be updated through the update endpoint

## Testing with cURL

### Create Doctor

```bash
curl -X POST http://localhost:8000/api/doctors \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "name": "Dr. Jane Doe",
    "email": "jane.doe@clinic.com",
    "password": "password123",
    "clinic_id": 1
  }'
```

### Get All Doctors

```bash
curl -X GET "http://localhost:8000/api/doctors?per_page=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Update Doctor

```bash
curl -X PUT http://localhost:8000/api/doctors/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "name": "Dr. Jane Doe Updated",
    "is_active": false
  }'
```

### Delete Doctor

```bash
curl -X DELETE http://localhost:8000/api/doctors/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```
