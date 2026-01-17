# Patients API Documentation

## Overview

Complete CRUD operations for patient management with advanced filtering, sorting, and search capabilities.

**Base URL:** `http://localhost:8000/api/patients`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Patients

Get a paginated list of patients with filtering and sorting.

**Endpoint:** `GET /api/patients`

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
| filter[doctor_id] | integer | Filter by doctor ID | `filter[doctor_id]=1` |
| filter[clinics_id] | integer | Filter by clinic ID | `filter[clinics_id]=1` |
| filter[sex] | integer | Filter by gender (1=Male, 2=Female) | `filter[sex]=1` |
| search | string | Search in name, phone, identifier | `search=john` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-created_at` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Patients retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "age": 30,
      "phone": "01001234567",
      "sex": 1,
      "sex_label": "Male",
      "address": "123 Main St, Cairo",
      "birth_date": "1994-01-15",
      "systemic_conditions": "Diabetes",
      "notes": "Regular patient",
      "identifier": "P-2026-001",
      "credit_balance": 0,
      "credit_balance_add_at": null,
      "doctor": {
        "id": 2,
        "name": "Dr. Ahmed Hassan",
        "phone": "01009876543"
      },
      "clinic": {
        "id": 1,
        "name": "Main Clinic"
      },
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z"
    }
  ],
  "pagination": {
    "total": 150,
    "per_page": 15,
    "current_page": 1,
    "last_page": 10,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

Get all patients:

```bash
curl -X GET http://localhost:8000/api/patients \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Filter by gender (male):

```bash
curl -X GET "http://localhost:8000/api/patients?filter[sex]=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Search and sort:

```bash
curl -X GET "http://localhost:8000/api/patients?search=john&sort=-created_at&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/patients`
- Params: Add query parameters as needed
- Authorization: Bearer Token

---

### 2. Get Single Patient

Retrieve details of a specific patient.

**Endpoint:** `GET /api/patients/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Patient ID |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Patient retrieved successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "age": 30,
    "phone": "01001234567",
    "sex": 1,
    "sex_label": "Male",
    "address": "123 Main St, Cairo",
    "birth_date": "1994-01-15",
    "systemic_conditions": "Diabetes",
    "notes": "Regular patient",
    "identifier": "P-2026-001",
    "credit_balance": 0,
    "doctor": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "clinic": {
      "id": 1,
      "name": "Main Clinic"
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
  "message": "Patient not found"
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/patients/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/patients/1`
- Authorization: Bearer Token

---

### 3. Create Patient

Create a new patient record.

**Endpoint:** `POST /api/patients`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "name": "John Doe",
  "age": 30,
  "phone": "01001234567",
  "sex": 1,
  "address": "123 Main St, Cairo",
  "birth_date": "1994-01-15",
  "systemic_conditions": "None",
  "notes": "New patient",
  "doctor_id": 2,
  "clinics_id": 1,
  "from_where_come_id": 1,
  "identifier": "P-2026-001",
  "credit_balance": 0
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Patient's full name |
| age | integer | No | Patient's age |
| phone | string | Yes | Unique phone number |
| sex | integer | Yes | 1 = Male, 2 = Female |
| address | string | No | Patient's address |
| birth_date | date | No | Format: YYYY-MM-DD |
| systemic_conditions | text | No | Medical conditions |
| notes | text | No | Additional notes |
| doctor_id | integer | No | Assigned doctor ID |
| clinics_id | integer | No | Clinic ID |
| from_where_come_id | integer | No | Referral source ID |
| identifier | string | No | Unique identifier |
| credit_balance | integer | No | Credit balance amount |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Patient created successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "age": 30,
    "phone": "01001234567",
    ...
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": ["The phone has already been taken."],
    "name": ["The name field is required."]
  }
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/patients \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "John Doe",
    "age": 30,
    "phone": "01001234567",
    "sex": 1,
    "birth_date": "1994-01-15",
    "doctor_id": 2,
    "clinics_id": 1
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/patients`
- Body (raw JSON): See request body above
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 201) {
  const response = pm.response.json();
  pm.environment.set("patient_id", response.data.id);
}
```

---

### 4. Update Patient

Update an existing patient's information.

**Endpoint:** `PUT /api/patients/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Patient ID |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "name": "John Doe Updated",
  "age": 31,
  "phone": "01001234567",
  "address": "456 New Street, Cairo",
  "notes": "Updated information"
}
```

**Note:** All fields are optional. Only send fields you want to update.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Patient updated successfully",
  "data": {
    "id": 1,
    "name": "John Doe Updated",
    "age": 31,
    ...
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Patient not found"
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": ["The phone has already been taken."]
  }
}
```

**cURL Example:**

```bash
curl -X PUT http://localhost:8000/api/patients/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "John Doe Updated",
    "age": 31
  }'
```

**Postman:**

- Method: PUT
- URL: `{{base_url}}/patients/{{patient_id}}`
- Body (raw JSON): See request body above
- Authorization: Bearer Token

---

### 5. Delete Patient (Soft Delete)

Soft delete a patient record (can be restored later).

**Endpoint:** `DELETE /api/patients/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Patient ID |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Patient deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Patient not found"
}
```

**cURL Example:**

```bash
curl -X DELETE http://localhost:8000/api/patients/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: DELETE
- URL: `{{base_url}}/patients/{{patient_id}}`
- Authorization: Bearer Token

---

### 6. Search Patient by Phone

Find a patient using their phone number.

**Endpoint:** `GET /api/patients/search/phone/{phone}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| phone | string | Patient's phone number |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Patient found",
  "data": {
    "id": 1,
    "name": "John Doe",
    "phone": "01001234567",
    ...
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Patient not found"
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/patients/search/phone/01001234567 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/patients/search/phone/01001234567`
- Authorization: Bearer Token

---

### 7. Search Patient by Email

Find a patient using their email address.

**Endpoint:** `GET /api/patients/search/email/{email}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| email | string | Patient's email address |

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Patient found",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    ...
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Patient not found"
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/patients/search/email/john@example.com" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/patients/search/email/john@example.com`
- Authorization: Bearer Token

---

## Advanced Filtering Examples

### Filter by Multiple Fields

```bash
GET /patients?filter[sex]=1&filter[doctor_id]=2&filter[clinics_id]=1
```

### Search with Filter

```bash
GET /patients?search=john&filter[sex]=1&sort=-created_at
```

### Pagination with Sorting

```bash
GET /patients?per_page=20&page=2&sort=name
```

### Complex Query

```bash
GET /patients?search=cairo&filter[sex]=1&filter[doctor_id]=2&sort=-created_at&per_page=25&page=1
```

---

## Response Status Codes

| Status Code | Description                              |
| ----------- | ---------------------------------------- |
| 200         | OK - Request successful                  |
| 201         | Created - Patient created successfully   |
| 404         | Not Found - Patient doesn't exist        |
| 422         | Unprocessable Entity - Validation failed |
| 401         | Unauthorized - Authentication required   |
| 500         | Internal Server Error - Server error     |

---

## Postman Collection Variables

```json
{
  "patient_id": "",
  "patient_phone": "",
  "patient_email": ""
}
```

---

## Complete Postman Workflow

### 1. Create Patient

```
POST /patients
→ Save patient_id from response
```

### 2. Get Patient

```
GET /patients/{{patient_id}}
→ Verify patient details
```

### 3. Update Patient

```
PUT /patients/{{patient_id}}
→ Update patient information
```

### 4. Search by Phone

```
GET /patients/search/phone/{{patient_phone}}
→ Find patient by phone
```

### 5. List All Patients

```
GET /patients?per_page=15&page=1
→ Browse all patients
```

### 6. Delete Patient

```
DELETE /patients/{{patient_id}}
→ Soft delete patient
```

---

## Frontend Integration Example

### React Hook

```javascript
import { useState, useEffect } from "react";
import api from "./api";

export const usePatients = () => {
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({});

  const fetchPatients = async (params = {}) => {
    setLoading(true);
    try {
      const response = await api.get("/patients", { params });
      setPatients(response.data);
      setPagination(response.pagination);
    } catch (error) {
      console.error("Error fetching patients:", error);
    } finally {
      setLoading(false);
    }
  };

  const createPatient = async (patientData) => {
    const response = await api.post("/patients", patientData);
    return response.data;
  };

  const updatePatient = async (id, patientData) => {
    const response = await api.put(`/patients/${id}`, patientData);
    return response.data;
  };

  const deletePatient = async (id) => {
    await api.delete(`/patients/${id}`);
  };

  return {
    patients,
    loading,
    pagination,
    fetchPatients,
    createPatient,
    updatePatient,
    deletePatient,
  };
};
```

---

## Validation Rules

| Field      | Rules                        |
| ---------- | ---------------------------- |
| name       | required, string, max:255    |
| phone      | required, unique, string     |
| sex        | required, in:1,2             |
| age        | nullable, integer, min:0     |
| birth_date | nullable, date, before:today |
| email      | nullable, email, unique      |
| doctor_id  | nullable, exists:users,id    |
| clinics_id | nullable, exists:clinics,id  |

---

## Notes

- Phone numbers must be unique across the system
- Soft deletes are used (deleted patients can be restored)
- All dates use format: `YYYY-MM-DD`
- Sex: 1 = Male, 2 = Female
- All timestamps are in UTC timezone
- Credit balance is stored in the smallest currency unit (cents)

---

**Last Updated:** January 15, 2026  
**API Version:** 1.0
