# Image API - Testing Guide

## Problem

Image with ID 5 exists in database but returns 404 when accessed via API.

## Root Cause

The images API is in **tenant routes** (multi-tenant architecture), meaning:

- You **MUST** provide `X-Tenant-ID` header
- Without it, the system can't switch to the correct tenant database
- Query runs against wrong database → 404

## Solution: Test with Proper Headers

### Test 1: List All Images (to verify tenant context)

```bash
# First, login to get tenant ID and token
curl -X POST "http://localhost:8000/api/auth/smart-login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07701234567",
    "password": "password123"
  }'
```

**Expected Response:**

```json
{
  "success": true,
  "message": "Login successful",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "Dr. Ahmed",
    "clinic_id": "_alamal"  ← ⭐ Use this value
  }
}
```

### Test 2: Get All Images with Proper Headers

```bash
# List images with tenant context
curl -X GET "http://localhost:8000/api/images" \
  -H "X-Tenant-ID: _alamal" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

**Expected Response:**

```json
{
  "success": true,
  "message": "Images retrieved successfully",
  "data": [
    {
      "id": 5,
      "path": "img1731774597.jpg",
      "disk": "public",
      "type": "general",
      "mime_type": null,
      "size": null,
      "width": null,
      "height": null,
      "alt_text": null,
      "order": 0,
      "imageable_type": "Patient",
      "imageable_id": 5,
      "url": "http://localhost:8000/storage/img1731774597.jpg",
      "created_at": "2024-11-16T16:30:11.000000Z",
      "updated_at": "2024-11-16T16:30:11.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Test 3: Get Single Image by ID

```bash
# Get specific image
curl -X GET "http://localhost:8000/api/images/5" \
  -H "X-Tenant-ID: _alamal" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

**Expected Response:**

```json
{
  "success": true,
  "message": "Image retrieved successfully",
  "data": {
    "id": 5,
    "path": "img1731774597.jpg",
    "disk": "public",
    "type": "general",
    "mime_type": null,
    "size": null,
    "width": null,
    "height": null,
    "alt_text": null,
    "order": 0,
    "imageable_type": "Patient",
    "imageable_id": 5,
    "url": "http://localhost:8000/storage/img1731774597.jpg",
    "created_at": "2024-11-16T16:30:11.000000Z",
    "updated_at": "2024-11-16T16:30:11.000000Z"
  }
}
```

### Test 4: Get Images by Imageable Type

```bash
# Get all images for a specific patient
curl -X GET "http://localhost:8000/api/images?imageable_type=Patient&imageable_id=5" \
  -H "X-Tenant-ID: _alamal" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Accept: application/json"
```

## Common Mistakes

### ❌ Missing X-Tenant-ID Header

```bash
# WRONG - Missing X-Tenant-ID
curl -X GET "http://localhost:8000/api/images/5" \
  -H "Authorization: Bearer TOKEN"

# Response: 404 Not Found
```

**Why?** System queries central database, image is in tenant database.

### ✅ Correct Way

```bash
# CORRECT - Include X-Tenant-ID
curl -X GET "http://localhost:8000/api/images/5" \
  -H "X-Tenant-ID: _alamal" \
  -H "Authorization: Bearer TOKEN"

# Response: 200 OK with image data
```

### ❌ Wrong Tenant ID

```bash
# WRONG - Wrong tenant ID
curl -X GET "http://localhost:8000/api/images/5" \
  -H "X-Tenant-ID: wrong_clinic_id" \
  -H "Authorization: Bearer TOKEN"

# Response: 404 Not Found (image doesn't exist in that tenant)
```

## Headers Breakdown

For ANY image API request, you need:

| Header          | Required    | Value            | Example                             |
| --------------- | ----------- | ---------------- | ----------------------------------- |
| `X-Tenant-ID`   | ✅ YES      | Your clinic ID   | `_alamal` or `clinic_1`             |
| `Authorization` | ✅ YES      | Bearer token     | `Bearer eyJ0eXAiOiJKV1QiLCJhbGc...` |
| `Accept`        | ❌ Optional | application/json | `application/json`                  |
| `Content-Type`  | ❌ Optional | application/json | `application/json`                  |

## Postman Collection

### 1. Login

```
POST http://localhost:8000/api/auth/smart-login
Content-Type: application/json

{
  "phone": "07701234567",
  "password": "password123"
}
```

### 2. Get Images

```
GET http://localhost:8000/api/images
Headers:
  X-Tenant-ID: {{clinic_id}}
  Authorization: Bearer {{token}}
  Accept: application/json
```

### 3. Get Image by ID

```
GET http://localhost:8000/api/images/5
Headers:
  X-Tenant-ID: {{clinic_id}}
  Authorization: Bearer {{token}}
  Accept: application/json
```

### 4. Upload Image

```
POST http://localhost:8000/api/images
Headers:
  X-Tenant-ID: {{clinic_id}}
  Authorization: Bearer {{token}}
  Content-Type: multipart/form-data

Body:
  file: (binary file)
  type: general
  imageable_type: Patient
  imageable_id: 5
```

### 5. Delete Image

```
DELETE http://localhost:8000/api/images/5
Headers:
  X-Tenant-ID: {{clinic_id}}
  Authorization: Bearer {{token}}
```

## JavaScript/Fetch Example

```javascript
// 1. Login
const loginResponse = await fetch(
  "http://localhost:8000/api/auth/smart-login",
  {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      phone: "07701234567",
      password: "password123",
    }),
  },
);

const { token, user } = await loginResponse.json();
const clinicId = user.clinic_id;

// 2. Get Image
const imageResponse = await fetch(`http://localhost:8000/api/images/5`, {
  method: "GET",
  headers: {
    "X-Tenant-ID": clinicId,
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  },
});

const imageData = await imageResponse.json();
console.log(imageData);
```

## Vue.js / Axios Example

```javascript
import axios from "axios";

// Set up axios instance
const api = axios.create({
  baseURL: "http://localhost:8000/api",
});

// Add interceptor to include tenant ID and token
api.interceptors.request.use((config) => {
  const clinicId = localStorage.getItem("clinic_id");
  const token = localStorage.getItem("token");

  config.headers["X-Tenant-ID"] = clinicId;
  config.headers["Authorization"] = `Bearer ${token}`;

  return config;
});

// Get image
async function getImage(imageId) {
  try {
    const response = await api.get(`/images/${imageId}`);
    return response.data;
  } catch (error) {
    console.error("Failed to get image:", error.response.data);
  }
}

// List images
async function listImages() {
  try {
    const response = await api.get("/images");
    return response.data;
  } catch (error) {
    console.error("Failed to list images:", error.response.data);
  }
}

// Upload image
async function uploadImage(file, type, imageableType, imageableId) {
  try {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("type", type);
    formData.append("imageable_type", imageableType);
    formData.append("imageable_id", imageableId);

    const response = await api.post("/images", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    return response.data;
  } catch (error) {
    console.error("Failed to upload image:", error.response.data);
  }
}
```

## React Example

```javascript
import axios from "axios";
import { useContext, useEffect, useState } from "react";

// Assuming you have AuthContext with token and clinic_id
const AuthContext = React.createContext();

function useImage() {
  const { token, clinicId } = useContext(AuthContext);
  const [image, setImage] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchImage = async (imageId) => {
    try {
      setLoading(true);
      const response = await axios.get(
        `http://localhost:8000/api/images/${imageId}`,
        {
          headers: {
            "X-Tenant-ID": clinicId,
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
          },
        },
      );

      setImage(response.data.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || "Failed to fetch image");
      setImage(null);
    } finally {
      setLoading(false);
    }
  };

  return { image, loading, error, fetchImage };
}

// Usage in component
function ImageComponent({ imageId }) {
  const { image, loading, error, fetchImage } = useImage();

  useEffect(() => {
    fetchImage(imageId);
  }, [imageId]);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!image) return <div>No image found</div>;

  return (
    <div>
      <img src={image.url} alt={image.alt_text} />
      <p>Type: {image.type}</p>
    </div>
  );
}
```

## Troubleshooting

### Still Getting 404?

1. **Verify tenant ID is correct:**

   ```bash
   # Check your clinic ID from login response
   echo "Your clinic_id should be: _alamal or similar"
   ```

2. **Verify image exists:**

   ```bash
   # Run in tinker
   php artisan tinker
   >>> App\Models\Image::find(5)
   ```

3. **Check headers are being sent:**

   ```bash
   # Use -v flag to see headers
   curl -v -X GET "http://localhost:8000/api/images/5" \
     -H "X-Tenant-ID: _alamal" \
     -H "Authorization: Bearer TOKEN"
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Summary

| Issue                 | Solution                                       |
| --------------------- | ---------------------------------------------- |
| 404 when image exists | Add `X-Tenant-ID` header                       |
| Can't find tenant ID  | Check login response `user.clinic_id`          |
| Wrong tenant database | Verify `X-Tenant-ID` value matches your clinic |
| Authorization error   | Refresh JWT token                              |
| Image list empty      | Make sure images exist in that tenant          |

✅ **Now your image API should work!**
