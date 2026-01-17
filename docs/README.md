# SmartClinic API - Documentation Index

**Version:** 1.0  
**Last Updated:** January 15, 2026  
**Framework:** Laravel 12  
**PHP Version:** 8.2+

---

## 📚 Documentation Structure

This directory contains complete API documentation for the SmartClinic system. Each API module has its own dedicated documentation file with detailed examples, cURL commands, Postman setup, and frontend integration code.

---

## 📖 Available Documentation

### 1. [Authentication API](./AUTH_API.md)

Complete JWT authentication system documentation.

**Endpoints:**

- `POST /auth/register` - Register new user
- `POST /auth/login` - Login with phone + password
- `GET /auth/me` - Get current user
- `POST /auth/refresh` - Refresh JWT token
- `POST /auth/change-password` - Change password
- `POST /auth/logout` - Logout

**Features:**

- JWT token management
- Phone-based authentication
- Role-based access control
- Password change functionality

---

### 2. [Patients API](./PATIENTS_API.md)

Patient records and medical history management.

**Endpoints:**

- `GET /patients` - List all patients (with filters)
- `GET /patients/{id}` - Get single patient
- `POST /patients` - Create patient
- `PUT /patients/{id}` - Update patient
- `DELETE /patients/{id}` - Delete patient
- `GET /patients/search/phone/{phone}` - Search by phone
- `GET /patients/search/email/{email}` - Search by email

**Features:**

- Advanced filtering and searching
- Credit balance tracking
- Patient demographics
- Soft deletes

---

### 3. [Cases API](./CASES_API.md)

Medical case and treatment management.

**Endpoints:**

- `GET /cases` - List all cases (with filters)
- `GET /cases/{id}` - Get single case
- `POST /cases` - Create case
- `PUT /cases/{id}` - Update case
- `DELETE /cases/{id}` - Delete case

**Features:**

- Treatment tracking
- Payment status
- Tooth number tracking
- Status management
- Soft deletes

---

### 4. [Bills API](./BILLS_API.md)

Billing and payment management system.

**Endpoints:**

- `GET /bills` - List all bills (with filters)
- `GET /bills/{id}` - Get single bill
- `POST /bills` - Create bill
- `PUT /bills/{id}` - Update bill
- `DELETE /bills/{id}` - Delete bill
- `PATCH /bills/{id}/mark-paid` - Mark as paid
- `PATCH /bills/{id}/mark-unpaid` - Mark as unpaid
- `GET /bills/patient/{patientId}` - Get patient bills
- `GET /bills/statistics/summary` - Get revenue statistics

**Features:**

- Payment tracking
- Revenue statistics
- Polymorphic billing (Cases/Reservations)
- Credit balance support
- User tracking (creator/updator)

---

### 5. [Reservations API](./RESERVATIONS_API.md)

Appointment scheduling and waiting list management.

**Endpoints:**

- `GET /reservations` - List all reservations (with filters)
- `GET /reservations/{id}` - Get single reservation
- `POST /reservations` - Create reservation
- `PUT /reservations/{id}` - Update reservation
- `DELETE /reservations/{id}` - Delete reservation

**Features:**

- Date and time management
- Doctor schedule tracking
- Waiting list support
- Status management
- User tracking (creator/updator)

---

### 6. [Recipes API](./RECIPES_API.md)

Medical prescription and medication management.

**Endpoints:**

- `GET /recipes` - List all recipes (with filters)
- `GET /recipes/{id}` - Get single recipe
- `POST /recipes` - Create recipe with medications
- `PUT /recipes/{id}` - Update recipe
- `DELETE /recipes/{id}` - Delete recipe

**Features:**

- Multiple medications per prescription
- Dosage and frequency tracking
- Patient prescription history
- Doctor prescriptions

---

## 🚀 Quick Start Guide

### 1. Authentication Flow

```bash
# Register
POST /api/auth/register
{
  "name": "Dr. Ahmed",
  "phone": "201001234567",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "doctor"
}

# Login
POST /api/auth/login
{
  "phone": "201001234567",
  "password": "password123"
}

# Use returned token for all requests
Authorization: Bearer {token}
```

### 2. Common Workflow

```bash
# 1. Create Patient
POST /api/patients

# 2. Create Case for Patient
POST /api/cases

# 3. Create Bill for Case
POST /api/bills

# 4. Mark Bill as Paid
PATCH /api/bills/{id}/mark-paid

# 5. Create Reservation
POST /api/reservations

# 6. Create Prescription
POST /api/recipes
```

---

## 🔧 Postman Collection

### Setup Instructions

1. **Import Collection**

   - Use the `POSTMAN_COLLECTION.json` file in this directory

2. **Configure Environment**

   ```json
   {
     "base_url": "http://localhost:8000/api",
     "token": "",
     "patient_id": "",
     "case_id": "",
     "bill_id": "",
     "reservation_id": "",
     "recipe_id": ""
   }
   ```

3. **Collection Pre-request Script**

   ```javascript
   // Auto-add token to all requests
   const token = pm.environment.get("token");
   if (token) {
     pm.request.headers.add({
       key: "Authorization",
       value: `Bearer ${token}`,
     });
   }
   ```

4. **Save Tokens Automatically**
   Add this to Login request Tests:
   ```javascript
   if (pm.response.code === 200) {
     const response = pm.response.json();
     pm.environment.set("token", response.data.token);
   }
   ```

---

## 📋 Common Query Parameters

### Filtering

```
filter[field]=value
filter[patient_id]=1
filter[is_paid]=0
filter[status_id]=1
```

### Sorting

```
sort=field         # Ascending
sort=-field        # Descending
sort=-created_at   # Newest first
```

### Pagination

```
per_page=20        # Items per page
page=2             # Page number
```

### Include Relationships

```
include=patient,doctor,category
include=recipeItems
```

### Search

```
search=keyword     # Search across multiple fields
```

---

## 🎨 Frontend Integration

### React Example

```javascript
import axios from "axios";

const api = axios.create({
  baseURL: "http://localhost:8000/api",
  headers: {
    "Content-Type": "application/json",
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle errors
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login
      window.location.href = "/login";
    }
    return Promise.reject(error);
  }
);

export default api;
```

### Vue Example

```javascript
import axios from "axios";

const api = axios.create({
  baseURL: process.env.VUE_APP_API_URL,
  headers: {
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
```

---

## 🔐 Authentication

All API endpoints (except register and login) require authentication:

```
Authorization: Bearer {jwt_token}
```

### Token Management

- **TTL:** 60 minutes
- **Refresh:** Use `/auth/refresh` endpoint
- **Logout:** Use `/auth/logout` to invalidate token

---

## 📊 Response Format

### Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Success with Pagination

```json
{
  "success": true,
  "message": "Records retrieved successfully",
  "data": [ ... ],
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

### Error Response

```json
{
  "success": false,
  "message": "Error message"
}
```

### Validation Error

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

---

## 🌐 HTTP Status Codes

| Code | Meaning               | Usage                          |
| ---- | --------------------- | ------------------------------ |
| 200  | OK                    | Successful GET, PUT, PATCH     |
| 201  | Created               | Successful POST                |
| 204  | No Content            | Successful DELETE (sometimes)  |
| 400  | Bad Request           | Invalid request                |
| 401  | Unauthorized          | Authentication required/failed |
| 403  | Forbidden             | Insufficient permissions       |
| 404  | Not Found             | Resource doesn't exist         |
| 422  | Unprocessable Entity  | Validation failed              |
| 500  | Internal Server Error | Server-side error              |

---

## 🛠️ Development Setup

### Prerequisites

- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Node.js & NPM

### Installation

```bash
# Clone repository
git clone <repository-url>
cd SmartClinic-API-Laravel

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Database
php artisan migrate
php artisan db:seed

# Start server
php artisan serve
```

### API URL

```
http://localhost:8000/api
```

---

## 📝 Additional Resources

### Core Documentation Files

- **COMPLETE_PROJECT_DOCUMENTATION.md** - Complete project overview
- **README.md** - Project readme
- **CLEAN_ARCHITECTURE.md** - Architecture patterns
- **QUERY_BUILDER_GUIDE.md** - Advanced filtering guide

### Specific Guides

- **JWT_AUTHENTICATION.md** - Authentication deep dive
- **LARAVEL_PERMISSION_GUIDE.md** - Permissions system
- **IMPLEMENTATION_SUMMARY.md** - Implementation details

---

## 🤝 API Testing Workflow

### 1. Authentication

```
Register → Login → Save Token
```

### 2. Patient Management

```
Create Patient → Get Patient → Update Patient → Search
```

### 3. Case Management

```
Create Case → Get Cases (with filters) → Update Status → Mark Paid
```

### 4. Billing

```
Create Bill → Get Statistics → Mark Paid → Get Patient Bills
```

### 5. Appointments

```
Create Reservation → Get Today's Schedule → Update Status
```

### 6. Prescriptions

```
Create Recipe with Medications → Get Patient History → Print
```

---

## 💡 Best Practices

### 1. Use Relationships

```javascript
// Include relationships to reduce API calls
const cases = await api.get("/cases?include=patient,doctor,category,status");
```

### 2. Filter on Server

```javascript
// Filter on API, not in frontend
const unpaid = await api.get("/bills?filter[is_paid]=0");
```

### 3. Pagination

```javascript
// Always paginate large datasets
const patients = await api.get("/patients?per_page=20&page=1");
```

### 4. Error Handling

```javascript
try {
  const response = await api.post("/patients", data);
} catch (error) {
  if (error.response?.status === 422) {
    // Handle validation errors
    console.log(error.response.data.errors);
  }
}
```

### 5. Token Refresh

```javascript
// Refresh token before expiration
setInterval(async () => {
  await api.post("/auth/refresh");
}, 50 * 60 * 1000); // 50 minutes
```

---

## 🔍 Search & Filter Examples

### Patient Search

```bash
# Search by name, phone
GET /patients?search=john

# Filter by gender and sort
GET /patients?filter[sex]=1&sort=-created_at

# Complex query
GET /patients?search=cairo&filter[sex]=1&filter[doctor_id]=2&sort=name&per_page=20
```

### Case Filters

```bash
# Unpaid cases for patient
GET /cases?filter[patient_id]=1&filter[is_paid]=0

# Cases by doctor and status
GET /cases?filter[doctor_id]=2&filter[status_id]=1&include=patient,category
```

### Bill Statistics

```bash
# Revenue for date range
GET /bills/statistics/summary?start_date=2026-01-01&end_date=2026-01-31

# Unpaid bills
GET /bills?filter[is_paid]=0&sort=-created_at
```

### Reservation Schedule

```bash
# Today's appointments
GET /reservations?filter[reservation_start_date]=2026-01-15&sort=reservation_from_time

# Doctor's schedule
GET /reservations?filter[doctor_id]=2&filter[reservation_start_date]=2026-01-20
```

---

## 📧 Support

For questions or issues, please refer to individual API documentation files or contact the development team.

---

## 📄 License

Copyright © 2026 SmartClinic

---

**Last Updated:** January 15, 2026  
**Documentation Version:** 1.0  
**API Version:** 1.0
