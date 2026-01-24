# Case Categories API Documentation

## Overview

Complete CRUD operations for managing medical case categories. Case categories are used to classify different types of medical procedures or treatments offered by the clinic.

**Base URL:** `http://localhost:8000/api/case-categories`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Case Categories

Get a paginated list of case categories with filtering and sorting.

**Endpoint:** `GET /api/case-categories`

**Authentication:** Required

**Permissions:** `view-clinic-cases`

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
| search | string | Search by category name | `search=Filling` |
| filter | array | Additional filters | `filter[clinic_id]=1` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-order` |
| include | string | Load relationships | `include=cases` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Tooth Filling",
      "order": 1,
      "clinic_id": 1,
      "item_cost": 25000,
      "created_at": "2026-01-15 10:00:00",
      "updated_at": "2026-01-15 10:00:00"
    },
    {
      "id": 2,
      "name": "Root Canal",
      "order": 2,
      "clinic_id": 1,
      "item_cost": 50000,
      "created_at": "2026-01-16 11:30:00",
      "updated_at": "2026-01-16 11:30:00"
    }
  ],
  "pagination": {
    "total": 10,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 10
  }
}
```

**cURL Examples:**

```bash
# Basic request
curl -X GET "http://localhost:8000/api/case-categories" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# With search
curl -X GET "http://localhost:8000/api/case-categories?search=Filling" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# With sorting by order
curl -X GET "http://localhost:8000/api/case-categories?sort=order" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# With pagination
curl -X GET "http://localhost:8000/api/case-categories?per_page=20&page=1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

### 2. Get Single Case Category

Retrieve details of a specific case category.

**Endpoint:** `GET /api/case-categories/{id}`

**Authentication:** Required

**Permissions:** `view-clinic-cases`

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Case category ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case category retrieved successfully",
  "data": {
    "id": 1,
    "name": "Tooth Filling",
    "order": 1,
    "clinic_id": 1,
    "item_cost": 25000,
    "created_at": "2026-01-15 10:00:00",
    "updated_at": "2026-01-15 10:00:00"
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Case category not found"
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/case-categories/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

### 3. Create Case Category

Create a new case category.

**Endpoint:** `POST /api/case-categories`

**Authentication:** Required

**Permissions:** `create-case`

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "name": "Tooth Extraction",
  "order": 3,
  "item_cost": 30000
}
```

**Request Fields:**
| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| name | string | Yes | Category name | Max 255 characters |
| order | integer | No | Display order | Min: 0 |
| clinic_id | integer | No | Clinic ID (auto-filled from auth user) | Must exist in clinics table |
| item_cost | integer | No | Default cost for this category | Min: 0 |

**Note:** The `clinic_id` is automatically set from the authenticated user's clinic. You can optionally provide it to override (super admin only).

**Success Response (201):**

```json
{
  "success": true,
  "message": "Case category created successfully",
  "data": {
    "id": 3,
    "name": "Tooth Extraction",
    "order": 3,
    "clinic_id": 1,
    "item_cost": 30000,
    "created_at": "2026-01-20 14:30:00",
    "updated_at": "2026-01-20 14:30:00"
  }
}
```

**Validation Error Response (422):**

```json
{
  "success": false,
  "message": "The name field is required."
}
```

**cURL Example:**

```bash
curl -X POST "http://localhost:8000/api/case-categories" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tooth Extraction",
    "order": 3,
    "item_cost": 30000
  }'
```

---

### 4. Update Case Category

Update an existing case category.

**Endpoint:** `PUT /api/case-categories/{id}`

**Authentication:** Required

**Permissions:** `edit-case`

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Case category ID |

**Request Body:**

```json
{
  "name": "Tooth Extraction (Updated)",
  "order": 5,
  "item_cost": 35000
}
```

**Request Fields:**
| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| name | string | Yes | Category name | Max 255 characters |
| order | integer | No | Display order | Min: 0 |
| clinic_id | integer | No | Clinic ID (auto-filled from auth user) | Must exist in clinics table |
| item_cost | integer | No | Default cost for this category | Min: 0 |

**Note:** The `clinic_id` is automatically set from the authenticated user's clinic. You can optionally provide it to override (super admin only).

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case category updated successfully",
  "data": {
    "id": 3,
    "name": "Tooth Extraction (Updated)",
    "order": 5,
    "clinic_id": 1,
    "item_cost": 35000,
    "created_at": "2026-01-20 14:30:00",
    "updated_at": "2026-01-20 15:45:00"
  }
}
```

**Validation Error Response (422):**

```json
{
  "success": false,
  "message": "The clinic_id field must exist in clinics table."
}
```

**cURL Example:**

```bash
curl -X PUT "http://localhost:8000/api/case-categories/3" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tooth Extraction (Updated)",
    "order": 5,
    "item_cost": 35000
  }'
```

---

### 5. Delete Case Category

Delete a case category.

**Endpoint:** `DELETE /api/case-categories/{id}`

**Authentication:** Required

**Permissions:** `delete-case`

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Case category ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case category deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Case category not found"
}
```

**cURL Example:**

```bash
curl -X DELETE "http://localhost:8000/api/case-categories/3" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

---

## Data Model

### CaseCategory Object

```json
{
  "id": 1,
  "name": "Tooth Filling",
  "order": 1,
  "clinic_id": 1,
  "item_cost": 25000,
  "created_at": "2026-01-15 10:00:00",
  "updated_at": "2026-01-15 10:00:00"
}
```

**Field Descriptions:**

| Field      | Type     | Description                                                |
| ---------- | -------- | ---------------------------------------------------------- |
| id         | integer  | Unique identifier                                          |
| name       | string   | Category name (e.g., "Tooth Filling", "Root Canal")        |
| order      | integer  | Display order for sorting categories                       |
| clinic_id  | integer  | ID of the clinic this category belongs to                  |
| item_cost  | integer  | Default cost for this category (in smallest currency unit) |
| created_at | datetime | Timestamp when the category was created                    |
| updated_at | datetime | Timestamp when the category was last updated               |

---

## Validation Rules

### Create/Update Request

| Field     | Rules                                          |
| --------- | ---------------------------------------------- |
| name      | Required, String, Max: 255 characters          |
| order     | Optional, Integer, Min: 0                      |
| clinic_id | Required, Integer, Must exist in clinics table |
| item_cost | Optional, Integer, Min: 0                      |

### Custom Error Messages

- `name.required`: "Category name is required"
- `name.string`: "Category name must be a string"
- `name.max`: "Category name must not exceed 255 characters"
- `clinic_id.required`: "Clinic ID is required"
- `clinic_id.exists`: "The selected clinic does not exist"
- `order.integer`: "Order must be an integer"
- `order.min`: "Order must be at least 0"
- `item_cost.integer`: "Item cost must be an integer"
- `item_cost.min`: "Item cost must be at least 0"

---

## Permissions

The following permissions are required for case category operations:

| Operation       | Permission          |
| --------------- | ------------------- |
| List Categories | `view-clinic-cases` |
| View Category   | `view-clinic-cases` |
| Create Category | `create-case`       |
| Update Category | `edit-case`         |
| Delete Category | `delete-case`       |

---

## Role-Based Access

### Super Admin

- Can view and manage case categories for all clinics
- No clinic restriction applied

### Other Roles (Admin, Doctor, Receptionist)

- Can only view and manage case categories for their assigned clinic
- Automatically filtered by `clinic_id`

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

### 1. Get All Categories for a Clinic

```bash
curl -X GET "http://localhost:8000/api/case-categories?filter[clinic_id]=1&sort=order" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### 2. Search Categories by Name

```bash
curl -X GET "http://localhost:8000/api/case-categories?search=filling" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### 3. Create a New Category

```bash
curl -X POST "http://localhost:8000/api/case-categories" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Teeth Whitening",
    "order": 10,
    "item_cost": 40000
  }'
```

### 4. Update Category Cost

```bash
curl -X PUT "http://localhost:8000/api/case-categories/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tooth Filling",
    "order": 1,
    "item_cost": 28000
  }'
```

### 5. Reorder Categories

```bash
# Update order of multiple categories
curl -X PUT "http://localhost:8000/api/case-categories/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tooth Filling",
    "order": 5,
    "item_cost": 25000
  }'
```

---

## Notes

1. **Clinic Isolation**: Users can only access categories belonging to their clinic (except super admins)
2. **Auto Clinic Assignment**: The `clinic_id` is automatically set from the authenticated user's clinic if not provided
3. **Order Field**: Used for custom sorting of categories in the UI
4. **Item Cost**: Stored as integer (smallest currency unit, e.g., cents or fils)
5. **Relationships**: Case categories are linked to cases via `case_categores_id` field
6. **Soft Deletes**: Not implemented - deletion is permanent
7. **Search**: The search parameter searches within the category name field

---

## Testing with Postman

You can import the [POSTMAN_COLLECTION.json](./POSTMAN_COLLECTION.json) file to test all case category endpoints.

### Quick Test Sequence:

1. Login to get authentication token
2. Create a new case category
3. List all case categories
4. Get single case category by ID
5. Update the case category
6. Delete the case category

---

## Related Documentation

- [Cases API](./CASES_API.md) - Medical cases management
- [Authentication API](./AUTH_API.md) - User authentication
- [Patients API](./PATIENTS_API.md) - Patient management

---

## Changelog

### Version 1.0.0 (2026-01-20)

- Initial documentation for Case Categories API
- CRUD operations
- Permission-based access control
- Role-based filtering
