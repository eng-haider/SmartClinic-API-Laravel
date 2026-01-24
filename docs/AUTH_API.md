# Authentication API Documentation

## Overview

Complete authentication system using JWT tokens with phone number as the primary identifier.

**Base URL:** `http://localhost:8000/api/auth`

---

## 🚀 Quick Start for Frontend Developers

### Authentication Flow

```
1. User Registration → Create account + clinic → Receive JWT token
2. User Login → Validate credentials → Receive JWT token
3. Store Token → Save in localStorage/sessionStorage/cookies
4. Include Token → Add to Authorization header for all API requests
5. Token Refresh → When token expires, refresh without re-login
6. Logout → Invalidate token on server
```

### Complete Registration & Authorization Demo

#### Step 1: Register New User (Creates Clinic Automatically)

**What Happens:**

- User registers with their information
- System automatically creates a clinic for them
- User is assigned the `clinic_super_doctor` role (owner)
- JWT token is generated and returned
- User is immediately logged in

**Request Example:**

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Dr. Ahmed Hassan",
    "phone": "201001234567",
    "email": "ahmed@smartdental.com",
    "password": "password123",
    "password_confirmation": "password123",
    "clinic_name": "Smart Dental Clinic",
    "clinic_address": "123 Main Street, Cairo, Egypt",
    "clinic_phone": "201001234567",
    "clinic_email": "info@smartdental.com"
  }'
```

**Success Response (201 Created):**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Dr. Ahmed Hassan",
      "phone": "201001234567",
      "email": "ahmed@smartdental.com",
      "clinic_id": 1,
      "is_active": true,
      "created_at": "2026-01-17T10:30:00.000000Z",
      "updated_at": "2026-01-17T10:30:00.000000Z",
      "roles": ["clinic_super_doctor"],
      "permissions": [
        "view_patients",
        "create_patients",
        "edit_patients",
        "delete_patients",
        "view_cases",
        "create_cases",
        "edit_cases",
        "delete_cases",
        "view_bills",
        "create_bills",
        "edit_bills",
        "delete_bills",
        "manage_clinic_settings",
        "manage_users"
      ]
    },
    "clinic": {
      "id": 1,
      "name": "Smart Dental Clinic",
      "address": "123 Main Street, Cairo, Egypt",
      "phone": "201001234567",
      "email": "info@smartdental.com",
      "is_active": true,
      "created_at": "2026-01-17T10:30:00.000000Z",
      "updated_at": "2026-01-17T10:30:00.000000Z"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL2F1dGgvcmVnaXN0ZXIiLCJpYXQiOjE3MDU0ODU4MDAsImV4cCI6MTcwNTQ4OTQwMCwibmJmIjoxNzA1NDg1ODAwLCJqdGkiOiJhYmMxMjM0NWRlZjY3ODkwIiwic3ViIjoiMSIsInBydiI6IjIzYmQ1Yzg5NDlmNjAwYWRiMzllNzAxYzQwMDg3MmRiN2E1OTc2ZjcifQ.abc123def456ghi789jkl012mno345pqr678stu901vwx234yz",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "message": "User registered successfully",
  "success": true
}
```

**Frontend Implementation:**

```javascript
// React/JavaScript Example
async function registerUser(userData) {
  try {
    const response = await fetch("http://localhost:8000/api/auth/register", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        name: userData.name,
        phone: userData.phone,
        email: userData.email,
        password: userData.password,
        password_confirmation: userData.passwordConfirmation,
        clinic_name: userData.clinicName,
        clinic_address: userData.clinicAddress,
        clinic_phone: userData.clinicPhone,
        clinic_email: userData.clinicEmail,
      }),
    });

    const result = await response.json();

    if (response.ok) {
      // Save token to localStorage
      localStorage.setItem("auth_token", result.data.token);
      localStorage.setItem("user", JSON.stringify(result.data.user));
      localStorage.setItem("clinic", JSON.stringify(result.data.clinic));

      // Token expires in 1 hour (3600 seconds)
      const expiresAt = Date.now() + result.data.expires_in * 1000;
      localStorage.setItem("token_expires_at", expiresAt);

      return {
        success: true,
        user: result.data.user,
        clinic: result.data.clinic,
        token: result.data.token,
      };
    } else {
      return {
        success: false,
        errors: result.errors || result.message,
      };
    }
  } catch (error) {
    console.error("Registration error:", error);
    return {
      success: false,
      errors: "Network error occurred",
    };
  }
}

// Usage in React Component
const handleRegister = async (formData) => {
  const result = await registerUser({
    name: formData.name,
    phone: formData.phone,
    email: formData.email,
    password: formData.password,
    passwordConfirmation: formData.passwordConfirmation,
    clinicName: formData.clinicName,
    clinicAddress: formData.clinicAddress,
    clinicPhone: formData.clinicPhone,
    clinicEmail: formData.clinicEmail,
  });

  if (result.success) {
    // Redirect to dashboard
    window.location.href = "/dashboard";
  } else {
    // Show errors
    setErrors(result.errors);
  }
};
```

---

#### Step 2: Login Existing User

**Request Example:**

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "201001234567",
    "password": "password123"
  }'
```

**Success Response (200 OK):**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Dr. Ahmed Hassan",
      "phone": "201001234567",
      "email": "ahmed@smartdental.com",
      "clinic_id": 1,
      "is_active": true,
      "created_at": "2026-01-17T10:30:00.000000Z",
      "updated_at": "2026-01-17T10:30:00.000000Z",
      "roles": ["clinic_super_doctor"],
      "permissions": [
        "view_patients",
        "create_patients",
        "edit_patients",
        "delete_patients",
        "manage_clinic_settings"
      ]
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "message": "Login successful",
  "success": true
}
```

**Frontend Implementation:**

```javascript
// React/JavaScript Example
async function loginUser(phone, password) {
  try {
    const response = await fetch("http://localhost:8000/api/auth/login", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ phone, password }),
    });

    const result = await response.json();

    if (response.ok) {
      // Save token and user info
      localStorage.setItem("auth_token", result.data.token);
      localStorage.setItem("user", JSON.stringify(result.data.user));

      const expiresAt = Date.now() + result.data.expires_in * 1000;
      localStorage.setItem("token_expires_at", expiresAt);

      return { success: true, user: result.data.user };
    } else {
      return { success: false, errors: result.errors || result.message };
    }
  } catch (error) {
    console.error("Login error:", error);
    return { success: false, errors: "Network error occurred" };
  }
}
```

---

#### Step 3: Using the Token for Authorization

**All Protected API Requests Must Include:**

```
Authorization: Bearer {your_jwt_token}
```

**Example Protected Request:**

```bash
curl -X GET http://localhost:8000/api/patients \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json"
```

**Frontend Implementation - Axios Interceptor:**

```javascript
// React with Axios Example
import axios from "axios";

// Create axios instance
const api = axios.create({
  baseURL: "http://localhost:8000/api",
  headers: {
    "Content-Type": "application/json",
  },
});

// Add token to every request automatically
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

// Handle unauthorized responses (token expired)
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
      window.location.href = "/login";
    }
    return Promise.reject(error);
  }
);

// Usage
const fetchPatients = async () => {
  try {
    const response = await api.get("/patients");
    return response.data;
  } catch (error) {
    console.error("Error fetching patients:", error);
    throw error;
  }
};

export default api;
```

**Frontend Implementation - Fetch with Helper:**

```javascript
// Vanilla JavaScript / React without Axios
const authFetch = async (url, options = {}) => {
  const token = localStorage.getItem("auth_token");

  const headers = {
    "Content-Type": "application/json",
    ...options.headers,
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  try {
    const response = await fetch(`http://localhost:8000/api${url}`, {
      ...options,
      headers,
    });

    if (response.status === 401) {
      // Token expired or invalid
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
      window.location.href = "/login";
      throw new Error("Unauthorized");
    }

    return await response.json();
  } catch (error) {
    console.error("API Error:", error);
    throw error;
  }
};

// Usage
const getPatients = async () => {
  const data = await authFetch("/patients");
  return data;
};

const createPatient = async (patientData) => {
  const data = await authFetch("/patients", {
    method: "POST",
    body: JSON.stringify(patientData),
  });
  return data;
};
```

---

#### Step 4: Check Token Validity & Get User Info

**Request:**

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "name": "Dr. Ahmed Hassan",
    "phone": "201001234567",
    "email": "ahmed@smartdental.com",
    "clinic_id": 1,
    "is_active": true,
    "created_at": "2026-01-17T10:30:00.000000Z",
    "updated_at": "2026-01-17T10:30:00.000000Z",
    "roles": ["clinic_super_doctor"],
    "permissions": ["view_patients", "create_patients", "edit_patients"]
  },
  "success": true
}
```

---

#### Step 5: Refresh Token (Before Expiration)

**Request:**

```bash
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

**Response:**

```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "message": "Token refreshed successfully",
  "success": true
}
```

**Frontend Auto-Refresh Implementation:**

```javascript
// Check and refresh token before it expires
const checkAndRefreshToken = async () => {
  const token = localStorage.getItem("auth_token");
  const expiresAt = localStorage.getItem("token_expires_at");

  if (!token || !expiresAt) return;

  const now = Date.now();
  const timeUntilExpiry = parseInt(expiresAt) - now;

  // Refresh if less than 5 minutes remaining
  if (timeUntilExpiry < 5 * 60 * 1000) {
    try {
      const response = await fetch("http://localhost:8000/api/auth/refresh", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      const result = await response.json();

      if (response.ok) {
        localStorage.setItem("auth_token", result.data.token);
        const newExpiresAt = Date.now() + result.data.expires_in * 1000;
        localStorage.setItem("token_expires_at", newExpiresAt);
      }
    } catch (error) {
      console.error("Token refresh failed:", error);
    }
  }
};

// Run every 1 minute
setInterval(checkAndRefreshToken, 60000);

// Or run on app initialization
checkAndRefreshToken();
```

---

#### Step 6: Logout

**Request:**

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

**Response:**

```json
{
  "message": "Successfully logged out",
  "success": true
}
```

**Frontend Implementation:**

```javascript
const logout = async () => {
  const token = localStorage.getItem("auth_token");

  try {
    await fetch("http://localhost:8000/api/auth/logout", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    });
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    // Clear local storage regardless of API response
    localStorage.removeItem("auth_token");
    localStorage.removeItem("user");
    localStorage.removeItem("clinic");
    localStorage.removeItem("token_expires_at");
    window.location.href = "/login";
  }
};
```

---

### Error Response Format

All errors follow this structure:

```json
{
  "message": "Validation errors occurred",
  "errors": {
    "phone": ["The phone has already been taken."],
    "password": ["The password confirmation does not match."]
  },
  "success": false
}
```

**Common HTTP Status Codes:**

| Status Code | Meaning              | Example                      |
| ----------- | -------------------- | ---------------------------- |
| 200         | OK                   | Login successful             |
| 201         | Created              | Registration successful      |
| 400         | Bad Request          | Invalid data format          |
| 401         | Unauthorized         | Invalid credentials or token |
| 422         | Unprocessable Entity | Validation errors            |
| 429         | Too Many Requests    | Rate limit exceeded          |
| 500         | Server Error         | Internal server error        |

---

### Complete React Auth Context Example

```javascript
// contexts/AuthContext.js
import React, { createContext, useState, useContext, useEffect } from "react";
import axios from "axios";

const AuthContext = createContext();

export const useAuth = () => useContext(AuthContext);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState(localStorage.getItem("auth_token"));

  const api = axios.create({
    baseURL: "http://localhost:8000/api",
    headers: { "Content-Type": "application/json" },
  });

  // Add token to requests
  api.interceptors.request.use((config) => {
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  });

  // Handle 401 errors
  api.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.response?.status === 401) {
        logout();
      }
      return Promise.reject(error);
    }
  );

  useEffect(() => {
    if (token) {
      loadUser();
    } else {
      setLoading(false);
    }
  }, [token]);

  const loadUser = async () => {
    try {
      const { data } = await api.get("/auth/me");
      setUser(data.data);
    } catch (error) {
      console.error("Load user error:", error);
      logout();
    } finally {
      setLoading(false);
    }
  };

  const register = async (userData) => {
    try {
      const { data } = await api.post("/auth/register", userData);
      const newToken = data.data.token;

      setToken(newToken);
      setUser(data.data.user);
      localStorage.setItem("auth_token", newToken);
      localStorage.setItem("user", JSON.stringify(data.data.user));
      localStorage.setItem("clinic", JSON.stringify(data.data.clinic));

      return { success: true, data: data.data };
    } catch (error) {
      return {
        success: false,
        errors: error.response?.data?.errors || error.response?.data?.message,
      };
    }
  };

  const login = async (phone, password) => {
    try {
      const { data } = await api.post("/auth/login", { phone, password });
      const newToken = data.data.token;

      setToken(newToken);
      setUser(data.data.user);
      localStorage.setItem("auth_token", newToken);
      localStorage.setItem("user", JSON.stringify(data.data.user));

      return { success: true, user: data.data.user };
    } catch (error) {
      return {
        success: false,
        errors: error.response?.data?.errors || error.response?.data?.message,
      };
    }
  };

  const logout = async () => {
    try {
      await api.post("/auth/logout");
    } catch (error) {
      console.error("Logout error:", error);
    } finally {
      setToken(null);
      setUser(null);
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
      localStorage.removeItem("clinic");
    }
  };

  const value = {
    user,
    token,
    loading,
    login,
    register,
    logout,
    api, // Export configured axios instance
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

// Usage in App.js
import { AuthProvider } from "./contexts/AuthContext";

function App() {
  return (
    <AuthProvider>
      <YourAppRoutes />
    </AuthProvider>
  );
}

// Usage in components
import { useAuth } from "./contexts/AuthContext";

function LoginPage() {
  const { login } = useAuth();

  const handleSubmit = async (e) => {
    e.preventDefault();
    const result = await login(phone, password);
    if (result.success) {
      navigate("/dashboard");
    } else {
      setErrors(result.errors);
    }
  };
}
```

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
