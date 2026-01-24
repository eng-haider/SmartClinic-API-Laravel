# Reports & Analytics API Documentation

## Overview

The Reports & Analytics API provides comprehensive dashboard and statistics endpoints for the SmartClinic system. All endpoints follow the existing API patterns and return JSON responses with role-based data visibility.

## Authentication

All endpoints require JWT authentication. Include the Bearer token in the Authorization header:

```
Authorization: Bearer <your_jwt_token>
```

## Role-Based Data Visibility

| Role                  | Data Access          |
| --------------------- | -------------------- |
| `super_admin`         | All clinics data     |
| `clinic_super_doctor` | Own clinic data only |
| `doctor`              | Own clinic data only |
| `secretary`           | Own clinic data only |

## Common Query Parameters

Most report endpoints support these optional query parameters:

| Parameter   | Type           | Description                                                        |
| ----------- | -------------- | ------------------------------------------------------------------ |
| `date_from` | string (Y-m-d) | Start date for filtering                                           |
| `date_to`   | string (Y-m-d) | End date for filtering                                             |
| `period`    | string         | Grouping period: `day`, `week`, `month`, `year` (default: `month`) |

---

## 1. Dashboard Reports

### 1.1 Dashboard Overview

Get aggregated statistics for all main entities.

**Endpoint:** `GET /api/reports/dashboard/overview`

**Query Parameters:**

- `date_from` (optional): Start date
- `date_to` (optional): End date

**Response:**

```json
{
  "success": true,
  "message": "Dashboard overview retrieved successfully",
  "data": {
    "patients": {
      "total": 1250,
      "male": 680,
      "female": 570,
      "male_percentage": 54.4,
      "female_percentage": 45.6
    },
    "bills": {
      "total_bills": 3420,
      "paid_bills": 2890,
      "unpaid_bills": 530,
      "total_revenue": 285000,
      "total_outstanding": 45000,
      "collection_rate": 84.5
    },
    "reservations": {
      "total": 4500,
      "waiting": 120,
      "confirmed": 4380,
      "waiting_percentage": 2.67
    },
    "cases": {
      "total": 2800,
      "paid": 2400,
      "unpaid": 400,
      "total_value": 350000,
      "paid_percentage": 85.71
    },
    "expenses": {
      "total_expenses": 450,
      "paid_expenses": 400,
      "unpaid_expenses": 50,
      "total_amount": 85000.0,
      "paid_amount": 75000.0,
      "unpaid_amount": 10000.0
    }
  },
  "filters": {
    "clinic_id": 1,
    "date_from": "2026-01-01",
    "date_to": "2026-01-24"
  }
}
```

**Suggested Chart:** Dashboard cards with KPIs

---

### 1.2 Today's Summary

Get quick statistics for the current day.

**Endpoint:** `GET /api/reports/dashboard/today`

**Response:**

```json
{
  "success": true,
  "message": "Today's summary retrieved successfully",
  "data": {
    "new_patients": 12,
    "reservations_today": 45,
    "revenue_today": 15000,
    "cases_today": 28,
    "expenses_today": 2500.0
  },
  "filters": {
    "clinic_id": 1,
    "date": "2026-01-24"
  }
}
```

**Suggested Chart:** Dashboard stat cards

---

## 2. Patient Reports

### 2.1 Patient Summary

Get patient count and gender distribution.

**Endpoint:** `GET /api/reports/patients/summary`

**Query Parameters:**

- `date_from` (optional): Filter by patient creation date
- `date_to` (optional): Filter by patient creation date

**Response:**

```json
{
  "success": true,
  "message": "Patient summary retrieved successfully",
  "data": {
    "total": 1250,
    "male": 680,
    "female": 570,
    "male_percentage": 54.4,
    "female_percentage": 45.6
  },
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  }
}
```

**Suggested Chart:** Stat cards with gender pie chart

---

### 2.2 Patients by Referral Source

Get patient counts grouped by how they found the clinic.

**Endpoint:** `GET /api/reports/patients/by-source`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Patients by source retrieved successfully",
  "data": [
    {
      "source_id": 1,
      "source_name": "Google",
      "source_name_ar": "جوجل",
      "count": 450
    },
    {
      "source_id": 2,
      "source_name": "Referral",
      "source_name_ar": "إحالة",
      "count": 380
    },
    {
      "source_id": 3,
      "source_name": "Social Media",
      "source_name_ar": "وسائل التواصل",
      "count": 220
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "pie"
}
```

**Suggested Chart:** Pie chart or donut chart

---

### 2.3 Patients by Doctor

Get patient counts grouped by assigned doctor.

**Endpoint:** `GET /api/reports/patients/by-doctor`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Patients by doctor retrieved successfully",
  "data": [
    {
      "doctor_id": 1,
      "doctor_name": "Dr. Ahmed Hassan",
      "count": 320
    },
    {
      "doctor_id": 2,
      "doctor_name": "Dr. Sara Ali",
      "count": 280
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "bar"
}
```

**Suggested Chart:** Horizontal bar chart

---

### 2.4 Patient Registration Trend

Get patient registration counts over time.

**Endpoint:** `GET /api/reports/patients/trend`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `period` (optional): `day`, `week`, `month`, `year`

**Response:**

```json
{
  "success": true,
  "message": "Patient trend retrieved successfully",
  "data": [
    { "period": "2025-10", "count": 85 },
    { "period": "2025-11", "count": 92 },
    { "period": "2025-12", "count": 110 },
    { "period": "2026-01", "count": 78 }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "period": "month"
  },
  "chart_type": "line"
}
```

**Suggested Chart:** Line chart or area chart

---

### 2.5 Patient Age Distribution

Get patient counts grouped by age ranges.

**Endpoint:** `GET /api/reports/patients/age-distribution`

**Response:**

```json
{
  "success": true,
  "message": "Patient age distribution retrieved successfully",
  "data": [
    { "age_group": "0-17", "count": 150 },
    { "age_group": "18-30", "count": 380 },
    { "age_group": "31-45", "count": 420 },
    { "age_group": "46-60", "count": 220 },
    { "age_group": "60+", "count": 80 }
  ],
  "filters": {
    "clinic_id": 1
  },
  "chart_type": "bar"
}
```

**Suggested Chart:** Bar chart or histogram

---

## 3. Case Reports

### 3.1 Cases Summary

Get case counts and payment status.

**Endpoint:** `GET /api/reports/cases/summary`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Cases summary retrieved successfully",
  "data": {
    "total": 2800,
    "paid": 2400,
    "unpaid": 400,
    "total_value": 350000,
    "paid_percentage": 85.71
  },
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  }
}
```

**Suggested Chart:** Stat cards with progress indicator

---

### 3.2 Cases by Category

Get case counts grouped by treatment category.

**Endpoint:** `GET /api/reports/cases/by-category`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Cases by category retrieved successfully",
  "data": [
    {
      "category_id": 1,
      "category_name": "Root Canal",
      "count": 450,
      "total_value": 67500
    },
    {
      "category_id": 2,
      "category_name": "Extraction",
      "count": 380,
      "total_value": 28500
    },
    {
      "category_id": 3,
      "category_name": "Cleaning",
      "count": 620,
      "total_value": 31000
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "pie"
}
```

**Suggested Chart:** Pie chart or treemap

---

### 3.3 Cases by Status

Get case counts grouped by status.

**Endpoint:** `GET /api/reports/cases/by-status`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Cases by status retrieved successfully",
  "data": [
    {
      "status_id": 1,
      "status_name": "Completed",
      "status_name_ar": "مكتمل",
      "color": "#28a745",
      "count": 2200
    },
    {
      "status_id": 2,
      "status_name": "In Progress",
      "status_name_ar": "قيد التنفيذ",
      "color": "#ffc107",
      "count": 450
    },
    {
      "status_id": 3,
      "status_name": "Pending",
      "status_name_ar": "قيد الانتظار",
      "color": "#dc3545",
      "count": 150
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "donut"
}
```

**Suggested Chart:** Donut chart with status colors

---

### 3.4 Cases by Doctor

Get case counts and values grouped by doctor.

**Endpoint:** `GET /api/reports/cases/by-doctor`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Cases by doctor retrieved successfully",
  "data": [
    {
      "doctor_id": 1,
      "doctor_name": "Dr. Ahmed Hassan",
      "count": 850,
      "total_value": 127500
    },
    {
      "doctor_id": 2,
      "doctor_name": "Dr. Sara Ali",
      "count": 720,
      "total_value": 108000
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "bar"
}
```

**Suggested Chart:** Grouped bar chart (count + value)

---

### 3.5 Cases Trend

Get case counts over time.

**Endpoint:** `GET /api/reports/cases/trend`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `period` (optional): `day`, `week`, `month`, `year`

**Response:**

```json
{
  "success": true,
  "message": "Cases trend retrieved successfully",
  "data": [
    { "period": "2025-10", "count": 220 },
    { "period": "2025-11", "count": 245 },
    { "period": "2025-12", "count": 280 },
    { "period": "2026-01", "count": 195 }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "period": "month"
  },
  "chart_type": "line"
}
```

**Suggested Chart:** Line chart

---

## 4. Reservation Reports

### 4.1 Reservations Summary

Get reservation counts and waiting status.

**Endpoint:** `GET /api/reports/reservations/summary`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Reservations summary retrieved successfully",
  "data": {
    "total": 4500,
    "waiting": 120,
    "confirmed": 4380,
    "waiting_percentage": 2.67
  },
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  }
}
```

**Suggested Chart:** Stat cards

---

### 4.2 Reservations by Status

Get reservation counts grouped by status.

**Endpoint:** `GET /api/reports/reservations/by-status`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Reservations by status retrieved successfully",
  "data": [
    {
      "status_id": 1,
      "status_name": "Confirmed",
      "status_name_ar": "مؤكد",
      "color": "#28a745",
      "count": 3200
    },
    {
      "status_id": 2,
      "status_name": "Completed",
      "status_name_ar": "مكتمل",
      "color": "#007bff",
      "count": 1100
    },
    {
      "status_id": 3,
      "status_name": "Cancelled",
      "status_name_ar": "ملغي",
      "color": "#dc3545",
      "count": 200
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "donut"
}
```

**Suggested Chart:** Donut chart with status colors

---

### 4.3 Reservations by Doctor

Get reservation counts grouped by doctor.

**Endpoint:** `GET /api/reports/reservations/by-doctor`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Reservations by doctor retrieved successfully",
  "data": [
    {
      "doctor_id": 1,
      "doctor_name": "Dr. Ahmed Hassan",
      "count": 1800
    },
    {
      "doctor_id": 2,
      "doctor_name": "Dr. Sara Ali",
      "count": 1450
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "bar"
}
```

**Suggested Chart:** Horizontal bar chart

---

### 4.4 Reservations Trend

Get reservation counts over time.

**Endpoint:** `GET /api/reports/reservations/trend`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `period` (optional): `day`, `week`, `month`, `year`

**Response:**

```json
{
  "success": true,
  "message": "Reservations trend retrieved successfully",
  "data": [
    { "period": "2025-10", "count": 380 },
    { "period": "2025-11", "count": 420 },
    { "period": "2025-12", "count": 450 },
    { "period": "2026-01", "count": 310 }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "period": "month"
  },
  "chart_type": "line"
}
```

**Suggested Chart:** Line chart

---

## 5. Financial Reports

### 5.1 Bills Summary

Get bill counts and revenue statistics.

**Endpoint:** `GET /api/reports/financial/bills/summary`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Bills summary retrieved successfully",
  "data": {
    "total_bills": 3420,
    "paid_bills": 2890,
    "unpaid_bills": 530,
    "total_revenue": 285000,
    "total_outstanding": 45000,
    "collection_rate": 84.5
  },
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  }
}
```

**Suggested Chart:** KPI cards with collection rate gauge

---

### 5.2 Revenue by Doctor

Get revenue amounts grouped by doctor.

**Endpoint:** `GET /api/reports/financial/revenue/by-doctor`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Revenue by doctor retrieved successfully",
  "data": [
    {
      "doctor_id": 1,
      "doctor_name": "Dr. Ahmed Hassan",
      "total_revenue": 125000,
      "bills_count": 420
    },
    {
      "doctor_id": 2,
      "doctor_name": "Dr. Sara Ali",
      "total_revenue": 98000,
      "bills_count": 340
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "bar"
}
```

**Suggested Chart:** Bar chart

---

### 5.3 Revenue Trend

Get revenue amounts over time.

**Endpoint:** `GET /api/reports/financial/revenue/trend`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `period` (optional): `day`, `week`, `month`, `year`

**Response:**

```json
{
  "success": true,
  "message": "Revenue trend retrieved successfully",
  "data": [
    { "period": "2025-10", "total": 68000 },
    { "period": "2025-11", "total": 72000 },
    { "period": "2025-12", "total": 85000 },
    { "period": "2026-01", "total": 60000 }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "period": "month"
  },
  "chart_type": "area"
}
```

**Suggested Chart:** Area chart

---

### 5.4 Bills by Payment Status

Get bill distribution by payment status.

**Endpoint:** `GET /api/reports/financial/bills/by-payment-status`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Bills by payment status retrieved successfully",
  "data": [
    { "status": "paid", "count": 2890, "percentage": 84.5 },
    { "status": "unpaid", "count": 530, "percentage": 15.5 }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "pie"
}
```

**Suggested Chart:** Pie chart

---

### 5.5 Expenses Summary

Get expense counts and totals.

**Endpoint:** `GET /api/reports/financial/expenses/summary`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Expenses summary retrieved successfully",
  "data": {
    "total_expenses": 450,
    "paid_expenses": 400,
    "unpaid_expenses": 50,
    "total_amount": 85000.0,
    "paid_amount": 75000.0,
    "unpaid_amount": 10000.0
  },
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  }
}
```

**Suggested Chart:** Stat cards

---

### 5.6 Expenses by Category

Get expense amounts grouped by category.

**Endpoint:** `GET /api/reports/financial/expenses/by-category`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Expenses by category retrieved successfully",
  "data": [
    {
      "category_id": 1,
      "category_name": "Supplies",
      "count": 180,
      "total_amount": 35000.0
    },
    {
      "category_id": 2,
      "category_name": "Utilities",
      "count": 48,
      "total_amount": 12000.0
    },
    {
      "category_id": 3,
      "category_name": "Maintenance",
      "count": 25,
      "total_amount": 8500.0
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  },
  "chart_type": "pie"
}
```

**Suggested Chart:** Pie chart or treemap

---

### 5.7 Expenses Trend

Get expense amounts over time.

**Endpoint:** `GET /api/reports/financial/expenses/trend`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `period` (optional): `day`, `week`, `month`, `year`

**Response:**

```json
{
  "success": true,
  "message": "Expenses trend retrieved successfully",
  "data": [
    { "period": "2025-10", "total": 18500.0 },
    { "period": "2025-11", "total": 21000.0 },
    { "period": "2025-12", "total": 25000.0 },
    { "period": "2026-01", "total": 20500.0 }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "period": "month"
  },
  "chart_type": "area"
}
```

**Suggested Chart:** Area chart

---

### 5.8 Profit/Loss Report

Get revenue vs expenses with profit/loss calculation.

**Endpoint:** `GET /api/reports/financial/profit-loss`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)

**Response:**

```json
{
  "success": true,
  "message": "Profit/Loss report retrieved successfully",
  "data": {
    "total_revenue": 285000,
    "total_expenses": 85000.0,
    "profit_loss": 200000.0,
    "profit_margin": 70.18,
    "is_profit": true
  },
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null
  }
}
```

**Suggested Chart:** KPI cards with profit indicator

---

### 5.9 Profit/Loss Trend

Get profit/loss breakdown over time.

**Endpoint:** `GET /api/reports/financial/profit-loss/trend`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `period` (optional): `day`, `week`, `month`, `year`

**Response:**

```json
{
  "success": true,
  "message": "Profit/Loss trend retrieved successfully",
  "data": [
    {
      "period": "2025-10",
      "revenue": 68000,
      "expenses": 18500.0,
      "profit_loss": 49500.0
    },
    {
      "period": "2025-11",
      "revenue": 72000,
      "expenses": 21000.0,
      "profit_loss": 51000.0
    },
    {
      "period": "2025-12",
      "revenue": 85000,
      "expenses": 25000.0,
      "profit_loss": 60000.0
    },
    {
      "period": "2026-01",
      "revenue": 60000,
      "expenses": 20500.0,
      "profit_loss": 39500.0
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "period": "month"
  },
  "chart_type": "combo"
}
```

**Suggested Chart:** Combo chart (bars for revenue/expenses, line for profit)

---

### 5.10 Doctor Performance

Get comprehensive performance metrics for doctors.

**Endpoint:** `GET /api/reports/financial/doctor-performance`

**Query Parameters:**

- `date_from` (optional)
- `date_to` (optional)
- `doctor_id` (optional): Filter for specific doctor

**Response:**

```json
{
  "success": true,
  "message": "Doctor performance retrieved successfully",
  "data": [
    {
      "doctor_id": 1,
      "doctor_name": "Dr. Ahmed Hassan",
      "doctor_email": "ahmed@clinic.com",
      "total_patients": 320,
      "total_cases": 850,
      "total_reservations": 1800,
      "total_revenue": 125000
    },
    {
      "doctor_id": 2,
      "doctor_name": "Dr. Sara Ali",
      "doctor_email": "sara@clinic.com",
      "total_patients": 280,
      "total_cases": 720,
      "total_reservations": 1450,
      "total_revenue": 98000
    }
  ],
  "filters": {
    "clinic_id": 1,
    "date_from": null,
    "date_to": null,
    "doctor_id": null
  },
  "chart_type": "table"
}
```

**Suggested Chart:** Data table with sortable columns

---

## 6. Legacy Endpoints

### 6.1 Bill Report (Legacy)

**Endpoint:** `GET /api/reports/bills`

**Note:** This endpoint is kept for backward compatibility. Consider using `/api/reports/financial/bills/summary` instead.

---

## Error Responses

All endpoints return standard error responses:

### Validation Error (422)

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "date_from": ["The date from must be a valid date."],
    "date_to": ["The date to must be after or equal to date from."]
  }
}
```

### Unauthorized (401)

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Forbidden (403)

```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

---

## Frontend Integration Guide

### Chart Libraries Recommendations

| Chart Type | Recommended Libraries                     |
| ---------- | ----------------------------------------- |
| Line/Area  | Chart.js, ApexCharts, Recharts            |
| Bar        | Chart.js, ApexCharts, Recharts            |
| Pie/Donut  | Chart.js, ApexCharts, Recharts            |
| Combo      | ApexCharts, Highcharts                    |
| Table      | AG Grid, TanStack Table, Ant Design Table |
| KPI Cards  | Custom components                         |

### Date Range Picker

For date filtering, use a date range picker component:

- React: `react-datepicker`, `@mui/x-date-pickers`
- Vue: `vue-datepicker-next`, `vuetify date picker`
- Angular: `ngx-daterangepicker-material`

### Period Selector

Create a dropdown/button group for period selection:

```javascript
const periods = [
  { value: "day", label: "Daily" },
  { value: "week", label: "Weekly" },
  { value: "month", label: "Monthly" },
  { value: "year", label: "Yearly" },
];
```

### Example API Call (JavaScript)

```javascript
const fetchDashboardOverview = async (dateFrom, dateTo) => {
  const params = new URLSearchParams();
  if (dateFrom) params.append("date_from", dateFrom);
  if (dateTo) params.append("date_to", dateTo);

  const response = await fetch(`/api/reports/dashboard/overview?${params}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
    },
  });

  return response.json();
};
```

---

## Files Created

| File                                                          | Description                             |
| ------------------------------------------------------------- | --------------------------------------- |
| `app/Repositories/Reports/ReportsRepository.php`              | Main repository with all report queries |
| `app/Http/Controllers/Report/DashboardReportController.php`   | Dashboard overview endpoints            |
| `app/Http/Controllers/Report/PatientReportController.php`     | Patient statistics endpoints            |
| `app/Http/Controllers/Report/CaseReportController.php`        | Case statistics endpoints               |
| `app/Http/Controllers/Report/ReservationReportController.php` | Reservation statistics endpoints        |
| `app/Http/Controllers/Report/FinancialReportController.php`   | Financial reports endpoints             |

---

## Routes Summary

| Method | Endpoint                                         | Description                |
| ------ | ------------------------------------------------ | -------------------------- |
| GET    | `/api/reports/dashboard/overview`                | Dashboard overview         |
| GET    | `/api/reports/dashboard/today`                   | Today's summary            |
| GET    | `/api/reports/patients/summary`                  | Patient counts             |
| GET    | `/api/reports/patients/by-source`                | Patients by referral       |
| GET    | `/api/reports/patients/by-doctor`                | Patients by doctor         |
| GET    | `/api/reports/patients/trend`                    | Patient registration trend |
| GET    | `/api/reports/patients/age-distribution`         | Patient age groups         |
| GET    | `/api/reports/cases/summary`                     | Case counts                |
| GET    | `/api/reports/cases/by-category`                 | Cases by category          |
| GET    | `/api/reports/cases/by-status`                   | Cases by status            |
| GET    | `/api/reports/cases/by-doctor`                   | Cases by doctor            |
| GET    | `/api/reports/cases/trend`                       | Case trend                 |
| GET    | `/api/reports/reservations/summary`              | Reservation counts         |
| GET    | `/api/reports/reservations/by-status`            | Reservations by status     |
| GET    | `/api/reports/reservations/by-doctor`            | Reservations by doctor     |
| GET    | `/api/reports/reservations/trend`                | Reservation trend          |
| GET    | `/api/reports/financial/bills/summary`           | Bills summary              |
| GET    | `/api/reports/financial/revenue/by-doctor`       | Revenue by doctor          |
| GET    | `/api/reports/financial/revenue/trend`           | Revenue trend              |
| GET    | `/api/reports/financial/bills/by-payment-status` | Bills payment status       |
| GET    | `/api/reports/financial/expenses/summary`        | Expenses summary           |
| GET    | `/api/reports/financial/expenses/by-category`    | Expenses by category       |
| GET    | `/api/reports/financial/expenses/trend`          | Expenses trend             |
| GET    | `/api/reports/financial/profit-loss`             | Profit/Loss report         |
| GET    | `/api/reports/financial/profit-loss/trend`       | Profit/Loss trend          |
| GET    | `/api/reports/financial/doctor-performance`      | Doctor performance         |
