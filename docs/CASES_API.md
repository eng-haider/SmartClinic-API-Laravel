# Cases API Documentation

## Overview

Complete CRUD operations for medical case management with payment tracking and status updates.

**Base URL:** `http://localhost:8000/api/cases`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Cases

Get a paginated list of cases with filtering and sorting.

**Endpoint:** `GET /api/cases`

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
| filter[patient_id] | integer | Filter by patient ID | `filter[patient_id]=1` |
| filter[doctor_id] | integer | Filter by doctor ID | `filter[doctor_id]=2` |
| filter[status_id] | integer | Filter by status ID | `filter[status_id]=1` |
| filter[is_paid] | boolean | Filter by payment status | `filter[is_paid]=0` |
| filter[case_categores_id] | integer | Filter by category ID | `filter[case_categores_id]=3` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-created_at` |
| include | string | Load relationships | `include=patient,doctor,category,status` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Cases retrieved successfully",
  "data": [
    {
      "id": 1,
      "patient_id": 1,
      "doctor_id": 2,
      "clinic_id": 1,
      "case_categores_id": 3,
      "status_id": 1,
      "notes": "Root canal treatment required",
      "price": 50000,
      "tooth_num": "14",
      "root_stuffing": "Composite material",
      "is_paid": false,
      "payment_status": "Unpaid",
      "patient": {
        "id": 1,
        "name": "John Doe",
        "phone": "01001234567"
      },
      "doctor": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "category": {
        "id": 3,
        "name_ar": "حشو الأسنان",
        "name_en": "Tooth Filling"
      },
      "status": {
        "id": 1,
        "name_ar": "جديد",
        "name_en": "New",
        "color": "#3B82F6"
      },
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z",
      "deleted_at": null
    }
  ],
  "pagination": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

Get all cases:

```bash
curl -X GET http://localhost:8000/api/cases \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get unpaid cases:

```bash
curl -X GET "http://localhost:8000/api/cases?filter[is_paid]=0&sort=-created_at" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get cases with relationships:

```bash
curl -X GET "http://localhost:8000/api/cases?include=patient,doctor,category,status&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/cases`
- Params: Add query parameters as needed
- Authorization: Bearer Token

---

### 2. Get Single Case

Retrieve details of a specific case.

**Endpoint:** `GET /api/cases/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Case ID |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case retrieved successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "doctor_id": 2,
    "case_categores_id": 3,
    "status_id": 1,
    "notes": "Root canal treatment required",
    "price": 50000,
    "tooth_num": "14",
    "root_stuffing": "Composite material",
    "is_paid": false,
    "payment_status": "Unpaid",
    "patient": {
      "id": 1,
      "name": "John Doe",
      "phone": "01001234567"
    },
    "doctor": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "category": {
      "id": 3,
      "name_ar": "حشو الأسنان",
      "name_en": "Tooth Filling"
    },
    "status": {
      "id": 1,
      "name_ar": "جديد",
      "name_en": "New",
      "color": "#3B82F6"
    },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Case not found"
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/cases/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/cases/{{case_id}}`
- Authorization: Bearer Token

---

### 3. Create Case

Create a new medical case.

**Endpoint:** `POST /api/cases`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "patient_id": 1,
  "doctor_id": 2,
  "case_categores_id": 3,
  "status_id": 1,
  "notes": "Patient requires root canal treatment",
  "price": 50000,
  "tooth_num": "14",
  "root_stuffing": "Composite material",
  "is_paid": false
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| patient_id | integer | Yes | Patient ID (must exist) |
| doctor_id | integer | Yes | Doctor/User ID (must exist) |
| case_categores_id | integer | Yes | Case category ID (must exist) |
| status_id | integer | Yes | Status ID (must exist) |
| notes | string | No | Case notes (max 5000 characters) |
| price | integer | No | Treatment price (in cents/smallest unit) |
| tooth_num | string | No | Tooth number(s) (max 500 characters) |
| root_stuffing | string | No | Root filling material (max 500 characters) |
| is_paid | boolean | No | Payment status (default: false) |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Case created successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "doctor_id": 2,
    "case_categores_id": 3,
    "status_id": 1,
    "notes": "Patient requires root canal treatment",
    "price": 50000,
    "tooth_num": "14",
    "root_stuffing": "Composite material",
    "is_paid": false,
    "patient": { ... },
    "doctor": { ... },
    "category": { ... },
    "status": { ... },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "patient_id": ["The patient id field is required."],
    "price": ["The price must be at least 0."]
  }
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/cases \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "patient_id": 1,
    "doctor_id": 2,
    "case_categores_id": 3,
    "status_id": 1,
    "notes": "Root canal treatment",
    "price": 50000,
    "tooth_num": "14",
    "is_paid": false
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/cases`
- Body (raw JSON): See request body above
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 201) {
  const response = pm.response.json();
  pm.environment.set("case_id", response.data.id);
}
```

---

### 4. Update Case

Update an existing case.

**Endpoint:** `PUT /api/cases/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Case ID |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "status_id": 2,
  "notes": "Treatment completed successfully",
  "price": 55000,
  "is_paid": true
}
```

**Note:** All fields are optional. Only send fields you want to update.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case updated successfully",
  "data": {
    "id": 1,
    "status_id": 2,
    "notes": "Treatment completed successfully",
    "price": 55000,
    "is_paid": true,
    ...
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Case not found"
}
```

**cURL Example:**

```bash
curl -X PUT http://localhost:8000/api/cases/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "status_id": 2,
    "is_paid": true
  }'
```

**Postman:**

- Method: PUT
- URL: `{{base_url}}/cases/{{case_id}}`
- Body (raw JSON): See request body above
- Authorization: Bearer Token

---

### 5. Delete Case (Soft Delete)

Soft delete a case (can be restored later).

**Endpoint:** `DELETE /api/cases/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Case ID |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Case deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Case not found"
}
```

**cURL Example:**

```bash
curl -X DELETE http://localhost:8000/api/cases/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: DELETE
- URL: `{{base_url}}/cases/{{case_id}}`
- Authorization: Bearer Token

---

## Advanced Filtering Examples

### Get Unpaid Cases for a Patient

```bash
GET /cases?filter[patient_id]=1&filter[is_paid]=0
```

### Get Cases by Doctor and Status

```bash
GET /cases?filter[doctor_id]=2&filter[status_id]=1&sort=-created_at
```

### Get Recent Cases with Relationships

```bash
GET /cases?include=patient,doctor,category,status&sort=-created_at&per_page=10
```

### Complex Filter Query

```bash
GET /cases?filter[is_paid]=0&filter[doctor_id]=2&filter[status_id]=1&include=patient,category&sort=-price&per_page=25
```

---

## Response Status Codes

| Status Code | Description                              |
| ----------- | ---------------------------------------- |
| 200         | OK - Request successful                  |
| 201         | Created - Case created successfully      |
| 404         | Not Found - Case doesn't exist           |
| 422         | Unprocessable Entity - Validation failed |
| 401         | Unauthorized - Authentication required   |
| 500         | Internal Server Error - Server error     |

---

## Postman Collection Variables

```json
{
  "case_id": "",
  "patient_id": "",
  "doctor_id": "",
  "status_id": "",
  "category_id": ""
}
```

---

## Complete Postman Workflow

### 1. Create Case

```
POST /cases
→ Save case_id from response
```

### 2. Get Case

```
GET /cases/{{case_id}}
→ Verify case details
```

### 3. Update Case

```
PUT /cases/{{case_id}}
→ Update case information
```

### 4. Mark as Paid

```
PUT /cases/{{case_id}}
Body: { "is_paid": true }
→ Update payment status
```

### 5. List Cases with Filters

```
GET /cases?filter[is_paid]=0&per_page=15
→ Browse unpaid cases
```

### 6. Delete Case

```
DELETE /cases/{{case_id}}
→ Soft delete case
```

---

## Frontend Integration Example

### React Service

```javascript
import api from "./api";

export const caseService = {
  async getAll(params = {}) {
    return await api.get("/cases", { params });
  },

  async getById(id) {
    return await api.get(`/cases/${id}`);
  },

  async create(caseData) {
    return await api.post("/cases", caseData);
  },

  async update(id, caseData) {
    return await api.put(`/cases/${id}`, caseData);
  },

  async delete(id) {
    return await api.delete(`/cases/${id}`);
  },

  async markAsPaid(id) {
    return await api.put(`/cases/${id}`, { is_paid: true });
  },

  async getUnpaidCases(params = {}) {
    return await api.get("/cases", {
      params: {
        ...params,
        "filter[is_paid]": 0,
      },
    });
  },

  async getCasesByPatient(patientId, params = {}) {
    return await api.get("/cases", {
      params: {
        ...params,
        "filter[patient_id]": patientId,
        include: "doctor,category,status",
      },
    });
  },
};
```

### Vue Component Example

```vue
<template>
  <div>
    <h2>Medical Cases</h2>

    <select v-model="filters.is_paid" @change="fetchCases">
      <option value="">All</option>
      <option value="0">Unpaid</option>
      <option value="1">Paid</option>
    </select>

    <table>
      <tr v-for="case in cases" :key="case.id">
        <td>{{ case.id }}</td>
        <td>{{ case.patient.name }}</td>
        <td>{{ case.category.name_en }}</td>
        <td>{{ case.price }}</td>
        <td>{{ case.is_paid ? 'Paid' : 'Unpaid' }}</td>
        <td>
          <button @click="markAsPaid(case.id)" v-if="!case.is_paid">
            Mark Paid
          </button>
        </td>
      </tr>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { caseService } from "../services/caseService";

const cases = ref([]);
const filters = ref({ is_paid: "" });

const fetchCases = async () => {
  const response = await caseService.getAll({
    "filter[is_paid]": filters.value.is_paid,
    include: "patient,doctor,category,status",
  });
  cases.value = response.data;
};

const markAsPaid = async (id) => {
  await caseService.markAsPaid(id);
  await fetchCases();
};

onMounted(() => {
  fetchCases();
});
</script>
```

---

## Validation Rules

| Field             | Rules                               |
| ----------------- | ----------------------------------- |
| patient_id        | required, exists:patients,id        |
| doctor_id         | required, exists:users,id           |
| case_categores_id | required, exists:case_categories,id |
| status_id         | required, exists:statuses,id        |
| notes             | nullable, string, max:5000          |
| price             | nullable, integer, min:0            |
| tooth_num         | nullable, string, max:500           |
| root_stuffing     | nullable, string, max:500           |
| is_paid           | nullable, boolean                   |

---

## Business Logic Notes

- **Soft Deletes:** Cases are soft-deleted and can be restored
- **Price Format:** Prices are stored in the smallest currency unit (cents)
- **Payment Status:** `is_paid` boolean tracks payment status
- **Relationships:** Cases belong to Patient, Doctor, Category, and Status
- **Tooth Numbers:** Can store multiple tooth numbers as comma-separated string
- **Root Stuffing:** Records the material used for root canal filling
- **Status Tracking:** Use status_id to track case progress (New, In Progress, Completed, etc.)

---

## Common Use Cases

### 1. Create Case and Generate Bill

```javascript
// Create case
const caseResponse = await caseService.create({
  patient_id: 1,
  doctor_id: 2,
  case_categores_id: 3,
  status_id: 1,
  price: 50000,
  is_paid: false,
});

// Create bill for the case
const billResponse = await billService.create({
  patient_id: 1,
  billable_id: caseResponse.data.id,
  billable_type: "App\\Models\\Case",
  price: 50000,
  is_paid: false,
});
```

### 2. Track Case Progress

```javascript
// Update case status
await caseService.update(caseId, {
  status_id: 2, // In Progress
  notes: "First session completed",
});

// Later, mark as completed
await caseService.update(caseId, {
  status_id: 3, // Completed
  is_paid: true,
});
```

### 3. Patient Case History

```javascript
// Get all cases for a patient
const patientCases = await caseService.getAll({
  "filter[patient_id]": patientId,
  include: "doctor,category,status",
  sort: "-created_at",
});
```

---

## Notes

- The field name `case_categores_id` maintains the original typo for backward compatibility
- All monetary values are in the smallest currency unit (e.g., cents)
- Soft deletes are used - deleted cases can be restored
- Use the `include` parameter to eager load relationships
- All timestamps are in UTC timezone
- Payment status (`is_paid`) is separate from bill status

---

**Last Updated:** January 15, 2026  
**API Version:** 1.0
