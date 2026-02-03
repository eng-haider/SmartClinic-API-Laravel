# Authentication Flow - نظام المصادقة

## Overview - نظرة عامة

SmartClinic uses a **two-step authentication** system for multi-tenant support:

```
┌─────────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION FLOW                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Step 1: Check Credentials (Central DB)                        │
│  ────────────────────────────────────────                      │
│  POST /api/auth/check-credentials                              │
│  Body: { phone, password }                                     │
│  Returns: { tenant_id, clinic_name, user_name }                │
│                                                                 │
│                         ↓                                       │
│                                                                 │
│  Step 2: Login with Tenant (Tenant DB)                         │
│  ─────────────────────────────────────                         │
│  POST /api/tenant/auth/login                                   │
│  Header: X-Tenant-ID: {tenant_id}                              │
│  Body: { phone, password }                                     │
│  Returns: { user, token }                                      │
│                                                                 │
│                         ↓                                       │
│                                                                 │
│  All Future Requests                                           │
│  ───────────────────                                           │
│  Header: X-Tenant-ID: {tenant_id}                              │
│  Header: Authorization: Bearer {token}                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Step 1: Check Credentials - التحقق من بيانات الدخول

**Purpose:** Verify user credentials and get the `tenant_id` (clinic database ID)

```http
POST /api/auth/check-credentials
Content-Type: application/json
```

### Request Body:

```json
{
  "phone": "07901234567",
  "password": "password123"
}
```

### Success Response (200):

```json
{
  "success": true,
  "message": "Credentials verified. Please proceed with tenant login.",
  "message_ar": "تم التحقق من بيانات الدخول. يرجى المتابعة.",
  "data": {
    "tenant_id": "clinic_123",
    "clinic_name": "عيادة النور",
    "user_name": "د. أحمد"
  }
}
```

### Error Response (401):

```json
{
  "success": false,
  "message": "Invalid phone number or password",
  "message_ar": "بيانات الدخول غير صحيحة"
}
```

---

## Step 2: Login with Tenant Context - تسجيل الدخول

**Purpose:** Authenticate user in the tenant database and get JWT token

```http
POST /api/tenant/auth/login
Content-Type: application/json
X-Tenant-ID: clinic_123
```

### Request Headers:

| Header         | Value              | Description               |
| -------------- | ------------------ | ------------------------- |
| `X-Tenant-ID`  | `clinic_123`       | The tenant_id from Step 1 |
| `Content-Type` | `application/json` | Required                  |

### Request Body:

```json
{
  "phone": "07901234567",
  "password": "password123"
}
```

### Success Response (200):

```json
{
  "success": true,
  "message": "Login successful",
  "message_ar": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "د. أحمد",
      "phone": "07901234567",
      "email": "ahmed@clinic.com",
      "roles": ["clinic_super_doctor"]
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

### Error Response (401):

```json
{
  "success": false,
  "message": "Invalid phone number or password"
}
```

---

## Using the Token - استخدام التوكن

After successful login, include both headers in ALL subsequent requests:

```http
GET /api/tenant/patients
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
X-Tenant-ID: clinic_123
```

### Example: Get All Patients

```bash
curl -X GET "http://localhost:8000/api/tenant/patients" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "X-Tenant-ID: clinic_123"
```

---

## Complete Flow Example (cURL)

### Step 1: Check Credentials

```bash
curl -X POST "http://localhost:8000/api/auth/check-credentials" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07901234567",
    "password": "password123"
  }'
```

**Response:**

```json
{
  "success": true,
  "data": {
    "tenant_id": "clinic_123",
    "clinic_name": "عيادة النور",
    "user_name": "د. أحمد"
  }
}
```

### Step 2: Login with Tenant ID

```bash
curl -X POST "http://localhost:8000/api/tenant/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: clinic_123" \
  -d '{
    "phone": "07901234567",
    "password": "password123"
  }'
```

**Response:**

```json
{
    "success": true,
    "data": {
        "user": { "id": 1, "name": "د. أحمد", ... },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

### Step 3: Use Token for API Calls

```bash
curl -X GET "http://localhost:8000/api/tenant/patients" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "X-Tenant-ID: clinic_123"
```

---

## Frontend Implementation Example

### JavaScript/TypeScript

```javascript
// Store these after login
let tenantId = null;
let authToken = null;

// Step 1: Check credentials
async function checkCredentials(phone, password) {
  const response = await fetch("/api/auth/check-credentials", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ phone, password }),
  });

  const data = await response.json();
  if (data.success) {
    tenantId = data.data.tenant_id;
    // Store tenantId in localStorage/sessionStorage
    localStorage.setItem("tenant_id", tenantId);
  }
  return data;
}

// Step 2: Login with tenant
async function login(phone, password) {
  const response = await fetch("/api/tenant/auth/login", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Tenant-ID": tenantId,
    },
    body: JSON.stringify({ phone, password }),
  });

  const data = await response.json();
  if (data.success) {
    authToken = data.data.token;
    // Store token in localStorage/sessionStorage
    localStorage.setItem("auth_token", authToken);
  }
  return data;
}

// API request helper
async function apiRequest(endpoint, options = {}) {
  const headers = {
    "Content-Type": "application/json",
    "X-Tenant-ID": localStorage.getItem("tenant_id"),
    Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
    ...options.headers,
  };

  return fetch(`/api/tenant${endpoint}`, { ...options, headers });
}

// Usage
const patients = await apiRequest("/patients");
```

### Flutter/Dart

```dart
class AuthService {
  String? tenantId;
  String? authToken;

  // Step 1: Check credentials
  Future<Map> checkCredentials(String phone, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/auth/check-credentials'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'phone': phone, 'password': password}),
    );

    final data = jsonDecode(response.body);
    if (data['success']) {
      tenantId = data['data']['tenant_id'];
      await storage.write(key: 'tenant_id', value: tenantId);
    }
    return data;
  }

  // Step 2: Login
  Future<Map> login(String phone, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/tenant/auth/login'),
      headers: {
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId!,
      },
      body: jsonEncode({'phone': phone, 'password': password}),
    );

    final data = jsonDecode(response.body);
    if (data['success']) {
      authToken = data['data']['token'];
      await storage.write(key: 'auth_token', value: authToken);
    }
    return data;
  }
}
```

---

## Error Codes

| Status | Message                                | Description         |
| ------ | -------------------------------------- | ------------------- |
| 401    | Invalid phone number or password       | Wrong credentials   |
| 401    | User account is inactive               | Account disabled    |
| 401    | User is not associated with any clinic | No clinic found     |
| 400    | Tenant not found                       | Invalid X-Tenant-ID |

---

## Security Notes

1. **Token Expiration:** JWT tokens expire after the configured time (default: 60 minutes)
2. **Refresh Token:** Use `POST /api/tenant/auth/refresh` to get a new token
3. **Logout:** Use `POST /api/tenant/auth/logout` to invalidate the token
4. **Always use HTTPS in production**
