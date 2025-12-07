# Quick Start Guide - Patients API

## Installation & Setup

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

```bash
# Create patients table
php artisan migrate

# Seed with test data (optional)
php artisan db:seed --class=PatientSeeder
```

### 4. Run the Server

```bash
php artisan serve
# Server will be available at http://localhost:8000
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/PatientController.php      # API Endpoints
│   ├── Requests/PatientRequest.php            # Validation
│   └── Resources/PatientResource.php          # Response Formatting
├── Models/Patient.php                         # Database Model
├── Repositories/
│   ├── Contracts/PatientRepositoryInterface.php
│   ├── PatientRepository.php                  # Data Access
│   ├── BaseRepository.php                     # Base class
│   └── QueryBuilderExamples.php              # Usage examples
├── Services/PatientService.php                # Business Logic
└── Providers/AppServiceProvider.php           # DI Container
```

---

## API Endpoints

### Create Patient

```bash
POST /api/patients
Content-Type: application/json

{
  "first_name": "Ahmed",
  "last_name": "Hassan",
  "phone": "01001234567",
  "date_of_birth": "1990-05-15",
  "gender": "male",
  "email": "ahmed@example.com"
}
```

### Get All Patients

```bash
GET /api/patients
GET /api/patients?filter[gender]=male
GET /api/patients?filter[city]=Cairo&sort=-created_at
GET /api/patients?search=ahmed&filter[is_active]=1
```

### Get Single Patient

```bash
GET /api/patients/{id}
```

### Update Patient

```bash
PUT /api/patients/{id}
Content-Type: application/json

{
  "first_name": "Ahmed Updated"
}
```

### Delete Patient

```bash
DELETE /api/patients/{id}
```

### Search Patient

```bash
GET /api/patients/search/phone/01001234567
GET /api/patients/search/email/ahmed@example.com
```

---

## Query Builder Examples

### Filter by single field

```bash
GET /api/patients?filter[gender]=male
GET /api/patients?filter[blood_type]=O+
GET /api/patients?filter[city]=Cairo
GET /api/patients?filter[is_active]=1
```

### Filter by multiple fields

```bash
GET /api/patients?filter[gender]=male&filter[city]=Cairo
GET /api/patients?filter[is_active]=1&filter[blood_type]=O+
```

### Sort results

```bash
GET /api/patients?sort=first_name              # Ascending
GET /api/patients?sort=-first_name             # Descending
GET /api/patients?sort=-created_at             # Newest first
GET /api/patients?sort=-created_at,first_name  # Multiple sorts
```

### Search + Filters + Sort

```bash
GET /api/patients?search=ahmed&filter[gender]=male&sort=-created_at
```

### Pagination

```bash
GET /api/patients?per_page=20&page=2
GET /api/patients?filter[city]=Cairo&per_page=15&page=3
```

---

## Testing with cURL

### Create Patient

```bash
curl -X POST http://localhost:8000/api/patients \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ali",
    "last_name": "Mohammed",
    "phone": "01098765432",
    "date_of_birth": "1995-03-20",
    "gender": "male",
    "email": "ali@example.com"
  }'
```

### Get All Patients

```bash
curl http://localhost:8000/api/patients
```

### Filter by Gender

```bash
curl "http://localhost:8000/api/patients?filter[gender]=male"
```

### Sort by Name

```bash
curl "http://localhost:8000/api/patients?sort=first_name"
```

### Get Specific Patient

```bash
curl http://localhost:8000/api/patients/1
```

### Update Patient

```bash
curl -X PUT http://localhost:8000/api/patients/1 \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ali Updated"
  }'
```

### Delete Patient

```bash
curl -X DELETE http://localhost:8000/api/patients/1
```

---

## Testing with Postman

### Import Collection

1. Open Postman
2. Click "Import"
3. Create requests for each endpoint

### Common Filters

| Filter     | Example                            |
| ---------- | ---------------------------------- |
| Gender     | `?filter[gender]=male`             |
| Blood Type | `?filter[blood_type]=O+`           |
| City       | `?filter[city]=Cairo`              |
| Active     | `?filter[is_active]=1`             |
| Email      | `?filter[email]=ahmed@example.com` |

### Common Sorts

| Sort             | Example                        |
| ---------------- | ------------------------------ |
| By Name          | `?sort=first_name`             |
| By Name (Desc)   | `?sort=-first_name`            |
| By Date (Newest) | `?sort=-created_at`            |
| Multiple         | `?sort=-created_at,first_name` |

---

## Database Fields

| Field                   | Type      | Notes                            |
| ----------------------- | --------- | -------------------------------- |
| id                      | Integer   | Primary Key                      |
| first_name              | String    | Required                         |
| last_name               | String    | Required                         |
| email                   | String    | Optional, Unique                 |
| phone                   | String    | Required, Unique                 |
| date_of_birth           | Date      | Required                         |
| gender                  | Enum      | male, female, other              |
| address                 | Text      | Optional                         |
| city                    | String    | Optional                         |
| state                   | String    | Optional                         |
| postal_code             | String    | Optional                         |
| country                 | String    | Optional                         |
| blood_type              | Enum      | O+, O-, A+, A-, B+, B-, AB+, AB- |
| allergies               | Text      | Optional                         |
| medical_history         | Text      | Optional                         |
| emergency_contact_name  | String    | Optional                         |
| emergency_contact_phone | String    | Optional                         |
| is_active               | Boolean   | Default: true                    |
| created_at              | Timestamp | Auto                             |
| updated_at              | Timestamp | Auto                             |

---

## Running Tests

### Run All Tests

```bash
php artisan test
```

### Run Feature Tests

```bash
php artisan test tests/Feature/PatientControllerTest.php
```

### Run Unit Tests

```bash
php artisan test tests/Unit/PatientServiceTest.php
```

### Run with Coverage

```bash
php artisan test --coverage
```

---

## Architecture Overview

```
Request
  ↓
PatientController (receives HTTP request)
  ↓
PatientService (handles business logic)
  ↓
PatientRepository (queries database using QueryBuilder)
  ↓
Database
  ↓
PatientRepository (returns results)
  ↓
PatientResource (transforms data)
  ↓
PatientController (returns JSON response)
  ↓
Response
```

---

## Key Technologies

✅ **Laravel 11** - PHP Framework
✅ **Spatie Query Builder** - Smart query building
✅ **Eloquent ORM** - Database abstraction
✅ **Form Requests** - Validation layer
✅ **API Resources** - Response transformation
✅ **Repository Pattern** - Data abstraction
✅ **Service Layer** - Business logic

---

## Important Files

| File                                                        | Purpose             |
| ----------------------------------------------------------- | ------------------- |
| `app/Models/Patient.php`                                    | Database model      |
| `app/Http/Controllers/PatientController.php`                | API endpoints       |
| `app/Http/Requests/PatientRequest.php`                      | Input validation    |
| `app/Http/Resources/PatientResource.php`                    | Response formatting |
| `app/Services/PatientService.php`                           | Business logic      |
| `app/Repositories/PatientRepository.php`                    | Data access         |
| `app/Repositories/Contracts/PatientRepositoryInterface.php` | Repository contract |
| `database/migrations/*_create_patients_table.php`           | Database schema     |
| `routes/api.php`                                            | API routes          |

---

## Troubleshooting

### 404 Not Found

- Check endpoint URL is correct
- Verify routes are registered in `routes/api.php`
- Run `php artisan route:list`

### 422 Validation Error

- Check required fields are provided
- Verify phone and email are unique (if creating)
- Check date format is YYYY-MM-DD

### No Data Returned

- Verify database migration ran: `php artisan migrate:status`
- Check database connection in `.env`
- Verify patient records exist in database

### Filter Not Working

- Check field name matches exactly
- Verify field is in `allowedFilters()` in repository
- Use correct format: `?filter[field]=value`

---

## Documentation

- [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) - Complete API docs
- [CLEAN_ARCHITECTURE.md](./CLEAN_ARCHITECTURE.md) - Architecture details
- [QUERY_BUILDER_GUIDE.md](./QUERY_BUILDER_GUIDE.md) - Query Builder usage
- [app/Repositories/QueryBuilderExamples.php](./app/Repositories/QueryBuilderExamples.php) - Code examples

---

## Support

For issues or questions, refer to the documentation files or the inline code comments.
