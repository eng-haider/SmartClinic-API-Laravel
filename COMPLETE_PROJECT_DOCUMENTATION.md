# SmartClinic API - Complete Project Documentation

**Version:** 1.0  
**Last Updated:** January 15, 2026  
**Framework:** Laravel 12  
**PHP Version:** 8.2+

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Architecture & Design Patterns](#architecture--design-patterns)
4. [Database Schema](#database-schema)
5. [Authentication System](#authentication-system)
6. [API Endpoints Reference](#api-endpoints-reference)
7. [Data Models](#data-models)
8. [Request & Response Formats](#request--response-formats)
9. [Frontend Integration Guide](#frontend-integration-guide)
10. [Environment Setup](#environment-setup)

---

## 1. Project Overview

SmartClinic API is a comprehensive clinic management system built with Laravel, providing RESTful APIs for managing:

- **Patients** - Patient records and medical history
- **Cases** - Medical cases and treatments
- **Reservations** - Appointment scheduling
- **Bills** - Payment and billing management
- **Recipes** - Medical prescriptions
- **Clinic Expenses** - Expense tracking and categories
- **Users** - Staff management with role-based access

### Key Features

✅ **JWT Authentication** - Secure token-based authentication  
✅ **Role-Based Access Control** - Admin, Doctor, Nurse, Receptionist, User roles  
✅ **Advanced Filtering & Sorting** - Using Spatie Laravel Query Builder  
✅ **Soft Deletes** - Safe data removal with restore capability  
✅ **Comprehensive API** - Full CRUD operations for all resources  
✅ **Clean Architecture** - Repository pattern with service layer  
✅ **API Resources** - Consistent response formatting  
✅ **Validation** - Form Request validation with custom messages  
✅ **Pagination** - Built-in pagination with metadata  
✅ **Relationships** - Eager loading support for optimized queries

---

## 2. Technology Stack

### Backend Framework

- **Laravel 12** - PHP framework
- **PHP 8.2+** - Programming language

### Key Packages

```json
{
  "tymon/jwt-auth": "^2.2", // JWT authentication
  "spatie/laravel-query-builder": "^6.3", // Advanced filtering
  "spatie/laravel-permission": "^6.23" // Role & permission management
}
```

### Database

- **MySQL / PostgreSQL / SQLite** - Relational database
- **Eloquent ORM** - Database abstraction

### Development Tools

- **Laravel Pint** - Code style fixer
- **PHPUnit** - Testing framework
- **Laravel Sail** - Docker development environment

---

## 3. Architecture & Design Patterns

### Clean Architecture Layers

```
┌─────────────────────────────────────────┐
│         HTTP Request Layer              │
│  (Routes, Middleware, Controllers)      │
├─────────────────────────────────────────┤
│         Business Logic Layer            │
│         (Services)                      │
├─────────────────────────────────────────┤
│         Data Access Layer               │
│         (Repositories)                  │
├─────────────────────────────────────────┤
│         Database Layer                  │
│         (Models, Eloquent)              │
└─────────────────────────────────────────┘
```

### Request Flow

```
Client Request
    ↓
Routes (api.php)
    ↓
Middleware (JWT Auth)
    ↓
Controller (HTTP handling)
    ↓
Form Request (Validation)
    ↓
Service (Business Logic)
    ↓
Repository (Data Access)
    ↓
Model (Eloquent)
    ↓
Database
    ↓
Resource (Response Formatting)
    ↓
JSON Response
```

### Directory Structure

```
app/
├── Http/
│   ├── Controllers/           # HTTP request handlers
│   │   ├── AuthController.php
│   │   ├── PatientController.php
│   │   ├── CaseController.php
│   │   ├── BillController.php
│   │   ├── ReservationController.php
│   │   ├── RecipeController.php
│   │   ├── CaseCategoryController.php
│   │   ├── ClinicExpenseController.php
│   │   └── ClinicExpenseCategoryController.php
│   │
│   ├── Middleware/            # Request/Response middleware
│   │   └── JwtMiddleware.php
│   │
│   ├── Requests/              # Form validation
│   │   ├── LoginRequest.php
│   │   ├── RegisterRequest.php
│   │   ├── PatientRequest.php
│   │   ├── CaseRequest.php
│   │   ├── BillRequest.php
│   │   └── ...
│   │
│   └── Resources/             # Response transformers
│       ├── UserResource.php
│       ├── PatientResource.php
│       ├── CaseResource.php
│       └── ...
│
├── Models/                    # Eloquent models
│   ├── User.php
│   ├── Patient.php
│   ├── Case.php
│   ├── Bill.php
│   ├── Reservation.php
│   ├── Recipe.php
│   ├── RecipeItem.php
│   ├── ClinicExpense.php
│   ├── ClinicExpenseCategory.php
│   ├── CaseCategory.php
│   ├── Status.php
│   ├── Clinic.php
│   ├── FromWhereCome.php
│   ├── Note.php
│   └── Image.php
│
├── Repositories/              # Data access layer
│   ├── UserRepository.php
│   ├── PatientRepository.php
│   ├── CaseRepository.php
│   ├── BillRepository.php
│   ├── ReservationRepository.php
│   ├── RecipeRepository.php
│   ├── ClinicExpenseRepository.php
│   ├── ClinicExpenseCategoryRepository.php
│   └── BaseRepository.php
│
└── Services/                  # Business logic layer
    ├── AuthService.php
    ├── PatientService.php
    └── CaseService.php
```

---

## 4. Database Schema

### Tables Overview

#### Users Table

```sql
users
  - id (PK)
  - name
  - email (unique, nullable)
  - phone (unique)
  - password
  - role (admin, doctor, nurse, receptionist, user)
  - is_active (boolean)
  - timestamps
```

#### Patients Table

```sql
patients
  - id (PK)
  - name
  - age
  - doctor_id (FK -> users)
  - clinics_id (FK -> clinics)
  - phone
  - systemic_conditions
  - sex (1=Male, 2=Female)
  - address
  - notes
  - birth_date
  - from_where_come_id (FK -> from_where_comes)
  - identifier
  - credit_balance
  - credit_balance_add_at
  - timestamps
  - deleted_at (soft delete)
```

#### Cases Table

```sql
cases
  - id (PK)
  - patient_id (FK -> patients)
  - doctor_id (FK -> users)
  - clinic_id (FK -> clinics)
  - case_categores_id (FK -> case_categories)
  - status_id (FK -> statuses)
  - notes
  - price
  - tooth_num
  - root_stuffing
  - is_paid (boolean)
  - timestamps
  - deleted_at (soft delete)
```

#### Bills Table

```sql
bills
  - id (PK)
  - patient_id (FK -> patients)
  - billable_id (polymorphic)
  - billable_type (polymorphic)
  - is_paid (boolean)
  - price
  - clinics_id (FK -> clinics)
  - doctor_id (FK -> users)
  - creator_id (FK -> users)
  - updator_id (FK -> users)
  - use_credit (boolean)
  - timestamps
  - deleted_at (soft delete)
```

#### Reservations Table

```sql
reservations
  - id (PK)
  - patient_id (FK -> patients)
  - doctor_id (FK -> users)
  - clinics_id (FK -> clinics)
  - status_id (FK -> statuses)
  - notes
  - reservation_start_date
  - reservation_end_date
  - reservation_from_time
  - reservation_to_time
  - is_waiting (boolean)
  - creator_id (FK -> users)
  - updator_id (FK -> users)
  - timestamps
  - deleted_at (soft delete)
```

#### Recipes Table

```sql
recipes
  - id (PK)
  - patient_id (FK -> patients)
  - doctors_id (FK -> users)
  - notes
  - timestamps
```

#### Recipe Items Table

```sql
recipe_items
  - id (PK)
  - recipe_id (FK -> recipes)
  - medication_name
  - dosage
  - frequency
  - duration
  - timestamps
```

#### Clinic Expenses Table

```sql
clinic_expenses
  - id (PK)
  - name
  - quantity
  - clinic_expense_category_id (FK)
  - clinic_id (FK -> clinics)
  - date
  - price
  - is_paid (boolean)
  - doctor_id (FK -> users)
  - creator_id (FK -> users)
  - updator_id (FK -> users)
  - timestamps
  - deleted_at (soft delete)
```

#### Case Categories Table

```sql
case_categories
  - id (PK)
  - name_ar
  - name_en
  - is_active (boolean)
  - timestamps
```

#### Statuses Table

```sql
statuses
  - id (PK)
  - name_ar
  - name_en
  - color (hex color code)
  - timestamps
```

### Relationships

**Patient Relationships:**

- belongsTo: Doctor (User), Clinic, FromWhereCome
- hasMany: Cases, Recipes, Reservations, Bills
- morphMany: Notes, Images

**Case Relationships:**

- belongsTo: Patient, Doctor (User), Category (CaseCategory), Status, Clinic
- morphMany: Notes, Bills

**Bill Relationships:**

- belongsTo: Patient, Clinic, Doctor (User), Creator (User), Updator (User)
- morphTo: Billable (Case, Reservation)

**Reservation Relationships:**

- belongsTo: Patient, Doctor (User), Clinic, Status, Creator (User), Updator (User)

**Recipe Relationships:**

- belongsTo: Patient, Doctor (User)
- hasMany: RecipeItems

---

## 5. Authentication System

### JWT Authentication Flow

```
1. User registers/logs in with phone + password
2. Server validates credentials
3. Server generates JWT token
4. Client stores token
5. Client sends token in Authorization header
6. Server validates token for protected routes
7. Server responds with requested data
```

### Authentication Endpoints

#### Register User

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "Ahmed Hassan",
  "phone": "201001234567",
  "email": "ahmed@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "doctor"
}

Response 201:
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": { ... },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "phone": "201001234567",
  "password": "password123"
}

Response 200:
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { ... },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### Get Current User

```http
GET /api/auth/me
Authorization: Bearer {token}

Response 200:
{
  "success": true,
  "message": "User retrieved successfully",
  "data": { ... }
}
```

#### Refresh Token

```http
POST /api/auth/refresh
Authorization: Bearer {token}

Response 200:
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### Logout

```http
POST /api/auth/logout
Authorization: Bearer {token}

Response 200:
{
  "success": true,
  "message": "Logout successful"
}
```

#### Change Password

```http
POST /api/auth/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}

Response 200:
{
  "success": true,
  "message": "Password changed successfully"
}
```

### Token Configuration

- **Default TTL:** 60 minutes
- **Algorithm:** HS256
- **Refresh:** Supported
- **Storage:** Store in httpOnly cookies or secure storage

### User Roles

- `admin` - Full system access
- `doctor` - Medical staff with patient access
- `nurse` - Nursing staff
- `receptionist` - Front desk staff
- `user` - Default role

---

## 6. API Endpoints Reference

### Base URL

```
http://localhost:8000/api
```

### Common Headers

```
Content-Type: application/json
Authorization: Bearer {jwt_token}  # For protected routes
```

### Patients API

#### List Patients

```http
GET /api/patients
Query Parameters:
  - per_page (default: 15)
  - page (default: 1)
  - filter[doctor_id]
  - filter[clinics_id]
  - filter[sex] (1=Male, 2=Female)
  - sort (-created_at, name, age)
  - search (name, phone, identifier)
```

#### Create Patient

```http
POST /api/patients
Body:
{
  "name": "John Doe",
  "age": 30,
  "phone": "01001234567",
  "sex": 1,
  "address": "123 Main St",
  "birth_date": "1994-01-15",
  "doctor_id": 1,
  "clinics_id": 1
}
```

#### Get Patient

```http
GET /api/patients/{id}
```

#### Update Patient

```http
PUT /api/patients/{id}
Body: (same as create, all fields optional)
```

#### Delete Patient

```http
DELETE /api/patients/{id}
```

#### Search by Phone

```http
GET /api/patients/search/phone/{phone}
```

#### Search by Email

```http
GET /api/patients/search/email/{email}
```

---

### Cases API

#### List Cases

```http
GET /api/cases
Query Parameters:
  - per_page
  - filter[patient_id]
  - filter[doctor_id]
  - filter[status_id]
  - filter[is_paid] (0 or 1)
  - sort
  - include (patient, doctor, category, status)
```

#### Create Case

```http
POST /api/cases
Body:
{
  "patient_id": 1,
  "doctor_id": 2,
  "case_categores_id": 3,
  "status_id": 1,
  "notes": "Root canal treatment",
  "price": 50000,
  "tooth_num": "14",
  "root_stuffing": "Composite",
  "is_paid": false
}
```

#### Get Case

```http
GET /api/cases/{id}
```

#### Update Case

```http
PUT /api/cases/{id}
```

#### Delete Case

```http
DELETE /api/cases/{id}
```

---

### Bills API

#### List Bills

```http
GET /api/bills
Query Parameters:
  - per_page
  - filter[patient_id]
  - filter[is_paid]
  - filter[doctor_id]
  - sort
```

#### Create Bill

```http
POST /api/bills
Body:
{
  "patient_id": 1,
  "billable_id": 1,
  "billable_type": "App\\Models\\Case",
  "price": 50000,
  "is_paid": false,
  "use_credit": false
}
```

#### Get Bill

```http
GET /api/bills/{id}
```

#### Update Bill

```http
PUT /api/bills/{id}
```

#### Delete Bill

```http
DELETE /api/bills/{id}
```

#### Mark as Paid

```http
PATCH /api/bills/{id}/mark-paid
```

#### Mark as Unpaid

```http
PATCH /api/bills/{id}/mark-unpaid
```

#### Get Bills by Patient

```http
GET /api/bills/patient/{patientId}
```

#### Get Statistics

```http
GET /api/bills/statistics/summary

Response:
{
  "success": true,
  "data": {
    "total_revenue": 500000,
    "total_unpaid": 150000,
    "total_expected": 650000,
    "payment_rate": 76.92
  }
}
```

---

### Reservations API

#### List Reservations

```http
GET /api/reservations
Query Parameters:
  - per_page
  - filter[patient_id]
  - filter[doctor_id]
  - filter[status_id]
  - filter[is_waiting]
  - filter[reservation_start_date]
  - sort
```

#### Create Reservation

```http
POST /api/reservations
Body:
{
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
}
```

#### Get Reservation

```http
GET /api/reservations/{id}
```

#### Update Reservation

```http
PUT /api/reservations/{id}
```

#### Delete Reservation

```http
DELETE /api/reservations/{id}
```

---

### Recipes API

#### List Recipes

```http
GET /api/recipes
Query Parameters:
  - per_page
  - filter[patient_id]
  - filter[doctors_id]
  - sort
```

#### Create Recipe

```http
POST /api/recipes
Body:
{
  "patient_id": 1,
  "doctors_id": 2,
  "notes": "Post-surgery prescription",
  "recipe_items": [
    {
      "medication_name": "Amoxicillin",
      "dosage": "500mg",
      "frequency": "3 times daily",
      "duration": "7 days"
    }
  ]
}
```

#### Get Recipe

```http
GET /api/recipes/{id}
```

#### Update Recipe

```http
PUT /api/recipes/{id}
```

#### Delete Recipe

```http
DELETE /api/recipes/{id}
```

---

### Clinic Expenses API

#### List Expenses

```http
GET /api/clinic-expenses
Query Parameters:
  - per_page
  - filter[clinic_id]
  - filter[clinic_expense_category_id]
  - filter[is_paid]
  - filter[doctor_id]
  - sort
```

#### Create Expense

```http
POST /api/clinic-expenses
Body:
{
  "name": "Medical supplies",
  "quantity": 10,
  "clinic_expense_category_id": 1,
  "clinic_id": 1,
  "date": "2026-01-15",
  "price": 25000,
  "is_paid": false,
  "doctor_id": 1
}
```

#### Get Expense

```http
GET /api/clinic-expenses/{id}
```

#### Update Expense

```http
PUT /api/clinic-expenses/{id}
```

#### Delete Expense

```http
DELETE /api/clinic-expenses/{id}
```

#### Mark as Paid

```http
PATCH /api/clinic-expenses/{id}/mark-paid
```

#### Mark as Unpaid

```http
PATCH /api/clinic-expenses/{id}/mark-unpaid
```

#### Get Statistics

```http
GET /api/clinic-expenses-statistics
Query Parameters:
  - start_date
  - end_date
  - clinic_id
```

#### Get Unpaid Expenses

```http
GET /api/clinic-expenses-unpaid
```

#### Get by Date Range

```http
GET /api/clinic-expenses-by-date-range
Query Parameters:
  - start_date
  - end_date
```

---

### Case Categories API

#### List Categories

```http
GET /api/case-categories
```

#### Create Category

```http
POST /api/case-categories
Body:
{
  "name_ar": "حشو الأسنان",
  "name_en": "Tooth Filling",
  "is_active": true
}
```

#### Get Category

```http
GET /api/case-categories/{id}
```

#### Update Category

```http
PUT /api/case-categories/{id}
```

#### Delete Category

```http
DELETE /api/case-categories/{id}
```

---

### Clinic Expense Categories API

#### List Categories

```http
GET /api/clinic-expense-categories
```

#### Create Category

```http
POST /api/clinic-expense-categories
Body:
{
  "name_ar": "مستلزمات طبية",
  "name_en": "Medical Supplies",
  "is_active": true
}
```

#### Get Active Categories

```http
GET /api/clinic-expense-categories-active
```

---

## 7. Data Models

### User Model

```php
{
  "id": 1,
  "name": "Dr. Ahmed Hassan",
  "email": "ahmed@example.com",
  "phone": "201001234567",
  "role": "doctor",
  "is_active": true,
  "created_at": "2026-01-01T10:00:00.000000Z",
  "updated_at": "2026-01-01T10:00:00.000000Z"
}
```

### Patient Model

```php
{
  "id": 1,
  "name": "John Doe",
  "age": 30,
  "phone": "01001234567",
  "sex": 1,
  "sex_label": "Male",
  "address": "123 Main St",
  "birth_date": "1994-01-15",
  "systemic_conditions": "None",
  "notes": "Regular patient",
  "identifier": "P-2026-001",
  "credit_balance": 0,
  "doctor": { ... },
  "clinic": { ... },
  "created_at": "2026-01-01T10:00:00.000000Z",
  "updated_at": "2026-01-01T10:00:00.000000Z"
}
```

### Case Model

```php
{
  "id": 1,
  "patient_id": 1,
  "doctor_id": 2,
  "case_categores_id": 3,
  "status_id": 1,
  "notes": "Root canal treatment",
  "price": 50000,
  "tooth_num": "14",
  "root_stuffing": "Composite material",
  "is_paid": false,
  "payment_status": "Unpaid",
  "patient": { ... },
  "doctor": { ... },
  "category": { ... },
  "status": { ... },
  "created_at": "2026-01-15T09:00:00.000000Z",
  "updated_at": "2026-01-15T09:00:00.000000Z"
}
```

### Bill Model

```php
{
  "id": 1,
  "patient_id": 1,
  "billable_id": 1,
  "billable_type": "App\\Models\\Case",
  "price": 50000,
  "is_paid": false,
  "use_credit": false,
  "patient": { ... },
  "billable": { ... },
  "clinic": { ... },
  "doctor": { ... },
  "creator": { ... },
  "created_at": "2026-01-15T09:00:00.000000Z",
  "updated_at": "2026-01-15T09:00:00.000000Z"
}
```

### Reservation Model

```php
{
  "id": 1,
  "patient_id": 1,
  "doctor_id": 2,
  "clinics_id": 1,
  "status_id": 1,
  "reservation_start_date": "2026-01-20",
  "reservation_end_date": "2026-01-20",
  "reservation_from_time": "09:00:00",
  "reservation_to_time": "10:00:00",
  "notes": "Regular checkup",
  "is_waiting": false,
  "patient": { ... },
  "doctor": { ... },
  "clinic": { ... },
  "status": { ... },
  "created_at": "2026-01-15T10:00:00.000000Z",
  "updated_at": "2026-01-15T10:00:00.000000Z"
}
```

### Recipe Model

```php
{
  "id": 1,
  "patient_id": 1,
  "doctors_id": 2,
  "notes": "Post-surgery prescription",
  "patient": { ... },
  "doctor": { ... },
  "recipe_items": [
    {
      "id": 1,
      "medication_name": "Amoxicillin",
      "dosage": "500mg",
      "frequency": "3 times daily",
      "duration": "7 days"
    }
  ],
  "created_at": "2026-01-15T11:00:00.000000Z",
  "updated_at": "2026-01-15T11:00:00.000000Z"
}
```

### Clinic Expense Model

```php
{
  "id": 1,
  "name": "Medical supplies",
  "quantity": 10,
  "price": 25000.00,
  "is_paid": false,
  "date": "2026-01-15",
  "category": { ... },
  "clinic": { ... },
  "doctor": { ... },
  "creator": { ... },
  "created_at": "2026-01-15T12:00:00.000000Z",
  "updated_at": "2026-01-15T12:00:00.000000Z"
}
```

---

## 8. Request & Response Formats

### Standard Response Format

#### Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

#### Success with Pagination

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

#### Error Response (404)

```json
{
  "success": false,
  "message": "Resource not found"
}
```

#### Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": ["The phone field is required."],
    "email": ["The email has already been taken."]
  }
}
```

#### Authentication Error (401)

```json
{
  "success": false,
  "message": "Unauthenticated",
  "error": "Token has expired"
}
```

#### Server Error (500)

```json
{
  "success": false,
  "message": "Internal server error",
  "error": "Detailed error message"
}
```

### HTTP Status Codes

| Code | Meaning               | Usage                                |
| ---- | --------------------- | ------------------------------------ |
| 200  | OK                    | Successful GET, PUT, PATCH requests  |
| 201  | Created               | Successful POST request              |
| 204  | No Content            | Successful DELETE request (optional) |
| 400  | Bad Request           | Invalid request format               |
| 401  | Unauthorized          | Authentication required or failed    |
| 403  | Forbidden             | Authenticated but not authorized     |
| 404  | Not Found             | Resource doesn't exist               |
| 422  | Unprocessable Entity  | Validation failed                    |
| 500  | Internal Server Error | Server-side error                    |

---

## 9. Frontend Integration Guide

### Initial Setup

#### 1. Install HTTP Client

```bash
# For React/Vue/Angular
npm install axios

# Or use fetch API (built-in)
```

#### 2. Configure Base URL

```javascript
// config/api.js
import axios from "axios";

const api = axios.create({
  baseURL: "http://localhost:8000/api",
  headers: {
    "Content-Type": "application/json",
  },
});

export default api;
```

#### 3. Setup Authentication Interceptor

```javascript
// config/api.js
import axios from "axios";

const api = axios.create({
  baseURL: process.env.REACT_APP_API_URL || "http://localhost:8000/api",
  headers: {
    "Content-Type": "application/json",
  },
});

// Request interceptor - Add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("auth_token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Handle errors
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      localStorage.removeItem("auth_token");
      window.location.href = "/login";
    }
    return Promise.reject(error);
  }
);

export default api;
```

### Authentication Flow

#### 1. Login

```javascript
// services/authService.js
import api from "../config/api";

export const authService = {
  async login(phone, password) {
    const response = await api.post("/auth/login", {
      phone,
      password,
    });

    if (response.success) {
      localStorage.setItem("auth_token", response.data.token);
      localStorage.setItem("user", JSON.stringify(response.data.user));
    }

    return response;
  },

  async register(userData) {
    const response = await api.post("/auth/register", userData);

    if (response.success) {
      localStorage.setItem("auth_token", response.data.token);
      localStorage.setItem("user", JSON.stringify(response.data.user));
    }

    return response;
  },

  async logout() {
    try {
      await api.post("/auth/logout");
    } finally {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
    }
  },

  async getCurrentUser() {
    return await api.get("/auth/me");
  },

  async refreshToken() {
    const response = await api.post("/auth/refresh");
    if (response.success) {
      localStorage.setItem("auth_token", response.data.token);
    }
    return response;
  },

  getToken() {
    return localStorage.getItem("auth_token");
  },

  getUser() {
    const user = localStorage.getItem("user");
    return user ? JSON.parse(user) : null;
  },

  isAuthenticated() {
    return !!this.getToken();
  },
};
```

#### 2. React Login Component Example

```jsx
// components/Login.jsx
import { useState } from "react";
import { authService } from "../services/authService";
import { useNavigate } from "react-router-dom";

export default function Login() {
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const response = await authService.login(phone, password);
      if (response.success) {
        navigate("/dashboard");
      }
    } catch (err) {
      setError(err.response?.data?.message || "Login failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="tel"
        placeholder="Phone"
        value={phone}
        onChange={(e) => setPhone(e.target.value)}
        required
      />
      <input
        type="password"
        placeholder="Password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        required
      />
      {error && <div className="error">{error}</div>}
      <button type="submit" disabled={loading}>
        {loading ? "Loading..." : "Login"}
      </button>
    </form>
  );
}
```

### CRUD Operations Examples

#### Patient Service

```javascript
// services/patientService.js
import api from "../config/api";

export const patientService = {
  // Get all patients with filters
  async getAll(params = {}) {
    return await api.get("/patients", { params });
  },

  // Get single patient
  async getById(id) {
    return await api.get(`/patients/${id}`);
  },

  // Create patient
  async create(patientData) {
    return await api.post("/patients", patientData);
  },

  // Update patient
  async update(id, patientData) {
    return await api.put(`/patients/${id}`, patientData);
  },

  // Delete patient
  async delete(id) {
    return await api.delete(`/patients/${id}`);
  },

  // Search by phone
  async searchByPhone(phone) {
    return await api.get(`/patients/search/phone/${phone}`);
  },
};
```

#### Case Service

```javascript
// services/caseService.js
import api from "../config/api";

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
    return await api.patch(`/cases/${id}/mark-paid`);
  },

  async markAsUnpaid(id) {
    return await api.patch(`/cases/${id}/mark-unpaid`);
  },
};
```

#### React Component Example - Patient List

```jsx
// components/PatientList.jsx
import { useState, useEffect } from "react";
import { patientService } from "../services/patientService";

export default function PatientList() {
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({});
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 15,
    search: "",
    "filter[sex]": "",
  });

  useEffect(() => {
    fetchPatients();
  }, [filters]);

  const fetchPatients = async () => {
    setLoading(true);
    try {
      const response = await patientService.getAll(filters);
      setPatients(response.data);
      setPagination(response.pagination);
    } catch (error) {
      console.error("Failed to fetch patients:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (searchTerm) => {
    setFilters({ ...filters, search: searchTerm, page: 1 });
  };

  const handlePageChange = (page) => {
    setFilters({ ...filters, page });
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      <h1>Patients</h1>

      {/* Search */}
      <input
        type="text"
        placeholder="Search patients..."
        onChange={(e) => handleSearch(e.target.value)}
      />

      {/* Patient List */}
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Age</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {patients.map((patient) => (
            <tr key={patient.id}>
              <td>{patient.id}</td>
              <td>{patient.name}</td>
              <td>{patient.phone}</td>
              <td>{patient.age}</td>
              <td>
                <button onClick={() => viewPatient(patient.id)}>View</button>
                <button onClick={() => editPatient(patient.id)}>Edit</button>
                <button onClick={() => deletePatient(patient.id)}>
                  Delete
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* Pagination */}
      <div className="pagination">
        <button
          onClick={() => handlePageChange(pagination.current_page - 1)}
          disabled={pagination.current_page === 1}
        >
          Previous
        </button>
        <span>
          Page {pagination.current_page} of {pagination.last_page}
        </span>
        <button
          onClick={() => handlePageChange(pagination.current_page + 1)}
          disabled={pagination.current_page === pagination.last_page}
        >
          Next
        </button>
      </div>
    </div>
  );
}
```

### State Management (Redux Example)

#### Patient Slice

```javascript
// store/slices/patientSlice.js
import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { patientService } from "../../services/patientService";

export const fetchPatients = createAsyncThunk(
  "patients/fetchAll",
  async (params) => {
    return await patientService.getAll(params);
  }
);

export const createPatient = createAsyncThunk(
  "patients/create",
  async (patientData) => {
    return await patientService.create(patientData);
  }
);

const patientSlice = createSlice({
  name: "patients",
  initialState: {
    list: [],
    pagination: {},
    loading: false,
    error: null,
  },
  reducers: {},
  extraReducers: (builder) => {
    builder
      .addCase(fetchPatients.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchPatients.fulfilled, (state, action) => {
        state.loading = false;
        state.list = action.payload.data;
        state.pagination = action.payload.pagination;
      })
      .addCase(fetchPatients.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message;
      });
  },
});

export default patientSlice.reducer;
```

### Vue.js Integration Example

#### Vue 3 Composition API

```vue
<!-- components/PatientList.vue -->
<template>
  <div>
    <h1>Patients</h1>

    <input v-model="searchTerm" @input="handleSearch" placeholder="Search..." />

    <div v-if="loading">Loading...</div>

    <table v-else>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="patient in patients" :key="patient.id">
          <td>{{ patient.id }}</td>
          <td>{{ patient.name }}</td>
          <td>{{ patient.phone }}</td>
          <td>
            <button @click="viewPatient(patient.id)">View</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { patientService } from "../services/patientService";

const patients = ref([]);
const loading = ref(false);
const searchTerm = ref("");

const fetchPatients = async () => {
  loading.value = true;
  try {
    const response = await patientService.getAll({
      search: searchTerm.value,
    });
    patients.value = response.data;
  } catch (error) {
    console.error("Error fetching patients:", error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  fetchPatients();
};

onMounted(() => {
  fetchPatients();
});
</script>
```

### Common Filtering & Sorting Patterns

```javascript
// Filter by gender
const malePatients = await patientService.getAll({
  "filter[sex]": 1,
});

// Sort by creation date (newest first)
const recentPatients = await patientService.getAll({
  sort: "-created_at",
});

// Search with filter
const results = await patientService.getAll({
  search: "john",
  "filter[sex]": 1,
  sort: "name",
});

// Pagination
const page2 = await patientService.getAll({
  page: 2,
  per_page: 20,
});

// Get cases for a specific patient
const patientCases = await caseService.getAll({
  "filter[patient_id]": patientId,
  include: "doctor,category,status",
});

// Get unpaid bills
const unpaidBills = await billService.getAll({
  "filter[is_paid]": 0,
  sort: "-created_at",
});
```

---

## 10. Environment Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL / PostgreSQL / SQLite
- Node.js & NPM (for Vite assets)

### Installation Steps

#### 1. Clone Repository

```bash
git clone <repository-url>
cd SmartClinic-API-Laravel
```

#### 2. Install Dependencies

```bash
composer install
npm install
```

#### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

#### 4. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartclinic
DB_USERNAME=root
DB_PASSWORD=
```

#### 5. Generate JWT Secret

```bash
php artisan jwt:secret
```

#### 6. Run Migrations

```bash
php artisan migrate
```

#### 7. Seed Database (Optional)

```bash
php artisan db:seed
```

#### 8. Start Development Server

```bash
# Backend
php artisan serve

# Frontend assets (if needed)
npm run dev
```

### Environment Variables

```env
# Application
APP_NAME="SmartClinic API"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartclinic
DB_USERNAME=root
DB_PASSWORD=

# JWT Authentication
JWT_SECRET=your_secret_key
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173

# Cache
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Mail (if needed)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Production Deployment

#### 1. Optimize Application

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

#### 2. Set Environment to Production

```env
APP_ENV=production
APP_DEBUG=false
```

#### 3. Configure Web Server (Nginx Example)

```nginx
server {
    listen 80;
    server_name api.smartclinic.com;
    root /var/www/smartclinic-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### 4. Setup SSL

```bash
sudo certbot --nginx -d api.smartclinic.com
```

#### 5. Setup Queue Worker (if using queues)

```bash
php artisan queue:work --daemon
```

### Testing

#### Run Tests

```bash
php artisan test
```

#### Run Specific Test

```bash
php artisan test --filter=PatientTest
```

---

## API Testing with Postman

### Postman Collection Structure

```
SmartClinic API/
├── Auth/
│   ├── Register
│   ├── Login
│   ├── Get Current User
│   ├── Refresh Token
│   ├── Change Password
│   └── Logout
├── Patients/
│   ├── List Patients
│   ├── Create Patient
│   ├── Get Patient
│   ├── Update Patient
│   ├── Delete Patient
│   ├── Search by Phone
│   └── Search by Email
├── Cases/
│   ├── List Cases
│   ├── Create Case
│   ├── Get Case
│   ├── Update Case
│   ├── Delete Case
│   ├── Mark as Paid
│   └── Mark as Unpaid
├── Bills/
│   ├── List Bills
│   ├── Create Bill
│   ├── Get Bill
│   ├── Update Bill
│   ├── Delete Bill
│   ├── Mark as Paid
│   ├── Mark as Unpaid
│   ├── By Patient
│   └── Statistics
├── Reservations/
│   ├── List Reservations
│   ├── Create Reservation
│   ├── Get Reservation
│   ├── Update Reservation
│   └── Delete Reservation
├── Recipes/
│   ├── List Recipes
│   ├── Create Recipe
│   ├── Get Recipe
│   ├── Update Recipe
│   └── Delete Recipe
└── Clinic Expenses/
    ├── List Expenses
    ├── Create Expense
    ├── Get Expense
    ├── Update Expense
    ├── Delete Expense
    ├── Mark as Paid
    ├── Mark as Unpaid
    ├── Statistics
    └── By Date Range
```

### Environment Variables in Postman

```
base_url: http://localhost:8000/api
token: (auto-populated from login)
```

### Pre-request Script for Authentication

```javascript
// Add to Collection level
const token = pm.environment.get("token");
if (token) {
  pm.request.headers.add({
    key: "Authorization",
    value: `Bearer ${token}`,
  });
}
```

### Test Script for Login (Save Token)

```javascript
// Add to Login request Tests tab
if (pm.response.code === 200) {
  const response = pm.response.json();
  if (response.success && response.data.token) {
    pm.environment.set("token", response.data.token);
  }
}
```

---

## Common Issues & Solutions

### Issue: Token Expired

**Error:** `"Token has expired"`  
**Solution:** Call `/api/auth/refresh` to get a new token

### Issue: CORS Error

**Error:** `"Access to XMLHttpRequest has been blocked by CORS policy"`  
**Solution:** Add frontend URL to CORS allowed origins in `config/cors.php`

### Issue: 419 Unknown Status

**Error:** `419 Unknown Status`  
**Solution:** Ensure CSRF token is not being validated for API routes (already configured)

### Issue: Validation Errors

**Error:** `422 Unprocessable Entity`  
**Solution:** Check request body matches validation rules in Form Request classes

### Issue: Database Connection Failed

**Error:** `Connection refused`  
**Solution:** Verify database credentials in `.env` and ensure database server is running

---

## Performance Optimization Tips

### 1. Eager Loading

```javascript
// Load relationships to avoid N+1 queries
const cases = await caseService.getAll({
  include: "patient,doctor,category,status",
});
```

### 2. Pagination

```javascript
// Always use pagination for large datasets
const patients = await patientService.getAll({
  per_page: 15,
  page: 1,
});
```

### 3. Filtering at API Level

```javascript
// Filter on server-side, not client-side
const activeCases = await caseService.getAll({
  "filter[is_paid]": 0,
});
```

### 4. Caching (Backend)

```php
// Laravel caching for frequently accessed data
Cache::remember('categories', 3600, function () {
    return CaseCategory::all();
});
```

---

## Security Best Practices

1. **Always use HTTPS in production**
2. **Store JWT tokens securely** (httpOnly cookies preferred)
3. **Implement rate limiting** on authentication endpoints
4. **Validate all inputs** on both frontend and backend
5. **Use environment variables** for sensitive data
6. **Keep dependencies updated** regularly
7. **Implement proper error handling** without exposing sensitive info
8. **Use CORS whitelist** for allowed origins
9. **Sanitize user inputs** to prevent XSS attacks
10. **Use prepared statements** (Eloquent does this by default)

---

## Support & Resources

### Documentation

- Laravel: https://laravel.com/docs
- JWT Auth: https://jwt-auth.readthedocs.io/
- Spatie Query Builder: https://spatie.be/docs/laravel-query-builder/

### Community

- Laravel Discord: https://discord.gg/laravel
- Stack Overflow: https://stackoverflow.com/questions/tagged/laravel

---

## Appendix: Quick Reference

### Common Query Parameters

```
per_page=15              # Items per page
page=1                   # Current page
sort=-created_at         # Sort descending by created_at
search=keyword           # Search across searchable fields
filter[field]=value      # Filter by field value
include=relation         # Eager load relationships
```

### Date Formats

- **Date:** `YYYY-MM-DD` (e.g., `2026-01-15`)
- **DateTime:** `YYYY-MM-DD HH:mm:ss` (e.g., `2026-01-15 09:30:00`)
- **Time:** `HH:mm` (e.g., `09:30`)

### Sex/Gender Values

- `1` = Male
- `2` = Female

### Boolean Values

- `true` / `1` = Yes/Enabled
- `false` / `0` = No/Disabled

### User Roles

- `admin` - Full access
- `doctor` - Doctor access
- `nurse` - Nurse access
- `receptionist` - Reception access
- `user` - Default user

---

**End of Documentation**

For questions or issues, please contact the development team or create an issue in the project repository.
