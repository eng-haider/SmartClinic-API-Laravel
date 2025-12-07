# JWT Authentication Guide

Complete guide for JWT authentication in the SmartClinic API.

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Authentication Endpoints](#authentication-endpoints)
4. [Usage Examples](#usage-examples)
5. [Token Management](#token-management)
6. [Protected Routes](#protected-routes)
7. [Error Handling](#error-handling)
8. [Best Practices](#best-practices)

---

## Overview

The SmartClinic API uses **JWT (JSON Web Tokens)** for authentication. Authentication is based on:

- **Phone Number** (primary identifier)
- **Password** (hashed with bcrypt)

### Key Features

✅ User registration with phone and password
✅ Login with phone number and password
✅ JWT token generation and validation
✅ Token refresh capability
✅ Logout (token invalidation)
✅ Role-based access (admin, doctor, nurse, receptionist, user)
✅ Account activation status

---

## Installation

### JWT Package Already Installed

The `tymon/jwt-auth` package is already installed and configured.

**Version:** 2.2.1

**Configuration:**

- JWT secret key generated in `.env` file
- Middleware registered in `bootstrap/app.php`
- User model implements `JwtSubject` interface

---

## Authentication Endpoints

### 1. Register User

**POST** `/api/auth/register`

**Request Headers:**

```
Content-Type: application/json
```

**Request Body:**

```json
{
  "name": "Ahmed Hassan",
  "phone": "201001234567",
  "email": "ahmed@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "doctor"
}
```

**Fields:**

- `name` (required) - User's full name
- `phone` (required, unique) - Mobile phone number
- `email` (optional, unique) - User email
- `password` (required, min 8) - User password
- `password_confirmation` (required) - Must match password
- `role` (optional) - admin, doctor, nurse, receptionist, user (default: user)

**Success Response (201):**

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Ahmed Hassan",
      "email": "ahmed@example.com",
      "phone": "201001234567",
      "role": "doctor",
      "is_active": true,
      "created_at": "2025-12-07 10:30:00",
      "updated_at": "2025-12-07 10:30:00"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Phone is already registered"
}
```

---

### 2. Login User

**POST** `/api/auth/login`

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

**Fields:**

- `phone` (required) - User's phone number
- `password` (required) - User's password

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
      "created_at": "2025-12-07 10:30:00",
      "updated_at": "2025-12-07 10:30:00"
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

---

### 3. Get Authenticated User

**GET** `/api/auth/me`

**Required Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
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
    "created_at": "2025-12-07 10:30:00",
    "updated_at": "2025-12-07 10:30:00"
  }
}
```

---

### 4. Refresh Token

**POST** `/api/auth/refresh`

**Required Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
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

---

### 5. Change Password

**POST** `/api/auth/change-password`

**Required Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

---

### 6. Logout User

**POST** `/api/auth/logout`

**Required Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Logout successful"
}
```

---

## Usage Examples

### Using cURL

#### Register User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahmed Hassan",
    "phone": "201001234567",
    "email": "ahmed@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "doctor"
  }'
```

#### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "201001234567",
    "password": "password123"
  }'
```

#### Get Current User

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Refresh Token

```bash
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Change Password

```bash
curl -X POST http://localhost:8000/api/auth/change-password \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "password123",
    "new_password": "newpassword456",
    "new_password_confirmation": "newpassword456"
  }'
```

#### Logout

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Token Management

### Token Structure

JWT tokens contain three parts separated by dots:

```
header.payload.signature
```

**Example:**

```
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAiLCJpYXQiOjE3NzAwMDA5NDcsImV4cCI6MTc3MDAxMzk0NywibmJmIjoxNzcwMDAwOTQ3LCJqdGkiOiIxMjM0NTY3ODkwIiwic3ViIjoiMSIsInBydiI6IiIsImVtYWlsIjoiYWhtZWRAZXhhbXBsZS5jb20iLCJyb2xlIjoiZG9jdG9yIn0.signature
```

### Token Expiration

**Default Token TTL:** 1 hour (3600 seconds)

Configure in `config/jwt.php`:

```php
'ttl' => env('JWT_TTL', 60), // in minutes
```

### Token Refresh

Tokens can be refreshed before expiration:

```bash
POST /api/auth/refresh
```

This returns a new token with extended expiration.

---

## Protected Routes

All patient routes are protected and require JWT authentication:

### Public Routes (No Auth Required)

```
POST   /api/auth/register  - Register new user
POST   /api/auth/login     - Login user
```

### Protected Routes (JWT Required)

```
GET    /api/auth/me                    - Get current user
POST   /api/auth/logout                - Logout user
POST   /api/auth/refresh               - Refresh token
POST   /api/auth/change-password       - Change password

GET    /api/patients                   - List patients
POST   /api/patients                   - Create patient
GET    /api/patients/{id}              - Get patient
PUT    /api/patients/{id}              - Update patient
DELETE /api/patients/{id}              - Delete patient
```

### Authorization Header Format

```
Authorization: Bearer {jwt_token}
```

**Example:**

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

---

## Error Handling

### Missing Authorization Header (401)

```json
{
  "success": false,
  "message": "Authorization header missing"
}
```

### Invalid or Expired Token (401)

```json
{
  "success": false,
  "message": "Token invalid or expired",
  "error": "Token has expired"
}
```

### Authentication Failed (401)

```json
{
  "success": false,
  "message": "Authentication failed",
  "error": "User not found"
}
```

### Invalid Credentials (401)

```json
{
  "success": false,
  "message": "Invalid phone number or password"
}
```

### User Inactive (401)

```json
{
  "success": false,
  "message": "User account is inactive"
}
```

### Validation Error (422)

```json
{
  "success": false,
  "message": "Phone is already registered"
}
```

---

## Best Practices

### 1. Token Storage

- Store token securely (not in localStorage in browser)
- Use httpOnly cookies for web applications
- Use Keychain (iOS) or Keystore (Android) for mobile apps

### 2. Token Expiration

- Implement token refresh before expiration
- Store refresh token separately with longer TTL
- Automatic logout when token expires

### 3. Password Security

- Enforce strong password requirements
- Hash passwords with bcrypt (already implemented)
- Never send passwords in URLs or logs
- Use HTTPS for all authentication requests

### 4. HTTPS Only

- Always use HTTPS in production
- Never transmit tokens over HTTP
- Use secure flags for cookies

### 5. CORS Configuration

Configure CORS in `config/cors.php`:

```php
'allowed_origins' => ['https://example.com'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_headers' => ['Content-Type', 'Authorization'],
```

### 6. Rate Limiting

Implement rate limiting on auth endpoints:

```php
Route::middleware('throttle:5,1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
});
```

### 7. Logging

Log authentication events:

- Successful logins
- Failed login attempts
- Token invalidation
- Password changes

---

## User Roles

Supported roles:

- `admin` - Full system access
- `doctor` - Doctor access
- `nurse` - Nurse access
- `receptionist` - Reception staff
- `user` - Regular user (default)

Extend authorization with gate/policy:

```php
Gate::define('manage-patients', function (User $user) {
    return in_array($user->role, ['admin', 'doctor']);
});
```

---

## Environment Variables

Key JWT configuration in `.env`:

```env
JWT_SECRET=your_secret_key
JWT_ALGORITHM=HS256
JWT_TTL=60
```

---

## Testing with Postman

### 1. Create Collection

Create a new Postman collection called "SmartClinic Auth"

### 2. Set Up Variables

```
{{base_url}} = http://localhost:8000/api
{{token}} = (auto-populated from login response)
```

### 3. Add Requests

**Register:**

```
POST {{base_url}}/auth/register
Body: raw JSON with registration data
```

**Login:**

```
POST {{base_url}}/auth/login
Body: raw JSON with phone and password
Tests tab: Save token to variable
  pm.environment.set("token", pm.response.json().data.token);
```

**Get Me:**

```
GET {{base_url}}/auth/me
Headers: Authorization: Bearer {{token}}
```

**Logout:**

```
POST {{base_url}}/auth/logout
Headers: Authorization: Bearer {{token}}
```

---

## Troubleshooting

### Token Expired

**Error:** "Token has expired"
**Solution:** Call `/api/auth/refresh` to get a new token

### Invalid Token

**Error:** "Token invalid or expired"
**Solution:**

- Check token is complete and not corrupted
- Verify it was copied correctly
- Check Authorization header format: `Bearer {token}`

### User Not Found

**Error:** "User not found after authentication"
**Solution:**

- Verify user exists in database
- Check user is not deleted
- Verify is_active status is true

### Phone Already Registered

**Error:** "Phone is already registered"
**Solution:**

- Use different phone number
- Or login with existing phone number

### CORS Issues

**Error:** Cross-Origin Request Blocked
**Solution:**

- Add client origin to CORS whitelist in config/cors.php
- Ensure Authorization header is allowed

---

## Architecture

JWT auth uses the same clean architecture pattern:

```
Request
  ↓
AuthController (HTTP handler)
  ↓
AuthService (business logic + JWT)
  ↓
UserRepository (data access)
  ↓
Database (User model)
  ↓
UserResource (response formatting)
  ↓
Response with JWT Token
```

### Files

- **AuthController:** `app/Http/Controllers/AuthController.php`
- **AuthService:** `app/Services/AuthService.php`
- **UserRepository:** `app/Repositories/UserRepository.php`
- **UserRepositoryInterface:** `app/Repositories/Contracts/UserRepositoryInterface.php`
- **User Model:** `app/Models/User.php`
- **UserResource:** `app/Http/Resources/UserResource.php`
- **JwtMiddleware:** `app/Http/Middleware/JwtMiddleware.php`
- **Requests:** `app/Http/Requests/LoginRequest.php`, `RegisterRequest.php`

---

## Security Considerations

✅ Passwords hashed with bcrypt
✅ Phone number unique constraint
✅ Email optional but unique if provided
✅ Role-based access control
✅ Account activation status
✅ Token invalidation on logout
✅ Protected routes with middleware
✅ HTTPS recommended in production

---

## Next Steps

1. **Test Authentication:**

   ```bash
   php artisan serve
   # Then use Postman or cURL to test endpoints
   ```

2. **Implement Authorization:**
   Add role checks to API endpoints

3. **Configure CORS:**
   Update `config/cors.php` for your frontend domain

4. **Set Up Rate Limiting:**
   Protect auth endpoints from brute force attacks

5. **Add Logging:**
   Log authentication events for security auditing

---

## Support

For more information:

- JWT Documentation: https://jwt.io
- tymon/jwt-auth: https://jwt-auth.readthedocs.io/
- Laravel Authentication: https://laravel.com/docs/authentication
