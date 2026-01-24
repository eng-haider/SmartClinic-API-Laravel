# Images API Documentation

**Version:** 1.0  
**Base URL:** `/api`  
**Authentication:** JWT Bearer Token Required

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Image Types](#image-types)
4. [Imageable Types](#imageable-types)
5. [API Endpoints](#api-endpoints)
   - [List Images](#1-list-images)
   - [Upload Single Image](#2-upload-single-image)
   - [Upload Multiple Images](#3-upload-multiple-images)
   - [Get Image Details](#4-get-image-details)
   - [Update Image Metadata](#5-update-image-metadata)
   - [Delete Image](#6-delete-image)
   - [Get Images by Imageable](#7-get-images-by-imageable)
   - [Update Image Order](#8-update-image-order)
   - [Get Image Statistics](#9-get-image-statistics)
6. [Response Examples](#response-examples)
7. [Error Handling](#error-handling)
8. [Use Cases](#use-cases)
9. [Frontend Integration](#frontend-integration)

---

## Overview

The Images API provides comprehensive image management functionality for the SmartClinic system. It supports:

- Single and multiple image uploads
- Polymorphic relationships with various models (Patient, Case, User, etc.)
- Image metadata management (type, alt text, dimensions, etc.)
- Automatic file storage and URL generation
- Image statistics and reporting
- Flexible filtering and pagination

---

## Authentication

All endpoints require JWT authentication. Include the token in the request header:

```
Authorization: Bearer {your-jwt-token}
```

---

## Image Types

The system supports the following predefined image types:

| Type           | Description         | Common Use                      |
| -------------- | ------------------- | ------------------------------- |
| `profile`      | Profile photos      | User or doctor profile pictures |
| `document`     | General documents   | ID cards, certificates          |
| `xray`         | X-ray images        | Dental X-rays, radiographs      |
| `before`       | Before treatment    | Treatment progress tracking     |
| `after`        | After treatment     | Treatment results               |
| `treatment`    | During treatment    | Procedure documentation         |
| `prescription` | Prescription images | Medication prescriptions        |
| `other`        | Miscellaneous       | Any other image type            |

---

## Imageable Types

Images can be attached to the following entity types:

- `Patient` - Patient medical images
- `Case` - Medical case documentation
- `User` - User profile pictures
- `Reservation` - Reservation-related images
- `Recipe` - Recipe/prescription images

---

## API Endpoints

### 1. List Images

Retrieve a paginated list of images with optional filtering.

**Endpoint:** `GET /api/images`

**Query Parameters:**

| Parameter        | Type    | Required | Description                       |
| ---------------- | ------- | -------- | --------------------------------- |
| `type`           | string  | No       | Filter by image type              |
| `imageable_type` | string  | No       | Filter by imageable model type    |
| `imageable_id`   | integer | No       | Filter by imageable model ID      |
| `date_from`      | date    | No       | Filter from date (YYYY-MM-DD)     |
| `date_to`        | date    | No       | Filter to date (YYYY-MM-DD)       |
| `paginate`       | boolean | No       | Enable pagination (default: true) |

**Example Request:**

```bash
curl -X GET "https://api.smartclinic.com/api/images?type=xray&paginate=true" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Images retrieved successfully",
  "data": [
    {
      "id": 1,
      "url": "https://storage.smartclinic.com/images/xray/uuid-12345.jpg",
      "path": "images/xray/uuid-12345.jpg",
      "disk": "public",
      "type": "xray",
      "mime_type": "image/jpeg",
      "size": 245678,
      "size_formatted": "239.92 KB",
      "width": 1920,
      "height": 1080,
      "dimensions": "1920x1080",
      "alt_text": "Dental X-ray of patient #123",
      "order": 0,
      "imageable_type": "Patient",
      "imageable_id": 123,
      "created_at": "2026-01-15T10:30:00.000000Z",
      "updated_at": "2026-01-15T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

---

### 2. Upload Single Image

Upload a single image file.

**Endpoint:** `POST /api/images`

**Content-Type:** `multipart/form-data`

**Request Body:**

| Field            | Type    | Required | Description                                      |
| ---------------- | ------- | -------- | ------------------------------------------------ |
| `image`          | file    | Yes      | Image file (JPEG, PNG, JPG, GIF, WEBP, max 10MB) |
| `type`           | string  | No       | Image type (see [Image Types](#image-types))     |
| `imageable_type` | string  | No       | Entity type to attach to                         |
| `imageable_id`   | integer | No       | Entity ID to attach to                           |
| `alt_text`       | string  | No       | Alternative text for accessibility               |

**Example Request:**

```bash
curl -X POST "https://api.smartclinic.com/api/images" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json" \
  -F "image=@/path/to/xray.jpg" \
  -F "type=xray" \
  -F "imageable_type=Patient" \
  -F "imageable_id=123" \
  -F "alt_text=Dental X-ray"
```

**Success Response (201 Created):**

```json
{
  "success": true,
  "message": "Image uploaded successfully",
  "data": {
    "id": 45,
    "url": "https://storage.smartclinic.com/images/xray/uuid-67890.jpg",
    "path": "images/xray/uuid-67890.jpg",
    "disk": "public",
    "type": "xray",
    "mime_type": "image/jpeg",
    "size": 345678,
    "size_formatted": "337.58 KB",
    "width": 2048,
    "height": 1536,
    "dimensions": "2048x1536",
    "alt_text": "Dental X-ray",
    "order": 0,
    "imageable_type": "Patient",
    "imageable_id": 123,
    "created_at": "2026-01-22T14:25:00.000000Z",
    "updated_at": "2026-01-22T14:25:00.000000Z"
  }
}
```

---

### 3. Upload Multiple Images

Upload multiple images in a single request.

**Endpoint:** `POST /api/images`

**Content-Type:** `multipart/form-data`

**Request Body:**

| Field            | Type    | Required | Description                          |
| ---------------- | ------- | -------- | ------------------------------------ |
| `images[]`       | file[]  | Yes      | Array of image files (max 10 images) |
| `type`           | string  | No       | Image type for all images            |
| `imageable_type` | string  | No       | Entity type to attach to             |
| `imageable_id`   | integer | No       | Entity ID to attach to               |

**Example Request:**

```bash
curl -X POST "https://api.smartclinic.com/api/images" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json" \
  -F "images[]=@/path/to/before.jpg" \
  -F "images[]=@/path/to/after.jpg" \
  -F "type=treatment" \
  -F "imageable_type=Case" \
  -F "imageable_id=456"
```

**Success Response (201 Created):**

```json
{
  "success": true,
  "message": "Images uploaded successfully",
  "data": [
    {
      "id": 46,
      "url": "https://storage.smartclinic.com/images/treatment/uuid-11111.jpg",
      "path": "images/treatment/uuid-11111.jpg",
      "type": "treatment",
      "order": 0,
      "imageable_type": "Case",
      "imageable_id": 456,
      "created_at": "2026-01-22T14:30:00.000000Z"
    },
    {
      "id": 47,
      "url": "https://storage.smartclinic.com/images/treatment/uuid-22222.jpg",
      "path": "images/treatment/uuid-22222.jpg",
      "type": "treatment",
      "order": 1,
      "imageable_type": "Case",
      "imageable_id": 456,
      "created_at": "2026-01-22T14:30:01.000000Z"
    }
  ]
}
```

---

### 4. Get Image Details

Retrieve detailed information about a specific image.

**Endpoint:** `GET /api/images/{id}`

**Example Request:**

```bash
curl -X GET "https://api.smartclinic.com/api/images/45" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Image retrieved successfully",
  "data": {
    "id": 45,
    "url": "https://storage.smartclinic.com/images/xray/uuid-67890.jpg",
    "path": "images/xray/uuid-67890.jpg",
    "disk": "public",
    "type": "xray",
    "mime_type": "image/jpeg",
    "size": 345678,
    "size_formatted": "337.58 KB",
    "width": 2048,
    "height": 1536,
    "dimensions": "2048x1536",
    "alt_text": "Dental X-ray",
    "order": 0,
    "imageable_type": "Patient",
    "imageable_id": 123,
    "created_at": "2026-01-22T14:25:00.000000Z",
    "updated_at": "2026-01-22T14:25:00.000000Z"
  }
}
```

---

### 5. Update Image Metadata

Update image metadata (type, alt text, order). The actual image file cannot be updated.

**Endpoint:** `PUT /api/images/{id}` or `PATCH /api/images/{id}`

**Request Body:**

| Field      | Type    | Required | Description             |
| ---------- | ------- | -------- | ----------------------- |
| `type`     | string  | No       | Update image type       |
| `alt_text` | string  | No       | Update alternative text |
| `order`    | integer | No       | Update display order    |

**Example Request:**

```bash
curl -X PUT "https://api.smartclinic.com/api/images/45" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "alt_text": "Updated dental X-ray description",
    "order": 5
  }'
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Image updated successfully",
  "data": {
    "id": 45,
    "url": "https://storage.smartclinic.com/images/xray/uuid-67890.jpg",
    "alt_text": "Updated dental X-ray description",
    "order": 5,
    "updated_at": "2026-01-22T15:00:00.000000Z"
  }
}
```

---

### 6. Delete Image

Delete an image and its associated file from storage.

**Endpoint:** `DELETE /api/images/{id}`

**Example Request:**

```bash
curl -X DELETE "https://api.smartclinic.com/api/images/45" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Image deleted successfully"
}
```

---

### 7. Get Images by Imageable

Retrieve all images associated with a specific entity (Patient, Case, etc.).

**Endpoint:** `GET /api/images/by-imageable`

**Query Parameters:**

| Parameter        | Type    | Required | Description                             |
| ---------------- | ------- | -------- | --------------------------------------- |
| `imageable_type` | string  | Yes      | Entity type (Patient, Case, User, etc.) |
| `imageable_id`   | integer | Yes      | Entity ID                               |
| `type`           | string  | No       | Filter by image type                    |

**Example Request:**

```bash
curl -X GET "https://api.smartclinic.com/api/images/by-imageable?imageable_type=Patient&imageable_id=123&type=xray" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Images retrieved successfully",
  "data": [
    {
      "id": 45,
      "url": "https://storage.smartclinic.com/images/xray/uuid-67890.jpg",
      "type": "xray",
      "imageable_type": "Patient",
      "imageable_id": 123,
      "order": 0
    },
    {
      "id": 52,
      "url": "https://storage.smartclinic.com/images/xray/uuid-99999.jpg",
      "type": "xray",
      "imageable_type": "Patient",
      "imageable_id": 123,
      "order": 1
    }
  ]
}
```

---

### 8. Update Image Order

Update the display order of a specific image.

**Endpoint:** `PATCH /api/images/{id}/order`

**Request Body:**

| Field   | Type    | Required | Description                    |
| ------- | ------- | -------- | ------------------------------ |
| `order` | integer | Yes      | New order value (0 or greater) |

**Example Request:**

```bash
curl -X PATCH "https://api.smartclinic.com/api/images/45/order" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "order": 3
  }'
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Image order updated successfully",
  "data": {
    "id": 45,
    "order": 3,
    "updated_at": "2026-01-22T15:30:00.000000Z"
  }
}
```

---

### 9. Get Image Statistics

Retrieve statistics about stored images.

**Endpoint:** `GET /api/images/statistics/summary`

**Example Request:**

```bash
curl -X GET "https://api.smartclinic.com/api/images/statistics/summary" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Statistics retrieved successfully",
  "data": {
    "total_images": 342,
    "total_size_bytes": 1567234890,
    "total_size_mb": 1494.82,
    "by_type": {
      "xray": {
        "type": "xray",
        "count": 145,
        "total_size": 678901234
      },
      "profile": {
        "type": "profile",
        "count": 89,
        "total_size": 234567890
      },
      "treatment": {
        "type": "treatment",
        "count": 78,
        "total_size": 456789012
      },
      "document": {
        "type": "document",
        "count": 30,
        "total_size": 196976754
      }
    }
  }
}
```

---

## Response Examples

### Success Response Structure

All successful responses follow this structure:

```json
{
  "success": true,
  "message": "Operation description",
  "data": {
    /* Response data */
  }
}
```

### Paginated Response Structure

Paginated endpoints include metadata:

```json
{
  "success": true,
  "message": "Items retrieved successfully",
  "data": [
    /* Array of items */
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 142
  }
}
```

---

## Error Handling

### Common Error Codes

| Status Code | Description                              |
| ----------- | ---------------------------------------- |
| 400         | Bad Request - Invalid input              |
| 401         | Unauthorized - Missing or invalid token  |
| 404         | Not Found - Image doesn't exist          |
| 422         | Unprocessable Entity - Validation failed |
| 500         | Internal Server Error                    |

### Error Response Structure

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

### Validation Error Example

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "image": ["The image must be a file of type: jpeg, png, jpg, gif, webp."],
    "type": [
      "The image type must be one of: profile, document, xray, before, after, treatment, prescription, other."
    ]
  }
}
```

---

## Use Cases

### Use Case 1: Upload Patient X-ray

```javascript
// Upload a dental X-ray for a patient
const formData = new FormData();
formData.append("image", xrayFile);
formData.append("type", "xray");
formData.append("imageable_type", "Patient");
formData.append("imageable_id", patientId);
formData.append("alt_text", "Dental X-ray - Upper jaw");

const response = await fetch("/api/images", {
  method: "POST",
  headers: {
    Authorization: `Bearer ${token}`,
  },
  body: formData,
});

const result = await response.json();
console.log("Uploaded image URL:", result.data.url);
```

### Use Case 2: Display Patient Gallery

```javascript
// Fetch all images for a patient
const response = await fetch(
  `/api/images/by-imageable?imageable_type=Patient&imageable_id=${patientId}`,
  {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  }
);

const result = await response.json();
const images = result.data;

// Display in gallery
images.forEach((image) => {
  console.log(`${image.type}: ${image.url}`);
});
```

### Use Case 3: Before/After Treatment Comparison

```javascript
// Upload before and after images
const formData = new FormData();
formData.append("images[]", beforeImage);
formData.append("images[]", afterImage);
formData.append("type", "treatment");
formData.append("imageable_type", "Case");
formData.append("imageable_id", caseId);

const response = await fetch("/api/images", {
  method: "POST",
  headers: {
    Authorization: `Bearer ${token}`,
  },
  body: formData,
});

const result = await response.json();
const [before, after] = result.data;

// Display comparison
console.log("Before:", before.url);
console.log("After:", after.url);
```

### Use Case 4: Update Image Order in Gallery

```javascript
// Reorder images in a gallery
async function reorderImage(imageId, newOrder) {
  const response = await fetch(`/api/images/${imageId}/order`, {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ order: newOrder }),
  });

  return await response.json();
}

// Usage
await reorderImage(45, 0); // Move to first position
```

### Use Case 5: Storage Statistics Dashboard

```javascript
// Fetch and display storage statistics
const response = await fetch("/api/images/statistics/summary", {
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  },
});

const result = await response.json();
const stats = result.data;

console.log(`Total Images: ${stats.total_images}`);
console.log(`Total Storage: ${stats.total_size_mb} MB`);
console.log("By Type:");
Object.values(stats.by_type).forEach((typeStats) => {
  console.log(`  ${typeStats.type}: ${typeStats.count} images`);
});
```

---

## Frontend Integration

### React Example: Image Upload Component

```jsx
import React, { useState } from "react";
import axios from "axios";

const ImageUploader = ({ patientId, onUploadComplete }) => {
  const [file, setFile] = useState(null);
  const [type, setType] = useState("xray");
  const [uploading, setUploading] = useState(false);

  const handleUpload = async () => {
    if (!file) return;

    setUploading(true);
    const formData = new FormData();
    formData.append("image", file);
    formData.append("type", type);
    formData.append("imageable_type", "Patient");
    formData.append("imageable_id", patientId);

    try {
      const response = await axios.post("/api/images", formData, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("token")}`,
          "Content-Type": "multipart/form-data",
        },
      });

      onUploadComplete(response.data.data);
      setFile(null);
    } catch (error) {
      console.error("Upload failed:", error);
    } finally {
      setUploading(false);
    }
  };

  return (
    <div>
      <input
        type="file"
        accept="image/*"
        onChange={(e) => setFile(e.target.files[0])}
      />
      <select value={type} onChange={(e) => setType(e.target.value)}>
        <option value="xray">X-ray</option>
        <option value="profile">Profile</option>
        <option value="treatment">Treatment</option>
      </select>
      <button onClick={handleUpload} disabled={!file || uploading}>
        {uploading ? "Uploading..." : "Upload"}
      </button>
    </div>
  );
};

export default ImageUploader;
```

### Vue.js Example: Image Gallery Component

```vue
<template>
  <div class="image-gallery">
    <div v-for="image in images" :key="image.id" class="image-item">
      <img :src="image.url" :alt="image.alt_text" />
      <div class="image-info">
        <span>{{ image.type }}</span>
        <span>{{ image.size_formatted }}</span>
        <button @click="deleteImage(image.id)">Delete</button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "ImageGallery",
  props: ["patientId"],
  data() {
    return {
      images: [],
    };
  },
  async mounted() {
    await this.fetchImages();
  },
  methods: {
    async fetchImages() {
      const response = await this.$http.get("/api/images/by-imageable", {
        params: {
          imageable_type: "Patient",
          imageable_id: this.patientId,
        },
      });
      this.images = response.data.data;
    },
    async deleteImage(imageId) {
      if (!confirm("Delete this image?")) return;

      await this.$http.delete(`/api/images/${imageId}`);
      this.images = this.images.filter((img) => img.id !== imageId);
    },
  },
};
</script>
```

---

## File Size Limits

- **Maximum file size:** 10MB per image
- **Maximum files per upload:** 10 images
- **Supported formats:** JPEG, PNG, JPG, GIF, WEBP

---

## Best Practices

1. **Always include alt_text** for accessibility
2. **Use appropriate image types** for better organization
3. **Compress images** before upload to save storage
4. **Delete unused images** to free up storage space
5. **Use pagination** when fetching large image lists
6. **Set proper order values** for multi-image galleries

---

## Notes

- Images are automatically deleted from storage when the database record is deleted
- Image URLs are generated dynamically based on the configured storage disk
- Clinic users only see images related to their clinic's entities
- Super admins have access to all images across all clinics

---

**Last Updated:** January 22, 2026  
**API Version:** 1.0
