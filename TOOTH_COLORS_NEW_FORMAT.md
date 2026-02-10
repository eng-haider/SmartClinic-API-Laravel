# Tooth Colors Setting - New Format

## Overview

The `tooth_colors` setting now supports changing both the **name** and **color** of each tooth status from the frontend.

## New Format Structure

```json
[
  {
    "id": "healthy",
    "name": "Healthy",
    "color": "#FFFFFF"
  },
  {
    "id": "cavity",
    "name": "Cavity",
    "color": "#FF6B6B"
  },
  {
    "id": "filling",
    "name": "Filling",
    "color": "#4ECDC4"
  },
  {
    "id": "crown",
    "name": "Crown",
    "color": "#FFD93D"
  },
  {
    "id": "missing",
    "name": "Missing",
    "color": "#95A5A6"
  },
  {
    "id": "implant",
    "name": "Implant",
    "color": "#3498DB"
  },
  {
    "id": "root_canal",
    "name": "Root Canal",
    "color": "#9B59B6"
  }
]
```

## Fields Explanation

- **`id`**: Unique identifier (used in code, should not be changed)
- **`name`**: Display name (can be changed from frontend - supports any language)
- **`color`**: Hex color code (can be changed from frontend)

## Usage Examples

### Get Current Settings

```http
GET /api/tenant/clinic-settings/tooth_colors
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 25,
    "setting_key": "tooth_colors",
    "setting_value": [
      {
        "id": "cavity",
        "name": "Cavity",
        "color": "#FF6B6B"
      }
      // ... more statuses
    ]
  }
}
```

### Update Tooth Status Name and Color

```http
PUT /api/tenant/clinic-settings/tooth_colors
Content-Type: application/json

{
  "setting_value": [
    {
      "id": "healthy",
      "name": "صحي",
      "color": "#FFFFFF"
    },
    {
      "id": "cavity",
      "name": "تسوس",
      "color": "#FF0000"
    },
    {
      "id": "filling",
      "name": "حشوة",
      "color": "#4ECDC4"
    },
    {
      "id": "crown",
      "name": "تاج",
      "color": "#FFD93D"
    },
    {
      "id": "missing",
      "name": "مفقود",
      "color": "#95A5A6"
    },
    {
      "id": "implant",
      "name": "زراعة",
      "color": "#3498DB"
    },
    {
      "id": "root_canal",
      "name": "علاج جذور",
      "color": "#9B59B6"
    }
  ]
}
```

## Frontend Implementation

### Display Tooth Statuses

```javascript
// Fetch settings
const response = await fetch("/api/tenant/clinic-settings/tooth_colors");
const { data } = await response.json();

// Render in UI
data.setting_value.forEach((status) => {
  console.log(`${status.name}: ${status.color}`);
  // Display in color picker or dropdown
});
```

### Update Status Name

```javascript
const toothColors = [
  {
    id: "cavity",
    name: "تسوس", // Changed from "Cavity" to Arabic
    color: "#FF6B6B",
  },
  // ... other statuses
];

await fetch("/api/tenant/clinic-settings/tooth_colors", {
  method: "PUT",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ setting_value: toothColors }),
});
```

### Update Status Color

```javascript
const toothColors = [
  {
    id: "cavity",
    name: "Cavity",
    color: "#FF0000", // Changed from "#FF6B6B" to red
  },
  // ... other statuses
];

await fetch("/api/tenant/clinic-settings/tooth_colors", {
  method: "PUT",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ setting_value: toothColors }),
});
```

## Important Notes

1. **Always send the complete array** when updating - include all statuses, not just the one you're changing
2. **Never change the `id` field** - it's used in the code to identify statuses
3. **The `name` field supports any language** - you can use Arabic, English, or any other language
4. **Colors must be valid hex codes** - e.g., `#FFFFFF`, `#FF6B6B`
5. **Changes are tenant-specific** - each clinic can have different names/colors

## Migration for Existing Tenants

If you have existing tenants with the old format (`{"cavity": "#FF6B6B"}`), you need to manually update them to the new format or run this SQL:

```sql
-- For each tenant database:
UPDATE clinic_settings
SET setting_value = JSON_ARRAY(
  JSON_OBJECT('id', 'healthy', 'name', 'Healthy', 'color', '#FFFFFF'),
  JSON_OBJECT('id', 'cavity', 'name', 'Cavity', 'color', '#FF6B6B'),
  JSON_OBJECT('id', 'filling', 'name', 'Filling', 'color', '#4ECDC4'),
  JSON_OBJECT('id', 'crown', 'name', 'Crown', 'color', '#FFD93D'),
  JSON_OBJECT('id', 'missing', 'name', 'Missing', 'color', '#95A5A6'),
  JSON_OBJECT('id', 'implant', 'name', 'Implant', 'color', '#3498DB'),
  JSON_OBJECT('id', 'root_canal', 'name', 'Root Canal', 'color', '#9B59B6')
)
WHERE setting_key = 'tooth_colors';
```

## Benefits of New Format

✅ **Multilingual Support**: Change names to any language  
✅ **Easy Customization**: Update both name and color  
✅ **Better UX**: Frontend can display name + color picker  
✅ **Backward Compatible**: Old code can still use `id` field  
✅ **Extensible**: Easy to add new statuses in the future
