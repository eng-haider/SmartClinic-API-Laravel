# Cases API Documentation

## Overview

This document describes the Cases API endpoints for the SmartClinic API. All endpoints require JWT authentication unless specified otherwise.

## Base URL

```
/api/cases
```

## Authentication

All endpoints require a valid JWT token in the Authorization header:

```
Authorization: Bearer {your_jwt_token}
```

---

## Endpoints

### 1. Get All Cases

**GET** `/api/cases`

Get a paginated list of all cases with filtering and sorting capabilities.

**Query Parameters:**

- `per_page` (optional): Number of items per page (default: 15)
- `filter[patient_id]`: Filter by patient ID
- `filter[doctor_id]`: Filter by doctor ID
- `filter[status_id]`: Filter by status ID
- `filter[is_paid]`: Filter by payment status (0 or 1)
- `sort`: Sort by field (e.g., `-created_at`, `price`)
- `include`: Include relationships (patient, doctor, category, status)

**Response:**

```json
{
  "success": true,
  "message": "Cases retrieved successfully",
  "data": [...],
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

---

### 2. Create Case

**POST** `/api/cases`

Create a new medical case.

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
  "item_cost": 5000,
  "root_stuffing": "Composite material",
  "is_paid": false
}
```

**Validation Rules:**

- `patient_id`: required, must exist in patients table
- `doctor_id`: required, must exist in users table
- `case_categores_id`: required, must exist in case_categories table
- `status_id`: required, must exist in statuses table
- `notes`: optional, string, max 5000 characters
- `price`: optional, integer, min 0
- `tooth_num`: optional, string, max 500 characters
- `item_cost`: optional, integer, min 0
- `root_stuffing`: optional, string, max 500 characters
- `is_paid`: optional, boolean

**Response (201 Created):**

```json
{
  "success": true,
  "message": "Case created successfully",
  "data": {
    "id": 1,
    "patient": {...},
    "doctor": {...},
    "category": {...},
    "status": {...},
    ...
  }
}
```

---

### 3. Get Case by ID

**GET** `/api/cases/{id}`

Get details of a specific case.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case retrieved successfully",
  "data": {
    "id": 1,
    "patient": {
      "id": 1,
      "name": "John Doe",
      "phone": "07700000001"
    },
    "doctor": {
      "id": 2,
      "name": "Dr. Smith",
      "phone": "07700000002"
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
    "notes": "Patient requires root canal treatment",
    "price": 50000,
    "tooth_num": "14",
    "item_cost": 5000,
    "root_stuffing": "Composite material",
    "is_paid": false,
    "payment_status": "Unpaid",
    "created_at": "2025-12-09 10:30:00",
    "updated_at": "2025-12-09 10:30:00",
    "deleted_at": null
  }
}
```

---

### 4. Update Case

**PUT/PATCH** `/api/cases/{id}`

Update an existing case.

**Request Body:** (Same as Create Case)

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case updated successfully",
  "data": {...}
}
```

---

### 5. Delete Case (Soft Delete)

**DELETE** `/api/cases/{id}`

Soft delete a case (can be restored later).

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case deleted successfully"
}
```

---

### 6. Restore Case

**POST** `/api/cases/{id}/restore`

Restore a soft-deleted case.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case restored successfully"
}
```

---

### 7. Force Delete Case

**DELETE** `/api/cases/{id}/force`

Permanently delete a case (cannot be restored).

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case permanently deleted"
}
```

---

## Filter Endpoints

### 8. Get Cases by Patient

**GET** `/api/cases/patient/{patientId}`

Get all cases for a specific patient.

**Query Parameters:**

- `per_page` (optional): Number of items per page (default: 15)

---

### 9. Get Cases by Doctor

**GET** `/api/cases/doctor/{doctorId}`

Get all cases assigned to a specific doctor.

---

### 10. Get Cases by Status

**GET** `/api/cases/status/{statusId}`

Get all cases with a specific status.

---

### 11. Get Cases by Category

**GET** `/api/cases/category/{categoryId}`

Get all cases in a specific category.

---

## Payment Endpoints

### 12. Get Paid Cases

**GET** `/api/cases/payment/paid`

Get all paid cases.

---

### 13. Get Unpaid Cases

**GET** `/api/cases/payment/unpaid`

Get all unpaid cases.

---

### 14. Mark Case as Paid

**PATCH** `/api/cases/{id}/mark-paid`

Mark a case as paid.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case marked as paid successfully"
}
```

---

### 15. Mark Case as Unpaid

**PATCH** `/api/cases/{id}/mark-unpaid`

Mark a case as unpaid.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case marked as unpaid successfully"
}
```

---

### 16. Update Case Status

**PATCH** `/api/cases/{id}/status`

Update the status of a case.

**Request Body:**

```json
{
  "status_id": 2
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Case status updated successfully"
}
```

---

### 17. Get Statistics

**GET** `/api/cases-statistics`

Get revenue and payment statistics.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Statistics retrieved successfully",
  "data": {
    "total_revenue": 500000,
    "total_unpaid": 150000,
    "total_expected": 650000,
    "payment_rate": 76.92
  }
}
```

---

## Error Responses

### 404 Not Found

```json
{
  "success": false,
  "message": "Case not found"
}
```

### 422 Validation Error

```json
{
  "success": false,
  "message": "Validation error message"
}
```

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

---

## Usage Examples

### Using cURL

#### Create a Case

```bash
curl -X POST http://127.0.0.1:8000/api/cases \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "patient_id": 1,
    "doctor_id": 2,
    "case_categores_id": 3,
    "status_id": 1,
    "notes": "Root canal treatment needed",
    "price": 50000,
    "tooth_num": "14",
    "is_paid": false
  }'
```

#### Get All Cases with Filters

```bash
curl -X GET "http://127.0.0.1:8000/api/cases?filter[is_paid]=0&sort=-created_at&per_page=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

#### Mark Case as Paid

```bash
curl -X PATCH http://127.0.0.1:8000/api/cases/1/mark-paid \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

#### Get Revenue Statistics

```bash
curl -X GET http://127.0.0.1:8000/api/cases-statistics \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Notes

1. All monetary values are in the smallest currency unit (e.g., cents)
2. The `case_categores_id` field name maintains the original typo for backward compatibility
3. Soft-deleted cases can be restored using the restore endpoint
4. Use the `include` parameter to eager load relationships and reduce API calls
5. Dates are returned in `Y-m-d H:i:s` format
6. All endpoints support pagination with `per_page` parameter
