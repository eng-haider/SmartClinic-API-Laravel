# Notes API Documentation

## Overview

Complete CRUD operations for managing notes. Notes can be attached to different models (Patients, Cases) using polymorphic relationships.

**Base URL:** `http://localhost:8000/api/notes`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Notes

Get a paginated list of notes with filtering and sorting.

**Endpoint:** `GET /api/notes`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| per_page | integer | Items per page (default: 15) | `per_page=20` |
| page | integer | Page number (default: 1) | `page=2` |
| filter[noteable_id] | integer | Filter by related item ID | `filter[noteable_id]=1` |
| filter[noteable_type] | string | Filter by model type | `filter[noteable_type]=App\Models\Patient` |
| filter[created_by] | integer | Filter by creator user ID | `filter[created_by]=2` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-created_at` |
| include | string | Load relationships | `include=creator,noteable` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Notes retrieved successfully",
  "data": [
    {
      "id": 1,
      "noteable_id": 5,
      "noteable_type": "App\\Models\\Patient",
      "content": "Patient has a history of diabetes. Monitor blood sugar levels.",
      "created_by": 2,
      "creator": {
        "id": 2,
        "name": "Dr. Ahmed Hassan",
        "email": "ahmed@smartdental.com"
      },
      "created_at": "2026-01-20 10:30:00",
      "updated_at": "2026-01-20 10:30:00"
    }
  ],
  "pagination": {
    "total": 25,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

```bash
# Basic request
curl -X GET "http://localhost:8000/api/notes" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# With filters
curl -X GET "http://localhost:8000/api/notes?filter[noteable_type]=App\Models\Patient&filter[noteable_id]=5" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# Include relationships
curl -X GET "http://localhost:8000/api/notes?include=creator,noteable" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

### 2. Get Notes by Noteable

Get all notes for a specific item (patient, case, etc.)

**Endpoint:** `GET /api/notes/{noteableType}/{noteableId}`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| noteableType | string | Yes | Type: `patient` or `case` |
| noteableId | integer | Yes | ID of the patient or case |

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| per_page | integer | Items per page (default: 15) | `per_page=20` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Notes retrieved successfully",
  "data": [
    {
      "id": 1,
      "noteable_id": 5,
      "noteable_type": "App\\Models\\Patient",
      "content": "Patient has a history of diabetes.",
      "created_by": 2,
      "creator": {
        "id": 2,
        "name": "Dr. Ahmed Hassan",
        "email": "ahmed@smartdental.com"
      },
      "created_at": "2026-01-20 10:30:00",
      "updated_at": "2026-01-20 10:30:00"
    }
  ],
  "pagination": {
    "total": 5,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 5
  }
}
```

**cURL Examples:**

```bash
# Get all notes for patient ID 5
curl -X GET "http://localhost:8000/api/notes/patient/5" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# Get all notes for case ID 10
curl -X GET "http://localhost:8000/api/notes/case/10" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

### 3. Get Single Note

Retrieve details of a specific note.

**Endpoint:** `GET /api/notes/{id}`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Note ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Note retrieved successfully",
  "data": {
    "id": 1,
    "noteable_id": 5,
    "noteable_type": "App\\Models\\Patient",
    "content": "Patient has a history of diabetes. Monitor blood sugar levels.",
    "created_by": 2,
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan",
      "email": "ahmed@smartdental.com"
    },
    "created_at": "2026-01-20 10:30:00",
    "updated_at": "2026-01-20 10:30:00"
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Note not found"
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/notes/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

### 4. Create Note

Create a new note.

**Endpoint:** `POST /api/notes`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "noteable_id": 5,
  "noteable_type": "App\\Models\\Patient",
  "content": "Patient allergic to penicillin. Prescribed alternative antibiotics."
}
```

**Request Fields:**
| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| noteable_id | integer | Yes | ID of the related item | Must be valid integer |
| noteable_type | string | Yes | Model type | Must be `App\Models\Patient` or `App\Models\CaseModel` |
| content | string | Yes | Note content | Required string |
| created_by | integer | No | Auto-filled from auth user | Must exist in users table |

**Note:** The `created_by` field is automatically set from the authenticated user.

**Success Response (201):**

```json
{
  "success": true,
  "message": "Note created successfully",
  "data": {
    "id": 15,
    "noteable_id": 5,
    "noteable_type": "App\\Models\\Patient",
    "content": "Patient allergic to penicillin. Prescribed alternative antibiotics.",
    "created_by": 2,
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan",
      "email": "ahmed@smartdental.com"
    },
    "created_at": "2026-01-21 14:20:00",
    "updated_at": "2026-01-21 14:20:00"
  }
}
```

**Validation Error Response (422):**

```json
{
  "success": false,
  "message": "Note content is required"
}
```

**cURL Example:**

```bash
curl -X POST "http://localhost:8000/api/notes" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "noteable_id": 5,
    "noteable_type": "App\\Models\\Patient",
    "content": "Patient allergic to penicillin. Prescribed alternative antibiotics."
  }'
```

---

### 5. Update Note

Update an existing note.

**Endpoint:** `PUT /api/notes/{id}`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Note ID |

**Request Body:**

```json
{
  "noteable_id": 5,
  "noteable_type": "App\\Models\\Patient",
  "content": "Updated: Patient allergic to penicillin and aspirin. Use alternative medications."
}
```

**Request Fields:**
| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| noteable_id | integer | Yes | ID of the related item | Must be valid integer |
| noteable_type | string | Yes | Model type | Must be `App\Models\Patient` or `App\Models\CaseModel` |
| content | string | Yes | Note content | Required string |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Note updated successfully",
  "data": {
    "id": 15,
    "noteable_id": 5,
    "noteable_type": "App\\Models\\Patient",
    "content": "Updated: Patient allergic to penicillin and aspirin. Use alternative medications.",
    "created_by": 2,
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan",
      "email": "ahmed@smartdental.com"
    },
    "created_at": "2026-01-21 14:20:00",
    "updated_at": "2026-01-21 15:30:00"
  }
}
```

**Validation Error Response (422):**

```json
{
  "success": false,
  "message": "Note content is required"
}
```

**cURL Example:**

```bash
curl -X PUT "http://localhost:8000/api/notes/15" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "noteable_id": 5,
    "noteable_type": "App\\Models\\Patient",
    "content": "Updated: Patient allergic to penicillin and aspirin."
  }'
```

---

### 6. Delete Note

Delete a note.

**Endpoint:** `DELETE /api/notes/{id}`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Note ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Note deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Note with ID 15 not found"
}
```

**cURL Example:**

```bash
curl -X DELETE "http://localhost:8000/api/notes/15" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

## Data Model

### Note Object

```json
{
  "id": 1,
  "noteable_id": 5,
  "noteable_type": "App\\Models\\Patient",
  "content": "Patient has a history of diabetes.",
  "created_by": 2,
  "creator": {
    "id": 2,
    "name": "Dr. Ahmed Hassan",
    "email": "ahmed@smartdental.com"
  },
  "created_at": "2026-01-20 10:30:00",
  "updated_at": "2026-01-20 10:30:00"
}
```

**Field Descriptions:**

| Field         | Type     | Description                                   |
| ------------- | -------- | --------------------------------------------- |
| id            | integer  | Unique identifier                             |
| noteable_id   | integer  | ID of the related model (patient, case, etc.) |
| noteable_type | string   | Full class name of the related model          |
| content       | string   | The note content/text                         |
| created_by    | integer  | ID of the user who created the note           |
| creator       | object   | Creator user details (when included)          |
| created_at    | datetime | Timestamp when the note was created           |
| updated_at    | datetime | Timestamp when the note was last updated      |

---

## Validation Rules

### Create/Update Request

| Field         | Rules                                                                    |
| ------------- | ------------------------------------------------------------------------ |
| noteable_id   | Required, Integer                                                        |
| noteable_type | Required, String, Must be `App\Models\Patient` or `App\Models\CaseModel` |
| content       | Required, String                                                         |
| created_by    | Optional, Integer, Must exist in users table (auto-filled from auth)     |

### Custom Error Messages

- `noteable_id.required`: "The related item ID is required"
- `noteable_id.integer`: "The related item ID must be an integer"
- `noteable_type.required`: "The related item type is required"
- `noteable_type.in`: "The related item type must be either Patient or Case"
- `content.required`: "Note content is required"
- `content.string`: "Note content must be a string"
- `created_by.exists`: "The selected user does not exist"

---

## Response Codes

| Code | Description                                            |
| ---- | ------------------------------------------------------ |
| 200  | Success - Request completed successfully               |
| 201  | Created - New resource created successfully            |
| 401  | Unauthorized - Invalid or missing authentication token |
| 403  | Forbidden - User lacks required permissions            |
| 404  | Not Found - Resource does not exist                    |
| 422  | Validation Error - Invalid request data                |
| 500  | Server Error - Internal server error                   |

---

## Common Use Cases

### 1. Get All Notes for a Patient

```bash
curl -X GET "http://localhost:8000/api/notes/patient/5" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### 2. Add Note to Patient

```bash
curl -X POST "http://localhost:8000/api/notes" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "noteable_id": 5,
    "noteable_type": "App\\Models\\Patient",
    "content": "Patient requires follow-up appointment in 2 weeks."
  }'
```

### 3. Add Note to Case

```bash
curl -X POST "http://localhost:8000/api/notes" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "noteable_id": 10,
    "noteable_type": "App\\Models\\CaseModel",
    "content": "Treatment completed successfully. Patient reported no pain."
  }'
```

### 4. Get Notes by Creator

```bash
curl -X GET "http://localhost:8000/api/notes?filter[created_by]=2&include=creator" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

## Notes

1. **Polymorphic Relationship**: Notes use Laravel's polymorphic relationships to attach to multiple model types
2. **Auto Creator**: The `created_by` field is automatically set from the authenticated user
3. **Noteable Types**: Currently supports `App\Models\Patient` and `App\Models\CaseModel`
4. **Short Type Names**: The `byNoteable` endpoint accepts short names (`patient`, `case`) which are mapped to full class names
5. **Includes**: Use `include=creator,noteable` to load related data
6. **Sorting**: Default sorting is by `created_at` descending (newest first)

---

## Related Documentation

- [Patients API](./PATIENTS_API.md) - Patient management
- [Cases API](./CASES_API.md) - Medical cases management
- [Authentication API](./AUTH_API.md) - User authentication

---

## Changelog

### Version 1.0.0 (2026-01-21)

- Initial documentation for Notes API
- CRUD operations
- Polymorphic relationships support
- Auto creator assignment from authenticated user
