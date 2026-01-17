# Reservations API Documentation

## Overview

Complete appointment scheduling system with status tracking and waiting list management.

**Base URL:** `http://localhost:8000/api/reservations`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Reservations

Get a paginated list of reservations with filtering and sorting.

**Endpoint:** `GET /api/reservations`

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
| filter[clinics_id] | integer | Filter by clinic ID | `filter[clinics_id]=1` |
| filter[status_id] | integer | Filter by status ID | `filter[status_id]=1` |
| filter[is_waiting] | boolean | Filter by waiting status | `filter[is_waiting]=1` |
| filter[reservation_start_date] | date | Filter by date | `filter[reservation_start_date]=2026-01-20` |
| sort | string | Sort field | `sort=-reservation_start_date` |
| include | string | Load relationships | `include=patient,doctor,clinic,status` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Reservations retrieved successfully",
  "data": [
    {
      "id": 1,
      "patient_id": 1,
      "doctor_id": 2,
      "clinics_id": 1,
      "status_id": 1,
      "notes": "Regular checkup appointment",
      "reservation_start_date": "2026-01-20",
      "reservation_end_date": "2026-01-20",
      "reservation_from_time": "09:00:00",
      "reservation_to_time": "10:00:00",
      "is_waiting": false,
      "creator_id": 2,
      "updator_id": null,
      "patient": {
        "id": 1,
        "name": "John Doe",
        "phone": "01001234567"
      },
      "doctor": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "clinic": {
        "id": 1,
        "name": "Main Clinic"
      },
      "status": {
        "id": 1,
        "name_ar": "محجوز",
        "name_en": "Confirmed",
        "color": "#10B981"
      },
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z",
      "deleted_at": null
    }
  ],
  "pagination": {
    "total": 80,
    "per_page": 15,
    "current_page": 1,
    "last_page": 6,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

Get all reservations:

```bash
curl -X GET http://localhost:8000/api/reservations \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get today's reservations:

```bash
curl -X GET "http://localhost:8000/api/reservations?filter[reservation_start_date]=2026-01-15&sort=reservation_from_time" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get waiting list:

```bash
curl -X GET "http://localhost:8000/api/reservations?filter[is_waiting]=1&sort=-created_at" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/reservations`
- Params: Add query parameters as needed
- Authorization: Bearer Token

---

### 2. Get Single Reservation

Retrieve details of a specific reservation.

**Endpoint:** `GET /api/reservations/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Reservation ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Reservation retrieved successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "doctor_id": 2,
    "clinics_id": 1,
    "status_id": 1,
    "notes": "Regular checkup appointment",
    "reservation_start_date": "2026-01-20",
    "reservation_end_date": "2026-01-20",
    "reservation_from_time": "09:00:00",
    "reservation_to_time": "10:00:00",
    "is_waiting": false,
    "patient": { ... },
    "doctor": { ... },
    "clinic": { ... },
    "status": { ... },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/reservations/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/reservations/{{reservation_id}}`
- Authorization: Bearer Token

---

### 3. Create Reservation

Create a new appointment reservation.

**Endpoint:** `POST /api/reservations`

**Authentication:** Required

**Request Body:**

```json
{
  "patient_id": 1,
  "doctor_id": 2,
  "clinics_id": 1,
  "status_id": 1,
  "notes": "Regular checkup appointment",
  "reservation_start_date": "2026-01-20",
  "reservation_end_date": "2026-01-20",
  "reservation_from_time": "09:00",
  "reservation_to_time": "10:00",
  "is_waiting": false
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| patient_id | integer | Yes | Patient ID (must exist) |
| doctor_id | integer | Yes | Doctor/User ID (must exist) |
| clinics_id | integer | Yes | Clinic ID (must exist) |
| status_id | integer | Yes | Status ID (must exist) |
| reservation_start_date | date | Yes | Start date (YYYY-MM-DD) |
| reservation_end_date | date | Yes | End date (YYYY-MM-DD) |
| reservation_from_time | time | Yes | Start time (HH:mm) |
| reservation_to_time | time | Yes | End time (HH:mm) |
| notes | string | No | Appointment notes |
| is_waiting | boolean | No | Waiting list status (default: false) |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Reservation created successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "doctor_id": 2,
    "clinics_id": 1,
    "status_id": 1,
    "reservation_start_date": "2026-01-20",
    "reservation_from_time": "09:00:00",
    "reservation_to_time": "10:00:00",
    "is_waiting": false,
    "creator_id": 2,
    "created_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "reservation_start_date": ["The reservation start date field is required."],
    "reservation_from_time": [
      "The reservation from time must be before reservation to time."
    ]
  }
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "patient_id": 1,
    "doctor_id": 2,
    "clinics_id": 1,
    "status_id": 1,
    "reservation_start_date": "2026-01-20",
    "reservation_end_date": "2026-01-20",
    "reservation_from_time": "09:00",
    "reservation_to_time": "10:00",
    "notes": "Regular checkup",
    "is_waiting": false
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/reservations`
- Body (raw JSON): See request body above
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 201) {
  pm.environment.set("reservation_id", pm.response.json().data.id);
}
```

---

### 4. Update Reservation

Update an existing reservation.

**Endpoint:** `PUT /api/reservations/{id}`

**Authentication:** Required

**Request Body:**

```json
{
  "status_id": 2,
  "reservation_from_time": "10:00",
  "reservation_to_time": "11:00",
  "notes": "Rescheduled appointment"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Reservation updated successfully",
  "data": {
    "id": 1,
    "status_id": 2,
    "reservation_from_time": "10:00:00",
    "reservation_to_time": "11:00:00",
    "updator_id": 2,
    ...
  }
}
```

**cURL Example:**

```bash
curl -X PUT http://localhost:8000/api/reservations/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "status_id": 2,
    "notes": "Patient requested time change"
  }'
```

**Postman:**

- Method: PUT
- URL: `{{base_url}}/reservations/{{reservation_id}}`
- Body (raw JSON): See request body above
- Authorization: Bearer Token

---

### 5. Delete Reservation

Soft delete a reservation.

**Endpoint:** `DELETE /api/reservations/{id}`

**Authentication:** Required

**Success Response (200):**

```json
{
  "success": true,
  "message": "Reservation deleted successfully"
}
```

**cURL Example:**

```bash
curl -X DELETE http://localhost:8000/api/reservations/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: DELETE
- URL: `{{base_url}}/reservations/{{reservation_id}}`
- Authorization: Bearer Token

---

## Advanced Filtering Examples

### Get Today's Appointments

```bash
GET /reservations?filter[reservation_start_date]=2026-01-15&sort=reservation_from_time
```

### Get Doctor's Schedule

```bash
GET /reservations?filter[doctor_id]=2&filter[reservation_start_date]=2026-01-20&sort=reservation_from_time
```

### Get Waiting List Patients

```bash
GET /reservations?filter[is_waiting]=1&sort=-created_at
```

### Get Upcoming Appointments

```bash
GET /reservations?filter[status_id]=1&sort=reservation_start_date,reservation_from_time
```

### Get Clinic Schedule for Week

```bash
GET /reservations?filter[clinics_id]=1&filter[reservation_start_date][gte]=2026-01-20&filter[reservation_start_date][lte]=2026-01-26&sort=reservation_start_date
```

---

## Frontend Integration Example

### React Service

```javascript
import api from "./api";

export const reservationService = {
  async getAll(params = {}) {
    return await api.get("/reservations", { params });
  },

  async getById(id) {
    return await api.get(`/reservations/${id}`);
  },

  async create(reservationData) {
    return await api.post("/reservations", reservationData);
  },

  async update(id, reservationData) {
    return await api.put(`/reservations/${id}`, reservationData);
  },

  async delete(id) {
    return await api.delete(`/reservations/${id}`);
  },

  async getTodayReservations() {
    const today = new Date().toISOString().split("T")[0];
    return await api.get("/reservations", {
      params: {
        "filter[reservation_start_date]": today,
        sort: "reservation_from_time",
        include: "patient,doctor,status",
      },
    });
  },

  async getDoctorSchedule(doctorId, date) {
    return await api.get("/reservations", {
      params: {
        "filter[doctor_id]": doctorId,
        "filter[reservation_start_date]": date,
        sort: "reservation_from_time",
        include: "patient,status",
      },
    });
  },

  async getWaitingList() {
    return await api.get("/reservations", {
      params: {
        "filter[is_waiting]": 1,
        sort: "-created_at",
        include: "patient,doctor",
      },
    });
  },
};
```

### React Component - Calendar View

```jsx
import { useState, useEffect } from "react";
import { reservationService } from "../services/reservationService";

export default function AppointmentCalendar() {
  const [reservations, setReservations] = useState([]);
  const [selectedDate, setSelectedDate] = useState(
    new Date().toISOString().split("T")[0]
  );
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchReservations();
  }, [selectedDate]);

  const fetchReservations = async () => {
    setLoading(true);
    try {
      const response = await reservationService.getAll({
        "filter[reservation_start_date]": selectedDate,
        sort: "reservation_from_time",
        include: "patient,doctor,status",
      });
      setReservations(response.data);
    } catch (error) {
      console.error("Error fetching reservations:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleDateChange = (date) => {
    setSelectedDate(date);
  };

  const getStatusColor = (status) => {
    return status?.color || "#6B7280";
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      <h2>Appointments Schedule</h2>

      <input
        type="date"
        value={selectedDate}
        onChange={(e) => handleDateChange(e.target.value)}
      />

      <div className="appointments-list">
        {reservations.map((reservation) => (
          <div
            key={reservation.id}
            className="appointment-card"
            style={{ borderLeftColor: getStatusColor(reservation.status) }}
          >
            <div className="time">
              {reservation.reservation_from_time} -{" "}
              {reservation.reservation_to_time}
            </div>
            <div className="patient">
              <strong>{reservation.patient.name}</strong>
              <span>{reservation.patient.phone}</span>
            </div>
            <div className="doctor">{reservation.doctor.name}</div>
            <div
              className="status"
              style={{ backgroundColor: getStatusColor(reservation.status) }}
            >
              {reservation.status.name_en}
            </div>
            {reservation.notes && (
              <div className="notes">{reservation.notes}</div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
```

### Vue Component - Waiting List

```vue
<template>
  <div>
    <h2>Waiting List</h2>

    <div v-if="loading">Loading...</div>

    <table v-else>
      <thead>
        <tr>
          <th>ID</th>
          <th>Patient</th>
          <th>Phone</th>
          <th>Doctor</th>
          <th>Requested Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="reservation in waitingList" :key="reservation.id">
          <td>{{ reservation.id }}</td>
          <td>{{ reservation.patient.name }}</td>
          <td>{{ reservation.patient.phone }}</td>
          <td>{{ reservation.doctor.name }}</td>
          <td>{{ reservation.reservation_start_date }}</td>
          <td>
            <button @click="scheduleAppointment(reservation.id)">
              Schedule
            </button>
            <button @click="removeFromWaiting(reservation.id)">Remove</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { reservationService } from "../services/reservationService";

const waitingList = ref([]);
const loading = ref(false);

const fetchWaitingList = async () => {
  loading.value = true;
  try {
    const response = await reservationService.getWaitingList();
    waitingList.value = response.data;
  } catch (error) {
    console.error("Error fetching waiting list:", error);
  } finally {
    loading.value = false;
  }
};

const scheduleAppointment = async (id) => {
  try {
    await reservationService.update(id, {
      is_waiting: false,
      status_id: 1, // Confirmed
    });
    await fetchWaitingList();
  } catch (error) {
    console.error("Error scheduling appointment:", error);
  }
};

const removeFromWaiting = async (id) => {
  if (confirm("Remove from waiting list?")) {
    await reservationService.delete(id);
    await fetchWaitingList();
  }
};

onMounted(() => {
  fetchWaitingList();
});
</script>
```

---

## Validation Rules

| Field                  | Rules                                                  |
| ---------------------- | ------------------------------------------------------ |
| patient_id             | required, exists:patients,id                           |
| doctor_id              | required, exists:users,id                              |
| clinics_id             | required, exists:clinics,id                            |
| status_id              | required, exists:statuses,id                           |
| reservation_start_date | required, date                                         |
| reservation_end_date   | required, date, after_or_equal:reservation_start_date  |
| reservation_from_time  | required, date_format:H:i                              |
| reservation_to_time    | required, date_format:H:i, after:reservation_from_time |
| notes                  | nullable, string, max:5000                             |
| is_waiting             | nullable, boolean                                      |

---

## Business Logic

### Automatic User Tracking

- `creator_id` is automatically set when creating a reservation
- `updator_id` is automatically set when updating a reservation

### Waiting List Management

- Set `is_waiting: true` for patients on waiting list
- Use status to track appointment state (Confirmed, Cancelled, Completed, etc.)

### Date & Time Handling

- Dates: `YYYY-MM-DD` format
- Times: `HH:mm` format (24-hour)
- End time must be after start time
- End date must be same or after start date

### Status Management

Common status IDs:

- 1: Confirmed
- 2: Cancelled
- 3: Completed
- 4: No Show
- 5: Rescheduled

---

## Common Use Cases

### 1. Book Appointment

```javascript
const reservation = await reservationService.create({
  patient_id: 1,
  doctor_id: 2,
  clinics_id: 1,
  status_id: 1,
  reservation_start_date: "2026-01-20",
  reservation_end_date: "2026-01-20",
  reservation_from_time: "09:00",
  reservation_to_time: "10:00",
  notes: "Regular checkup",
  is_waiting: false,
});
```

### 2. Add to Waiting List

```javascript
const waiting = await reservationService.create({
  patient_id: 1,
  doctor_id: 2,
  clinics_id: 1,
  status_id: 1,
  reservation_start_date: "2026-01-20",
  reservation_end_date: "2026-01-20",
  reservation_from_time: "09:00",
  reservation_to_time: "10:00",
  is_waiting: true,
});
```

### 3. Check Doctor Availability

```javascript
const schedule = await reservationService.getDoctorSchedule(
  doctorId,
  "2026-01-20"
);

// Find available time slots
const bookedSlots = schedule.data.map((r) => ({
  from: r.reservation_from_time,
  to: r.reservation_to_time,
}));
```

### 4. Daily Schedule View

```javascript
const today = new Date().toISOString().split("T")[0];
const todayReservations = await reservationService.getTodayReservations();
```

---

## Notes

- Soft deletes are used - deleted reservations can be restored
- Creator and updator are automatically tracked
- Use `is_waiting` flag for waiting list management
- Times are stored in 24-hour format
- All timestamps are in UTC timezone
- Status colors are returned for UI display

---

**Last Updated:** January 15, 2026  
**API Version:** 1.0
