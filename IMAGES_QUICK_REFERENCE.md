# Images API Quick Reference

Quick reference guide for the SmartClinic Images API.

---

## Base URL

```
/api/images
```

## Authentication

```
Authorization: Bearer {JWT_TOKEN}
```

---

## Endpoints Cheat Sheet

### 📋 List Images

```bash
GET /api/images?type=xray&paginate=true
```

### ⬆️ Upload Single Image

```bash
POST /api/images
Content-Type: multipart/form-data

Fields:
- image (file, required)
- type (string, optional)
- imageable_type (string, optional)
- imageable_id (integer, optional)
- alt_text (string, optional)
```

### ⬆️ Upload Multiple Images

```bash
POST /api/images
Content-Type: multipart/form-data

Fields:
- images[] (file array, required, max 10)
- type (string, optional)
- imageable_type (string, optional)
- imageable_id (integer, optional)
```

### 🔍 Get Image Details

```bash
GET /api/images/{id}
```

### ✏️ Update Image Metadata

```bash
PUT /api/images/{id}
Content-Type: application/json

{
  "type": "xray",
  "alt_text": "Updated description",
  "order": 5
}
```

### 🗑️ Delete Image

```bash
DELETE /api/images/{id}
```

### 🔗 Get Images by Entity

```bash
GET /api/images/by-imageable?imageable_type=Patient&imageable_id=123&type=xray
```

### 🔢 Update Image Order

```bash
PATCH /api/images/{id}/order
Content-Type: application/json

{
  "order": 3
}
```

### 📊 Get Statistics

```bash
GET /api/images/statistics/summary
```

---

## Image Types

| Type           | Use Case                       |
| -------------- | ------------------------------ |
| `profile`      | User/doctor profile pictures   |
| `document`     | ID cards, certificates         |
| `xray`         | Dental X-rays, radiographs     |
| `before`       | Before treatment photos        |
| `after`        | After treatment photos         |
| `treatment`    | During treatment documentation |
| `prescription` | Medication prescriptions       |
| `other`        | Miscellaneous images           |

---

## Imageable Types

- `Patient`
- `Case`
- `User`
- `Reservation`
- `Recipe`

---

## File Limits

- **Max Size:** 10MB per file
- **Max Files:** 10 per upload
- **Formats:** JPEG, PNG, JPG, GIF, WEBP

---

## Response Format

```json
{
  "success": true,
  "message": "Operation message",
  "data": {
    "id": 1,
    "url": "https://storage.../image.jpg",
    "type": "xray",
    "size_formatted": "337.58 KB",
    "dimensions": "2048x1536",
    "imageable_type": "Patient",
    "imageable_id": 123
  }
}
```

---

## Common Filters

```bash
# By type
?type=xray

# By entity
?imageable_type=Patient&imageable_id=123

# By date range
?date_from=2026-01-01&date_to=2026-01-31

# Without pagination
?paginate=false
```

---

## Error Codes

| Code | Meaning           |
| ---- | ----------------- |
| 200  | Success           |
| 201  | Created           |
| 400  | Bad Request       |
| 401  | Unauthorized      |
| 404  | Not Found         |
| 422  | Validation Failed |
| 500  | Server Error      |

---

**Last Updated:** January 22, 2026
