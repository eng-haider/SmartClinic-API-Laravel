# Bills API Documentation

## Overview

Complete billing system with payment tracking, statistics, and patient billing history.

**Base URL:** `http://localhost:8000/api/bills`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Bills

Get a paginated list of bills with filtering and sorting.

**Endpoint:** `GET /api/bills`

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
| filter[is_paid] | boolean | Filter by payment status | `filter[is_paid]=0` |
| filter[clinics_id] | integer | Filter by clinic ID | `filter[clinics_id]=1` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-created_at` |
| include | string | Load relationships | `include=patient,doctor,billable` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bills retrieved successfully",
  "data": [
    {
      "id": 1,
      "patient_id": 1,
      "billable_id": 1,
      "billable_type": "App\\Models\\Case",
      "is_paid": false,
      "price": 50000,
      "clinics_id": 1,
      "doctor_id": 2,
      "creator_id": 2,
      "updator_id": null,
      "use_credit": false,
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
      "billable": {
        "id": 1,
        "notes": "Root canal treatment",
        "tooth_num": "14"
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
    "total": 200,
    "per_page": 15,
    "current_page": 1,
    "last_page": 14,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

Get all bills:

```bash
curl -X GET http://localhost:8000/api/bills \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get unpaid bills:

```bash
curl -X GET "http://localhost:8000/api/bills?filter[is_paid]=0&sort=-created_at" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/bills`
- Params: Add query parameters as needed
- Authorization: Bearer Token

---

### 2. Get Single Bill

Retrieve details of a specific bill.

**Endpoint:** `GET /api/bills/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Bill ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bill retrieved successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "billable_id": 1,
    "billable_type": "App\\Models\\Case",
    "is_paid": false,
    "price": 50000,
    "use_credit": false,
    "patient": {
      "id": 1,
      "name": "John Doe",
      "phone": "01001234567",
      "credit_balance": 10000
    },
    "billable": {
      "id": 1,
      "notes": "Root canal treatment"
    },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/bills/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/bills/{{bill_id}}`
- Authorization: Bearer Token

---

### 3. Create Bill

Create a new bill for a case or reservation.

**Endpoint:** `POST /api/bills`

**Authentication:** Required

**Request Body:**

```json
{
  "patient_id": 1,
  "billable_id": 1,
  "billable_type": "App\\Models\\Case",
  "price": 50000,
  "is_paid": false,
  "use_credit": false,
  "clinics_id": 1,
  "doctor_id": 2
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| patient_id | integer | Yes | Patient ID (must exist) |
| billable_id | integer | Yes | Related entity ID (Case/Reservation) |
| billable_type | string | Yes | `App\Models\Case` or `App\Models\Reservation` |
| price | integer | Yes | Bill amount (in cents/smallest unit) |
| is_paid | boolean | No | Payment status (default: false) |
| use_credit | boolean | No | Use patient credit balance (default: false) |
| clinics_id | integer | No | Clinic ID |
| doctor_id | integer | No | Doctor ID |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Bill created successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "billable_id": 1,
    "billable_type": "App\\Models\\Case",
    "price": 50000,
    "is_paid": false,
    "creator_id": 2,
    "created_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/bills \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "patient_id": 1,
    "billable_id": 1,
    "billable_type": "App\\\\Models\\\\Case",
    "price": 50000,
    "is_paid": false
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/bills`
- Body (raw JSON): See request body above
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 201) {
  pm.environment.set("bill_id", pm.response.json().data.id);
}
```

---

### 4. Update Bill

Update an existing bill.

**Endpoint:** `PUT /api/bills/{id}`

**Authentication:** Required

**Request Body:**

```json
{
  "price": 55000,
  "is_paid": true,
  "use_credit": false
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bill updated successfully",
  "data": {
    "id": 1,
    "price": 55000,
    "is_paid": true,
    "updator_id": 2,
    ...
  }
}
```

**cURL Example:**

```bash
curl -X PUT http://localhost:8000/api/bills/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "is_paid": true
  }'
```

**Postman:**

- Method: PUT
- URL: `{{base_url}}/bills/{{bill_id}}`
- Body (raw JSON): See request body above
- Authorization: Bearer Token

---

### 5. Delete Bill

Soft delete a bill.

**Endpoint:** `DELETE /api/bills/{id}`

**Authentication:** Required

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bill deleted successfully"
}
```

**cURL Example:**

```bash
curl -X DELETE http://localhost:8000/api/bills/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: DELETE
- URL: `{{base_url}}/bills/{{bill_id}}`
- Authorization: Bearer Token

---

### 6. Mark Bill as Paid

Mark a bill as paid.

**Endpoint:** `PATCH /api/bills/{id}/mark-paid`

**Authentication:** Required

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bill marked as paid successfully",
  "data": {
    "id": 1,
    "is_paid": true,
    "updator_id": 2,
    ...
  }
}
```

**cURL Example:**

```bash
curl -X PATCH http://localhost:8000/api/bills/1/mark-paid \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: PATCH
- URL: `{{base_url}}/bills/{{bill_id}}/mark-paid`
- Authorization: Bearer Token

---

### 7. Mark Bill as Unpaid

Mark a bill as unpaid.

**Endpoint:** `PATCH /api/bills/{id}/mark-unpaid`

**Authentication:** Required

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bill marked as unpaid successfully",
  "data": {
    "id": 1,
    "is_paid": false,
    "updator_id": 2,
    ...
  }
}
```

**cURL Example:**

```bash
curl -X PATCH http://localhost:8000/api/bills/1/mark-unpaid \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: PATCH
- URL: `{{base_url}}/bills/{{bill_id}}/mark-unpaid`
- Authorization: Bearer Token

---

### 8. Get Bills by Patient

Get all bills for a specific patient.

**Endpoint:** `GET /api/bills/patient/{patientId}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| patientId | integer | Patient ID |

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| per_page | integer | Items per page |
| filter[is_paid] | boolean | Filter by payment status |
| sort | string | Sort order |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bills retrieved successfully",
  "data": [
    {
      "id": 1,
      "patient_id": 1,
      "price": 50000,
      "is_paid": false,
      "billable": { ... },
      "created_at": "2026-01-15T10:00:00.000000Z"
    }
  ],
  "pagination": { ... }
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/bills/patient/1?filter[is_paid]=0" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/bills/patient/{{patient_id}}`
- Params: Add filters as needed
- Authorization: Bearer Token

---

### 9. Get Bill Statistics

Get billing statistics and revenue summary.

**Endpoint:** `GET /api/bills/statistics/summary`

**Authentication:** Required

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| start_date | date | Start date (YYYY-MM-DD) |
| end_date | date | End date (YYYY-MM-DD) |
| clinic_id | integer | Filter by clinic |
| doctor_id | integer | Filter by doctor |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Statistics retrieved successfully",
  "data": {
    "total_revenue": 500000,
    "total_unpaid": 150000,
    "total_expected": 650000,
    "payment_rate": 76.92,
    "total_bills": 25,
    "paid_bills": 18,
    "unpaid_bills": 7,
    "average_bill_amount": 26000,
    "period": {
      "start_date": "2026-01-01",
      "end_date": "2026-01-31"
    }
  }
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/bills/statistics/summary?start_date=2026-01-01&end_date=2026-01-31" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/bills/statistics/summary`
- Params:
  - start_date: 2026-01-01
  - end_date: 2026-01-31
- Authorization: Bearer Token

---

### 10. Get Bill Reports with Filters

Get comprehensive bill statistics with optional filtering by date range and doctor.

**Endpoint:** `GET /api/reports/bills`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Type    | Required | Description               | Example                |
| --------- | ------- | -------- | ------------------------- | ---------------------- |
| date_from | date    | No       | Start date for filtering  | `date_from=2026-01-01` |
| date_to   | date    | No       | End date for filtering    | `date_to=2026-01-31`   |
| doctor_id | integer | No       | Filter by specific doctor | `doctor_id=2`          |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Bill report retrieved successfully",
  "data": {
    "total_bills": 45,
    "paid_bills": 30,
    "unpaid_bills": 15,
    "total_paid_price": 1500000,
    "total_unpaid_price": 450000,
    "total_revenue": 1500000,
    "total_outstanding": 450000
  }
}
```

**Response Fields:**

| Field              | Type    | Description                                    |
| ------------------ | ------- | ---------------------------------------------- |
| total_bills        | integer | Total number of bills in the filtered period   |
| paid_bills         | integer | Number of bills marked as paid                 |
| unpaid_bills       | integer | Number of bills that are unpaid                |
| total_paid_price   | integer | Sum of all paid bill amounts (in cents)        |
| total_unpaid_price | integer | Sum of all unpaid bill amounts (in cents)      |
| total_revenue      | integer | Alias for total_paid_price (backward compat)   |
| total_outstanding  | integer | Alias for total_unpaid_price (backward compat) |

**cURL Examples:**

```bash
# Get all-time report
curl -X GET "http://localhost:8000/api/reports/bills" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get report for specific date range
curl -X GET "http://localhost:8000/api/reports/bills?date_from=2026-01-01&date_to=2026-01-31" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get report for specific doctor in date range
curl -X GET "http://localhost:8000/api/reports/bills?date_from=2026-01-01&date_to=2026-01-31&doctor_id=2" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get report for last 7 days
curl -X GET "http://localhost:8000/api/reports/bills?date_from=2026-01-15&date_to=2026-01-22" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/reports/bills`
- Params (optional):
  - date_from: 2026-01-01
  - date_to: 2026-01-31
  - doctor_id: 2
- Authorization: Bearer Token

**Use Cases:**

1. **Monthly Financial Report**

   ```
   GET /reports/bills?date_from=2026-01-01&date_to=2026-01-31
   ```

   Get complete billing summary for January 2026

2. **Doctor Performance Report**

   ```
   GET /reports/bills?doctor_id=5&date_from=2026-01-01&date_to=2026-01-31
   ```

   Track specific doctor's billing metrics

3. **Weekly Collection Report**

   ```
   GET /reports/bills?date_from=2026-01-15&date_to=2026-01-22
   ```

   Monitor weekly revenue and outstanding amounts

4. **Year-to-Date Overview**
   ```
   GET /reports/bills?date_from=2026-01-01
   ```
   Get cumulative statistics from start of year

**Validation Error Response (422):**

```json
{
  "message": "The date from field must be a valid date.",
  "errors": {
    "date_from": ["The date from field must be a valid date."]
  }
}
```

**Notes:**

- Date format: YYYY-MM-DD or full datetime (YYYY-MM-DD HH:MM:SS)
- Dates are filtered on the `created_at` field of bills
- Non-admin users only see bills from their assigned clinic
- Super admin users see all bills across all clinics
- All price amounts are in the smallest currency unit (cents)
- Filter combinations are supported (date range + doctor)

**Frontend Integration Example:**

```javascript
// React/JavaScript
const billReports = {
  async getReport(filters = {}) {
    const params = new URLSearchParams();
    if (filters.date_from) params.append("date_from", filters.date_from);
    if (filters.date_to) params.append("date_to", filters.date_to);
    if (filters.doctor_id) params.append("doctor_id", filters.doctor_id);

    return await api.get(`/reports/bills?${params.toString()}`);
  },
};

// Usage examples
const monthlyReport = await billReports.getReport({
  date_from: "2026-01-01",
  date_to: "2026-01-31",
});

const doctorReport = await billReports.getReport({
  date_from: "2026-01-01",
  date_to: "2026-01-31",
  doctor_id: 2,
});

console.log("Total Revenue:", monthlyReport.data.total_revenue);
console.log("Outstanding:", monthlyReport.data.total_outstanding);
console.log(
  "Collection Rate:",
  (
    (monthlyReport.data.paid_bills / monthlyReport.data.total_bills) *
    100
  ).toFixed(2) + "%"
);
```

---

## Advanced Filtering Examples

### Get Unpaid Bills for Clinic

```bash
GET /bills?filter[clinics_id]=1&filter[is_paid]=0&sort=-created_at
```

### Get Patient Billing History

```bash
GET /bills/patient/1?sort=-created_at&include=billable,doctor
```

### Get Revenue by Date Range

```bash
GET /bills/statistics/summary?start_date=2026-01-01&end_date=2026-01-31&clinic_id=1
```

---

## Frontend Integration Example

### React Service

```javascript
import api from "./api";

export const billService = {
  async getAll(params = {}) {
    return await api.get("/bills", { params });
  },

  async getById(id) {
    return await api.get(`/bills/${id}`);
  },

  async create(billData) {
    return await api.post("/bills", billData);
  },

  async update(id, billData) {
    return await api.put(`/bills/${id}`, billData);
  },

  async delete(id) {
    return await api.delete(`/bills/${id}`);
  },

  async markAsPaid(id) {
    return await api.patch(`/bills/${id}/mark-paid`);
  },

  async markAsUnpaid(id) {
    return await api.patch(`/bills/${id}/mark-unpaid`);
  },

  async getByPatient(patientId, params = {}) {
    return await api.get(`/bills/patient/${patientId}`, { params });
  },

  async getStatistics(params = {}) {
    return await api.get("/bills/statistics/summary", { params });
  },
};
```

### Vue Component - Bill Management

```vue
<template>
  <div>
    <h2>Bills Management</h2>

    <!-- Statistics -->
    <div class="statistics">
      <div class="stat-card">
        <h3>Total Revenue</h3>
        <p>{{ formatCurrency(statistics.total_revenue) }}</p>
      </div>
      <div class="stat-card">
        <h3>Unpaid</h3>
        <p>{{ formatCurrency(statistics.total_unpaid) }}</p>
      </div>
      <div class="stat-card">
        <h3>Payment Rate</h3>
        <p>{{ statistics.payment_rate }}%</p>
      </div>
    </div>

    <!-- Bills List -->
    <table>
      <tr v-for="bill in bills" :key="bill.id">
        <td>{{ bill.id }}</td>
        <td>{{ bill.patient.name }}</td>
        <td>{{ formatCurrency(bill.price) }}</td>
        <td>{{ bill.is_paid ? "Paid" : "Unpaid" }}</td>
        <td>
          <button @click="markAsPaid(bill.id)" v-if="!bill.is_paid">
            Mark Paid
          </button>
        </td>
      </tr>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { billService } from "../services/billService";

const bills = ref([]);
const statistics = ref({});

const fetchBills = async () => {
  const response = await billService.getAll({
    include: "patient,doctor",
    sort: "-created_at",
  });
  bills.value = response.data;
};

const fetchStatistics = async () => {
  const response = await billService.getStatistics();
  statistics.value = response.data;
};

const markAsPaid = async (id) => {
  await billService.markAsPaid(id);
  await fetchBills();
  await fetchStatistics();
};

const formatCurrency = (amount) => {
  return (amount / 100).toFixed(2);
};

onMounted(() => {
  fetchBills();
  fetchStatistics();
});
</script>
```

---

## Validation Rules

| Field         | Rules                                               |
| ------------- | --------------------------------------------------- |
| patient_id    | required, exists:patients,id                        |
| billable_id   | required, integer                                   |
| billable_type | required, in:App\Models\Case,App\Models\Reservation |
| price         | required, integer, min:0                            |
| is_paid       | nullable, boolean                                   |
| use_credit    | nullable, boolean                                   |
| clinics_id    | nullable, exists:clinics,id                         |
| doctor_id     | nullable, exists:users,id                           |

---

## Business Logic

### Automatic User Tracking

- `creator_id` is automatically set when creating a bill
- `updator_id` is automatically set when updating a bill

### Polymorphic Relationship

Bills can be attached to:

- Cases (`App\Models\Case`)
- Reservations (`App\Models\Reservation`)

### Credit Balance

- Set `use_credit: true` to deduct from patient's credit balance
- System automatically handles credit balance calculations

### Payment Status

- `is_paid: false` - Unpaid (default)
- `is_paid: true` - Paid
- Use mark-paid/mark-unpaid endpoints for status updates

---

## Common Use Cases

### 1. Create Bill for Case

```javascript
const bill = await billService.create({
  patient_id: 1,
  billable_id: caseId,
  billable_type: "App\\Models\\Case",
  price: 50000,
  is_paid: false,
});
```

### 2. Process Payment

```javascript
// Mark bill as paid
await billService.markAsPaid(billId);

// Or update with credit
await billService.update(billId, {
  is_paid: true,
  use_credit: true,
});
```

### 3. Get Patient Outstanding Balance

```javascript
const unpaidBills = await billService.getByPatient(patientId, {
  "filter[is_paid]": 0,
});

const totalOutstanding = unpaidBills.data.reduce(
  (sum, bill) => sum + bill.price,
  0
);
```

### 4. Monthly Revenue Report

```javascript
const stats = await billService.getStatistics({
  start_date: "2026-01-01",
  end_date: "2026-01-31",
  clinic_id: 1,
});

console.log("Revenue:", stats.data.total_revenue);
console.log("Payment Rate:", stats.data.payment_rate);
```

---

## Notes

- All prices are stored in the smallest currency unit (cents)
- Soft deletes are used - deleted bills can be restored
- Bills support polymorphic relationships (Case or Reservation)
- Creator and updator are automatically tracked
- Use credit balance feature for patient credits
- Statistics endpoint provides comprehensive revenue analytics

---

**Last Updated:** January 22, 2026  
**API Version:** 1.0
