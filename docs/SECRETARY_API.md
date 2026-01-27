# Secretary Management API Documentation

## Overview

This API allows **clinic_super_doctor** users to manage secretary accounts in their clinic. Each secretary has the same role (`secretary`) but can be assigned custom permissions.

**Base URL:** `/api/secretaries`

**Authentication:** Required (JWT Token - Bearer)

**Authorized Roles:** `clinic_super_doctor` only

---

## Endpoints

### 1. Get All Secretaries

**GET** `/api/secretaries`

Get a paginated list of all secretaries in your clinic.

#### Query Parameters

| Parameter   | Type    | Required | Description                              | Example |
| ----------- | ------- | -------- | ---------------------------------------- | ------- |
| `search`    | string  | No       | Search by name, phone, or email          | `Sarah` |
| `is_active` | boolean | No       | Filter by active status                  | `true`  |
| `per_page`  | integer | No       | Items per page (default: 15)             | `20`    |
| `sort`      | string  | No       | Sort field (default: created_at)         | `name`  |
| `direction` | string  | No       | Sort direction: asc/desc (default: desc) | `asc`   |

#### Example Request

```bash
curl -X GET "http://localhost:8000/api/secretaries?search=Sarah&per_page=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

#### Example Response

```json
{
  "success": true,
  "message": "Secretaries retrieved successfully",
  "data": [
    {
      "id": 5,
      "name": "Sarah Johnson",
      "phone": "07701234567",
      "email": "sarah@smartclinic.com",
      "is_active": true,
      "role": "secretary",
      "all_permissions": [
        "view-own-clinic",
        "view-notes",
        "create-patient",
        "edit-patient",
        "view-clinic-patients"
      ],
      "direct_permissions": [
        "create-patient",
        "edit-patient",
        "view-clinic-patients"
      ],
      "role_permissions": ["view-own-clinic", "view-notes"],
      "permissions_count": 5,
      "created_at": "2026-01-27 10:30:00",
      "updated_at": "2026-01-27 10:30:00"
    }
  ],
  "pagination": {
    "total": 3,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 3
  }
}
```

---

### 2. Create Secretary

**POST** `/api/secretaries`

Create a new secretary with custom permissions.

#### Request Body

| Field         | Type    | Required | Description                   | Example                              |
| ------------- | ------- | -------- | ----------------------------- | ------------------------------------ |
| `name`        | string  | Yes      | Secretary's full name         | `Sarah Johnson`                      |
| `email`       | string  | Yes      | Valid email address           | `sarah@smartclinic.com`              |
| `phone`       | string  | Yes      | Phone number                  | `07701234567`                        |
| `password`    | string  | Yes      | Password (min 8 characters)   | `12345678`                           |
| `is_active`   | boolean | No       | Active status (default: true) | `true`                               |
| `permissions` | array   | No       | Array of permission names     | `["create-patient", "edit-patient"]` |

#### Example Request

```bash
curl -X POST "http://localhost:8000/api/secretaries" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Sarah Johnson",
    "email": "sarah@smartclinic.com",
    "phone": "07701234567",
    "password": "12345678",
    "is_active": true,
    "permissions": [
      "view-clinic-patients",
      "create-patient",
      "edit-patient",
      "view-clinic-reservations",
      "create-reservation"
    ]
  }'
```

#### Example Response

```json
{
  "success": true,
  "message": "Secretary created successfully",
  "data": {
    "id": 5,
    "name": "Sarah Johnson",
    "phone": "07701234567",
    "email": "sarah@smartclinic.com",
    "is_active": true,
    "role": "secretary",
    "all_permissions": [
      "view-own-clinic",
      "view-notes",
      "view-clinic-patients",
      "create-patient",
      "edit-patient",
      "view-clinic-reservations",
      "create-reservation"
    ],
    "direct_permissions": [
      "view-clinic-patients",
      "create-patient",
      "edit-patient",
      "view-clinic-reservations",
      "create-reservation"
    ]
  }
}
```

---

### 3. Get Secretary Details

**GET** `/api/secretaries/{id}`

Get detailed information about a specific secretary.

#### URL Parameters

| Parameter | Type    | Required | Description  |
| --------- | ------- | -------- | ------------ |
| `id`      | integer | Yes      | Secretary ID |

#### Example Request

```bash
curl -X GET "http://localhost:8000/api/secretaries/5" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

#### Example Response

```json
{
  "success": true,
  "message": "Secretary details retrieved successfully",
  "data": {
    "id": 5,
    "name": "Sarah Johnson",
    "phone": "07701234567",
    "email": "sarah@smartclinic.com",
    "is_active": true,
    "role": "secretary",
    "all_permissions": [
      "view-own-clinic",
      "view-notes",
      "create-patient",
      "edit-patient"
    ],
    "direct_permissions": ["create-patient", "edit-patient"],
    "role_permissions": ["view-own-clinic", "view-notes"],
    "created_at": "2026-01-27 10:30:00",
    "updated_at": "2026-01-27 10:30:00"
  }
}
```

---

### 4. Update Secretary

**PUT** `/api/secretaries/{id}`

Update secretary information and/or permissions.

#### URL Parameters

| Parameter | Type    | Required | Description  |
| --------- | ------- | -------- | ------------ |
| `id`      | integer | Yes      | Secretary ID |

#### Request Body

| Field         | Type    | Required | Description               |
| ------------- | ------- | -------- | ------------------------- |
| `name`        | string  | Yes      | Secretary's full name     |
| `email`       | string  | Yes      | Valid email address       |
| `phone`       | string  | Yes      | Phone number              |
| `password`    | string  | No       | New password (optional)   |
| `is_active`   | boolean | No       | Active status             |
| `permissions` | array   | No       | Array of permission names |

#### Example Request

```bash
curl -X PUT "http://localhost:8000/api/secretaries/5" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Sarah Johnson",
    "email": "sarah.new@smartclinic.com",
    "phone": "07701234567",
    "is_active": true,
    "permissions": [
      "view-clinic-patients",
      "create-patient",
      "edit-patient",
      "create-bill",
      "mark-bill-paid"
    ]
  }'
```

---

### 5. Update Secretary Permissions Only

**PATCH** `/api/secretaries/{id}/permissions`

Update only the permissions for a secretary without changing other information.

#### URL Parameters

| Parameter | Type    | Required | Description  |
| --------- | ------- | -------- | ------------ |
| `id`      | integer | Yes      | Secretary ID |

#### Request Body

| Field         | Type  | Required | Description               |
| ------------- | ----- | -------- | ------------------------- |
| `permissions` | array | Yes      | Array of permission names |

#### Example Request

```bash
curl -X PATCH "http://localhost:8000/api/secretaries/5/permissions" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "permissions": [
      "view-clinic-patients",
      "create-patient",
      "view-clinic-bills",
      "create-bill"
    ]
  }'
```

#### Example Response

```json
{
  "success": true,
  "message": "Secretary permissions updated successfully",
  "data": {
    "id": 5,
    "name": "Sarah Johnson",
    "all_permissions": [
      "view-own-clinic",
      "view-notes",
      "view-clinic-patients",
      "create-patient",
      "view-clinic-bills",
      "create-bill"
    ],
    "direct_permissions": [
      "view-clinic-patients",
      "create-patient",
      "view-clinic-bills",
      "create-bill"
    ]
  }
}
```

---

### 6. Toggle Secretary Status

**PATCH** `/api/secretaries/{id}/toggle-status`

Toggle secretary's active/inactive status.

#### URL Parameters

| Parameter | Type    | Required | Description  |
| --------- | ------- | -------- | ------------ |
| `id`      | integer | Yes      | Secretary ID |

#### Example Request

```bash
curl -X PATCH "http://localhost:8000/api/secretaries/5/toggle-status" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

#### Example Response

```json
{
  "success": true,
  "message": "Secretary status updated successfully",
  "data": {
    "id": 5,
    "name": "Sarah Johnson",
    "is_active": false
  }
}
```

---

### 7. Delete Secretary

**DELETE** `/api/secretaries/{id}`

Permanently delete a secretary account.

#### URL Parameters

| Parameter | Type    | Required | Description  |
| --------- | ------- | -------- | ------------ |
| `id`      | integer | Yes      | Secretary ID |

#### Example Request

```bash
curl -X DELETE "http://localhost:8000/api/secretaries/5" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

#### Example Response

```json
{
  "success": true,
  "message": "Secretary deleted successfully"
}
```

---

### 8. Get Available Permissions

**GET** `/api/secretaries/available-permissions`

Get a list of all permissions that can be assigned to secretaries.

#### Example Request

```bash
curl -X GET "http://localhost:8000/api/secretaries/available-permissions" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

#### Example Response

```json
{
  "success": true,
  "message": "Available permissions retrieved successfully",
  "data": {
    "grouped_permissions": {
      "patients": {
        "view-clinic-patients": "View all clinic patients",
        "create-patient": "Create new patients",
        "edit-patient": "Edit patient information",
        "delete-patient": "Delete patients",
        "search-patient": "Search patients"
      },
      "cases": {
        "view-clinic-cases": "View all clinic cases",
        "create-case": "Create new cases",
        "edit-case": "Edit cases",
        "delete-case": "Delete cases"
      },
      "bills": {
        "view-clinic-bills": "View all clinic bills",
        "create-bill": "Create new bills",
        "edit-bill": "Edit bills",
        "delete-bill": "Delete bills",
        "mark-bill-paid": "Mark bills as paid"
      },
      "reservations": {
        "view-clinic-reservations": "View all reservations",
        "create-reservation": "Create new reservations",
        "edit-reservation": "Edit reservations",
        "delete-reservation": "Delete reservations"
      },
      "notes": {
        "create-note": "Create notes",
        "edit-note": "Edit notes",
        "delete-note": "Delete notes"
      }
    },
    "base_role_permissions": ["view-own-clinic", "view-notes"],
    "note": "Base permissions (view-own-clinic, view-notes) are always granted via secretary role and cannot be removed."
  }
}
```

---

## Available Permissions

### Patient Management

- `view-clinic-patients` - View all clinic patients
- `create-patient` - Create new patients
- `edit-patient` - Edit patient information
- `delete-patient` - Delete patients
- `search-patient` - Search patients

### Case Management

- `view-clinic-cases` - View all clinic cases
- `create-case` - Create new cases
- `edit-case` - Edit cases
- `delete-case` - Delete cases

### Bill Management

- `view-clinic-bills` - View all clinic bills
- `create-bill` - Create new bills
- `edit-bill` - Edit bills
- `delete-bill` - Delete bills
- `mark-bill-paid` - Mark bills as paid

### Reservation Management

- `view-clinic-reservations` - View all reservations
- `create-reservation` - Create new reservations
- `edit-reservation` - Edit reservations
- `delete-reservation` - Delete reservations

### Notes

- `create-note` - Create notes
- `edit-note` - Edit notes
- `delete-note` - Delete notes

### Base Permissions (Always Included)

- `view-own-clinic` - View clinic information
- `view-notes` - View notes

---

## Error Responses

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

### 403 Forbidden

```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

### 404 Not Found

```json
{
  "success": false,
  "message": "Secretary not found"
}
```

### 422 Validation Error

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "phone": ["The phone has already been taken."]
  }
}
```

---

## Postman Collection

Import this collection into Postman for easy testing:

```json
{
  "info": {
    "name": "Secretary Management API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get All Secretaries",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/secretaries?search=&per_page=15",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries"],
          "query": [
            { "key": "search", "value": "" },
            { "key": "per_page", "value": "15" }
          ]
        }
      }
    },
    {
      "name": "Create Secretary",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          },
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"name\": \"Sarah Johnson\",\n  \"email\": \"sarah@smartclinic.com\",\n  \"phone\": \"07701234567\",\n  \"password\": \"12345678\",\n  \"is_active\": true,\n  \"permissions\": [\n    \"view-clinic-patients\",\n    \"create-patient\",\n    \"edit-patient\"\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/secretaries",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries"]
        }
      }
    },
    {
      "name": "Get Secretary Details",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/secretaries/5",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries", "5"]
        }
      }
    },
    {
      "name": "Update Secretary",
      "request": {
        "method": "PUT",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          },
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"name\": \"Sarah Johnson\",\n  \"email\": \"sarah@smartclinic.com\",\n  \"phone\": \"07701234567\",\n  \"is_active\": true,\n  \"permissions\": [\n    \"view-clinic-patients\",\n    \"create-patient\"\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/secretaries/5",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries", "5"]
        }
      }
    },
    {
      "name": "Update Secretary Permissions",
      "request": {
        "method": "PATCH",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          },
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"permissions\": [\n    \"view-clinic-patients\",\n    \"create-patient\",\n    \"create-bill\"\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/secretaries/5/permissions",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries", "5", "permissions"]
        }
      }
    },
    {
      "name": "Toggle Secretary Status",
      "request": {
        "method": "PATCH",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/secretaries/5/toggle-status",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries", "5", "toggle-status"]
        }
      }
    },
    {
      "name": "Delete Secretary",
      "request": {
        "method": "DELETE",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/secretaries/5",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries", "5"]
        }
      }
    },
    {
      "name": "Get Available Permissions",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/secretaries/available-permissions",
          "host": ["{{base_url}}"],
          "path": ["api", "secretaries", "available-permissions"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000"
    },
    {
      "key": "token",
      "value": ""
    }
  ]
}
```

---

## Testing

### 1. Login as Clinic Super Doctor

```bash
curl -X POST "http://localhost:8000/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone": "07700281899", "password": "12345678"}'
```

### 2. Create a Secretary

```bash
TOKEN="your_jwt_token_here"

curl -X POST "http://localhost:8000/api/secretaries" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sarah Johnson",
    "email": "sarah@clinic.com",
    "phone": "07701234567",
    "password": "12345678",
    "permissions": [
      "view-clinic-patients",
      "create-patient",
      "edit-patient"
    ]
  }'
```

### 3. List All Secretaries

```bash
curl -X GET "http://localhost:8000/api/secretaries" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Frontend Integration Example

```javascript
// React/Vue/Angular Example
const SecretaryManagement = () => {
  const [secretaries, setSecretaries] = useState([]);
  const [availablePermissions, setAvailablePermissions] = useState({});

  // Fetch all secretaries
  const fetchSecretaries = async () => {
    const response = await fetch("/api/secretaries", {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });
    const data = await response.json();
    setSecretaries(data.data);
  };

  // Create secretary
  const createSecretary = async (secretaryData) => {
    const response = await fetch("/api/secretaries", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(secretaryData),
    });
    return await response.json();
  };

  // Update permissions
  const updatePermissions = async (secretaryId, permissions) => {
    const response = await fetch(
      `/api/secretaries/${secretaryId}/permissions`,
      {
        method: "PATCH",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ permissions }),
      },
    );
    return await response.json();
  };

  return (
    <div>
      <button onClick={fetchSecretaries}>Load Secretaries</button>
      {/* Render secretaries list */}
    </div>
  );
};
```

---

## Complete! ✅

You now have:

- ✅ **SecretaryRepository** - Data access layer
- ✅ **SecretaryController** - API endpoints
- ✅ **SecretaryRequest** - Validation
- ✅ **Routes** - All API routes configured
- ✅ **Complete API Documentation**
- ✅ **Postman Collection** for testing
- ✅ **Frontend integration examples**
