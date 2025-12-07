# Patient API Documentation

## Base URL

```
http://localhost:8000/api
```

## Endpoints

### 1. Get All Patients

**GET** `/patients`

**Query Parameters (using Query Builder):**

- `filter[gender]` (string) - Filter by gender (male, female, other)
- `filter[blood_type]` (string) - Filter by blood type (O+, O-, A+, A-, B+, B-, AB+, AB-)
- `filter[is_active]` (boolean) - Filter by active status (0 or 1)
- `filter[city]` (string) - Filter by city
- `filter[email]` (string) - Filter by email
- `filter[phone]` (string) - Filter by phone
- `search` (string) - Search by first name, last name, email, or phone
- `sort` (string) - Sort field with order (use `-` for desc, e.g., `-created_at` or `first_name`)
- `per_page` (integer) - Records per page (default: 15)
- `page` (integer) - Page number (default: 1)

**Example Requests:**

Get all male patients:

```bash
curl -X GET "http://localhost:8000/api/patients?filter[gender]=male" \
  -H "Content-Type: application/json"
```

Get active patients sorted by creation date (newest first):

```bash
curl -X GET "http://localhost:8000/api/patients?filter[is_active]=1&sort=-created_at" \
  -H "Content-Type: application/json"
```

Get patients in Cairo with O+ blood type:

```bash
curl -X GET "http://localhost:8000/api/patients?filter[city]=Cairo&filter[blood_type]=O+" \
  -H "Content-Type: application/json"
```

Search with filters:

```bash
curl -X GET "http://localhost:8000/api/patients?search=john&filter[gender]=male&sort=-created_at&per_page=20" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "success": true,
  "message": "Patients retrieved successfully",
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "01001234567",
      "date_of_birth": "1990-05-15",
      "gender": "male",
      "address": "123 Main St",
      "city": "Cairo",
      "state": "Cairo",
      "postal_code": "12345",
      "country": "Egypt",
      "blood_type": "O+",
      "allergies": "Penicillin",
      "medical_history": "Asthma",
      "emergency_contact_name": "Jane Doe",
      "emergency_contact_phone": "01098765432",
      "is_active": true,
      "created_at": "2025-12-07 10:30:00",
      "updated_at": "2025-12-07 10:30:00"
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 20,
    "current_page": 1,
    "last_page": 3,
    "from": 1,
    "to": 20
  }
}
```

---

### 2. Get Single Patient

**GET** `/patients/{id}`

**Example Request:**

```bash
curl -X GET "http://localhost:8000/api/patients/1" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "success": true,
  "message": "Patient retrieved successfully",
  "data": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "01001234567",
    "date_of_birth": "1990-05-15",
    "gender": "male",
    "address": "123 Main St",
    "city": "Cairo",
    "state": "Cairo",
    "postal_code": "12345",
    "country": "Egypt",
    "blood_type": "O+",
    "allergies": "Penicillin",
    "medical_history": "Asthma",
    "emergency_contact_name": "Jane Doe",
    "emergency_contact_phone": "01098765432",
    "is_active": true,
    "created_at": "2025-12-07 10:30:00",
    "updated_at": "2025-12-07 10:30:00"
  }
}
```

---

### 3. Create Patient

**POST** `/patients`

**Request Body:**

```json
{
  "first_name": "Ahmed",
  "last_name": "Hassan",
  "email": "ahmed@example.com",
  "phone": "01001234567",
  "date_of_birth": "1990-05-15",
  "gender": "male",
  "address": "123 Main St",
  "city": "Cairo",
  "state": "Cairo",
  "postal_code": "12345",
  "country": "Egypt",
  "blood_type": "O+",
  "allergies": "Penicillin",
  "medical_history": "Asthma",
  "emergency_contact_name": "Jane Doe",
  "emergency_contact_phone": "01098765432"
}
```

**Required Fields:**

- `first_name` (string)
- `last_name` (string)
- `phone` (string, unique)
- `date_of_birth` (date, before today)
- `gender` (string: male, female, other)

**Example Request:**

```bash
curl -X POST "http://localhost:8000/api/patients" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ahmed",
    "last_name": "Hassan",
    "phone": "01001234567",
    "date_of_birth": "1990-05-15",
    "gender": "male",
    "email": "ahmed@example.com"
  }'
```

**Response (201 Created):**

```json
{
  "success": true,
  "message": "Patient created successfully",
  "data": {
    "id": 2,
    "first_name": "Ahmed",
    "last_name": "Hassan",
    ...
  }
}
```

---

### 4. Update Patient

**PUT** `/patients/{id}`

**Request Body:** (Same as create, but fields are optional)

```json
{
  "first_name": "Ahmed Updated",
  "phone": "01001234567"
}
```

**Example Request:**

```bash
curl -X PUT "http://localhost:8000/api/patients/1" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ahmed Updated"
  }'
```

**Response:**

```json
{
  "success": true,
  "message": "Patient updated successfully",
  "data": {
    "id": 1,
    "first_name": "Ahmed Updated",
    ...
  }
}
```

---

### 5. Delete Patient

**DELETE** `/patients/{id}`

**Example Request:**

```bash
curl -X DELETE "http://localhost:8000/api/patients/1" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "success": true,
  "message": "Patient deleted successfully"
}
```

---

### 6. Search Patient by Phone

**GET** `/patients/search/phone/{phone}`

**Example Request:**

```bash
curl -X GET "http://localhost:8000/api/patients/search/phone/01001234567" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "success": true,
  "message": "Patient found",
  "data": {
    "id": 1,
    ...
  }
}
```

---

### 7. Search Patient by Email

**GET** `/patients/search/email/{email}`

**Example Request:**

```bash
curl -X GET "http://localhost:8000/api/patients/search/email/john@example.com" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "success": true,
  "message": "Patient found",
  "data": {
    "id": 1,
    ...
  }
}
```

---

## Error Responses

### 404 Not Found

```json
{
  "success": false,
  "message": "Patient not found"
}
```

### 422 Unprocessable Entity (Validation Error)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": ["This phone number is already registered"],
    "email": ["This email is already registered"]
  }
}
```

### 500 Internal Server Error

```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## Status Codes

| Code | Description           |
| ---- | --------------------- |
| 200  | Success               |
| 201  | Created               |
| 400  | Bad Request           |
| 404  | Not Found             |
| 422  | Validation Error      |
| 500  | Internal Server Error |

---

## Example Usage with Postman

### Collection Setup

1. Create a new Postman collection
2. Add variable: `base_url` = `http://localhost:8000/api`
3. Create requests using `{{base_url}}/patients`

### Sample Workflow

1. **Create Patient** (POST) - Get patient ID
2. **Get Patient** (GET) - Verify creation
3. **Update Patient** (PUT) - Modify data
4. **Search Patient** (GET) - Search by phone
5. **List All Patients** (GET) - Check all patients
6. **Delete Patient** (DELETE) - Remove patient

---

## Filtering Examples

### Filter by single field

```bash
GET /patients?filter[gender]=male
GET /patients?filter[blood_type]=O+
GET /patients?filter[city]=Cairo
GET /patients?filter[is_active]=1
```

### Filter by multiple fields (AND logic)

```bash
GET /patients?filter[gender]=male&filter[blood_type]=O+
GET /patients?filter[city]=Cairo&filter[is_active]=1&filter[gender]=female
```

### Sorting examples

```bash
GET /patients?sort=first_name                    # Sort ascending
GET /patients?sort=-first_name                   # Sort descending
GET /patients?sort=-created_at                   # Newest first
GET /patients?sort=-created_at,first_name        # Multiple sorts
```

### Search with filters

```bash
GET /patients?search=john&filter[gender]=male&sort=-created_at
GET /patients?search=ahmed&filter[city]=Cairo&filter[is_active]=1
```

### Pagination with filters and sorting

```bash
GET /patients?filter[gender]=female&sort=first_name&per_page=20&page=2
GET /patients?filter[is_active]=1&sort=-created_at&per_page=15&page=3
```

### Combined complex query

```bash
GET /patients?search=cairo&filter[gender]=male&filter[blood_type]=O+&filter[is_active]=1&sort=-created_at&per_page=20&page=1
```

---

## Notes

- All dates should be in `YYYY-MM-DD` format
- Phone numbers should be unique across the system
- Email is optional but must be unique if provided
- Date of birth must be in the past
- All timestamps are in UTC timezone
