# Authentication API Documentation

## Overview

Complete authentication system using JWT tokens with phone number as the primary identifier.

**Base URL:** `http://localhost:8000/api/auth`

---

## Endpoints

### 1. Register User

Register a new user account.

**Endpoint:** `POST /api/auth/register`

**Authentication:** Not Required

**Request Headers:**

```
Content-Type: application/json
```

**Request Body:**

```json
{
  "name": "Dr. Ahmed Hassan",
  "phone": "201001234567",
  "email": "ahmed@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "clinic_name": "Smart Dental Clinic",
  "clinic_address": "123 Main Street, Cairo, Egypt",
  "clinic_phone": "201001234567",
  "clinic_email": "info@smartdental.com"
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | User's full name |
| phone | string | Yes | Unique phone number |
| email | string | No | Unique email address |
| password | string | Yes | Password (min 8 characters) |
| password_confirmation | string | Yes | Must match password |
| clinic_name | string | Yes | Clinic/Practice name |
| clinic_address | string | Yes | Clinic physical address |
| clinic_phone | string | No | Clinic contact phone |
| clinic_email | string | No | Clinic contact email |

**Note:** User role is automatically set to `clinic_super_doctor` upon registration.

**Success Response (201):**

```json
{
  "success": true,
  "message": "User and clinic registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Dr. Ahmed Hassan",
      "email": "ahmed@example.com",
      "phone": "201001234567",
      "role": "clinic_super_doctor",
      "is_active": true,
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z"
    },
    "clinic": {
      "id": 1,
      "name": "Smart Dental Clinic",
      "address": "123 Main Street, Cairo, Egypt",
      "phone": "201001234567",
      "email": "info@smartdental.com"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
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
    "email": ["The email has already been taken."]
  }
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Dr. Ahmed Hassan",
    "phone": "201001234567",
    "email": "ahmed@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "clinic_name": "Smart Dental Clinic",
    "clinic_address": "123 Main Street, Cairo, Egypt",
    "clinic_phone": "201001234567",
    "clinic_email": "info@smartdental.com"
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/auth/register`
- Body (raw JSON): See request body above
- Tests Script:

```javascript
if (pm.response.code === 201) {
  const response = pm.response.json();
  pm.environment.set("token", response.data.token);
  pm.environment.set("user_id", response.data.user.id);
}
```

---

### 2. Login

Authenticate user and receive JWT token.

**Endpoint:** `POST /api/auth/login`

**Authentication:** Not Required

**Request Headers:**

```
Content-Type: application/json
```

**Request Body:**

```json
{
  "phone": "201001234567",
  "password": "password123"
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| phone | string | Yes | User's phone number |
| password | string | Yes | User's password |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Ahmed Hassan",
      "email": "ahmed@example.com",
      "phone": "201001234567",
      "role": "doctor",
      "is_active": true,
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

**Error Response (401):**

```json
{
  "success": false,
  "message": "Invalid phone number or password"
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "201001234567",
    "password": "password123"
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/auth/login`
- Body (raw JSON): See request body above
- Tests Script:

```javascript
if (pm.response.code === 200) {
  const response = pm.response.json();
  pm.environment.set("token", response.data.token);
  pm.environment.set("user_id", response.data.user.id);
  pm.environment.set("user_role", response.data.user.role);
}
```

---

### 3. Get Current User

Get authenticated user details.

**Endpoint:** `GET /api/auth/me`

**Authentication:** Required (Bearer Token)

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "id": 1,
    "name": "Ahmed Hassan",
    "email": "ahmed@example.com",
    "phone": "201001234567",
    "role": "doctor",
    "is_active": true,
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (401):**

```json
{
  "success": false,
  "message": "Unauthenticated",
  "error": "Token has expired"
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/auth/me`
- Authorization: Bearer Token (automatically added from collection)

---

### 4. Refresh Token

Get a new JWT token before the current one expires.

**Endpoint:** `POST /api/auth/refresh`

**Authentication:** Required (Bearer Token)

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

**Error Response (401):**

```json
{
  "success": false,
  "message": "Token invalid or expired"
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/auth/refresh`
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 200) {
  const response = pm.response.json();
  pm.environment.set("token", response.data.token);
}
```

---

### 5. Change Password

Change the authenticated user's password.

**Endpoint:** `POST /api/auth/change-password`

**Authentication:** Required (Bearer Token)

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
  "current_password": "password123",
  "new_password": "newpassword456",
  "new_password_confirmation": "newpassword456"
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| current_password | string | Yes | Current password |
| new_password | string | Yes | New password (min 8 characters) |
| new_password_confirmation | string | Yes | Must match new_password |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Current password is incorrect"
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/auth/change-password \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "current_password": "password123",
    "new_password": "newpassword456",
    "new_password_confirmation": "newpassword456"
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/auth/change-password`
- Body (raw JSON): See request body above
- Authorization: Bearer Token

---

### 6. Logout

Invalidate the current JWT token.

**Endpoint:** `POST /api/auth/logout`

**Authentication:** Required (Bearer Token)

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Logout successful"
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/auth/logout`
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 200) {
  pm.environment.unset("token");
  pm.environment.unset("user_id");
}
```

---

## Token Management

### Token Configuration

- **Default TTL:** 60 minutes
- **Algorithm:** HS256
- **Refresh TTL:** 20160 minutes (14 days)

### Token Storage Best Practices

1. **Web Apps:** Use httpOnly cookies
2. **Mobile Apps:** Use Keychain (iOS) or Keystore (Android)
3. **SPAs:** Use memory or sessionStorage (not localStorage)

### Token Refresh Strategy

```javascript
// Auto-refresh before expiration
setInterval(async () => {
  try {
    const response = await refreshToken();
    updateToken(response.data.token);
  } catch (error) {
    // Token expired, redirect to login
    redirectToLogin();
  }
}, 50 * 60 * 1000); // 50 minutes
```

---

## Error Codes

| Status Code | Description           | Common Causes                      |
| ----------- | --------------------- | ---------------------------------- |
| 200         | OK                    | Successful request                 |
| 201         | Created               | User registered successfully       |
| 401         | Unauthorized          | Invalid credentials, expired token |
| 422         | Unprocessable Entity  | Validation failed                  |
| 500         | Internal Server Error | Server error                       |

---

## Postman Collection Setup

### Environment Variables

```json
{
  "base_url": "http://localhost:8000/api",
  "token": "",
  "user_id": "",
  "user_role": ""
}
```

### Collection Pre-request Script

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

### Collection Test Script

```javascript
// Auto-save token from responses
if (pm.response.code === 200 || pm.response.code === 201) {
  const response = pm.response.json();
  if (response.data && response.data.token) {
    pm.environment.set("token", response.data.token);
  }
}
```

---

## Testing Workflow

### 1. Register New User

```
POST /auth/register
→ Save token from response
```

### 2. Login

```
POST /auth/login
→ Save token from response
```

### 3. Get Current User

```
GET /auth/me
→ Verify user details
```

### 4. Test Protected Routes

```
Use saved token for all API requests
```

### 5. Refresh Token

```
POST /auth/refresh
→ Update saved token
```

### 6. Change Password

```
POST /auth/change-password
→ Update password
```

### 7. Logout

```
POST /auth/logout
→ Clear saved token
```

---

## Security Considerations

✅ Passwords hashed with bcrypt  
✅ Phone numbers must be unique  
✅ Tokens expire after 60 minutes  
✅ Refresh tokens available  
✅ Account activation status checked  
✅ HTTPS recommended in production  
✅ Rate limiting recommended on login/register

---

## User Roles

| Role         | Description          | Access Level              |
| ------------ | -------------------- | ------------------------- |
| admin        | System administrator | Full access               |
| doctor       | Medical doctor       | Patient management, cases |
| nurse        | Nursing staff        | Limited patient access    |
| receptionist | Front desk staff     | Appointments, billing     |
| user         | Default role         | Basic access              |

---

## Common Integration Patterns

### React Example

```javascript
import axios from "axios";

const authService = {
  async login(phone, password) {
    const response = await axios.post("/auth/login", {
      phone,
      password,
    });
    localStorage.setItem("token", response.data.data.token);
    return response.data;
  },

  async logout() {
    await axios.post("/auth/logout");
    localStorage.removeItem("token");
  },
};
```

### Vue Example

```javascript
export default {
  methods: {
    async login() {
      try {
        const { data } = await this.$axios.post("/auth/login", {
          phone: this.phone,
          password: this.password,
        });
        this.$store.commit("setToken", data.data.token);
        this.$router.push("/dashboard");
      } catch (error) {
        this.error = error.response.data.message;
      }
    },
  },
};
```

---

**Last Updated:** January 15, 2026  
**API Version:** 1.0
