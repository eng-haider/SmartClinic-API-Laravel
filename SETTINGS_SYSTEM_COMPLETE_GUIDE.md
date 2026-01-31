# Clinic Settings System - Complete Guide

## 🎯 System Overview

The clinic settings system has **TWO LEVELS**:

### Level 1: Setting Definitions (Super Admin)

**Table:** `setting_definitions`

- **Super Admin** defines WHAT settings exist
- Creates the "template" of available settings
- All clinics automatically get these settings

### Level 2: Clinic Settings (Doctors)

**Table:** `clinic_settings`

- Each clinic has their own copy of settings
- **Doctors** can only UPDATE the values
- Cannot create new setting keys (only Super Admin can)

---

## 🔄 How It Works

### 1. When Super Admin Adds a New Setting Key

```
1. Super Admin creates setting definition via:
   POST /api/setting-definitions

2. System automatically creates this setting for ALL existing clinics

3. All clinics can now see and update this setting
```

### 2. When a New Clinic Registers

```
1. User registers clinic via:
   POST /api/auth/register

2. System automatically:
   - Creates clinic
   - Copies ALL setting definitions to clinic_settings
   - Each setting gets default value from definition

3. Clinic is ready with all settings configured
```

### 3. When Doctor Updates Settings

```
1. Doctor updates setting value via:
   PUT /api/clinic-settings/{key}

2. Only the VALUE is updated
3. Cannot create new keys
4. Setting type and structure remain from definition
```

---

## 📊 Database Structure

### `setting_definitions` Table (Master Template)

```sql
- id
- setting_key           (unique, e.g., "tooth_colors")
- setting_type          (string, boolean, integer, json)
- default_value         (default value for new clinics)
- description
- category             (general, appointment, notification, etc.)
- display_order        (for sorting in UI)
- is_required
- is_active
- created_at
- updated_at
```

### `clinic_settings` Table (Clinic-Specific Values)

```sql
- id
- clinic_id            (foreign key to clinics)
- setting_key          (links to setting_definitions)
- setting_value        (clinic's custom value)
- setting_type         (copied from definition)
- description          (copied from definition)
- is_active
- created_at
- updated_at

UNIQUE: (clinic_id, setting_key)
```

---

## 🎨 Special Settings: Tooth Colors & Statuses

### Tooth Colors Setting

**Key:** `tooth_colors`
**Type:** `json`

**Structure:**

```json
[
  {
    "id": 1,
    "name": "White",
    "color": "#FFFFFF",
    "hex_code": "#FFFFFF",
    "is_active": true
  },
  {
    "id": 2,
    "name": "Light Yellow",
    "color": "#FFFACD",
    "hex_code": "#FFFACD",
    "is_active": true
  }
]
```

**Pre-defined Colors:**

1. White - `#FFFFFF`
2. Light Yellow - `#FFFACD`
3. Yellow - `#FFD700`
4. Light Brown - `#D2B48C`
5. Brown - `#8B4513`
6. Dark Brown - `#654321`
7. Grey - `#808080`
8. Black - `#000000`
9. Red (Infection) - `#FF0000`
10. Blue (Bruise) - `#0000FF`

### Tooth Statuses Setting

**Key:** `tooth_statuses`
**Type:** `json`

**Structure:**

```json
[
  {
    "id": 1,
    "name": "Healthy",
    "color": "#22C55E",
    "icon": "✓",
    "is_active": true
  },
  {
    "id": 2,
    "name": "Cavity",
    "color": "#EF4444",
    "icon": "⚠",
    "is_active": true
  }
]
```

**Pre-defined Statuses:**

1. Healthy - Green `#22C55E` ✓
2. Cavity - Red `#EF4444` ⚠
3. Filled - Blue `#3B82F6` ■
4. Missing - Grey `#6B7280` ✗
5. Crown - Orange `#F59E0B` ♔
6. Root Canal - Purple `#8B5CF6` ⊕
7. Implant - Teal `#14B8A6` ⊛
8. Bridge - Pink `#EC4899` ⊞

---

## 🔐 API Endpoints

### Super Admin Endpoints (Setting Definitions)

#### 1. Get All Setting Definitions

```http
GET /api/setting-definitions
Authorization: Bearer {super_admin_token}
```

**Response:**

```json
{
  "success": true,
  "message": "Setting definitions retrieved successfully",
  "data": [
    {
      "id": 1,
      "setting_key": "tooth_colors",
      "setting_type": "json",
      "default_value": [...],
      "description": "Available tooth colors for dental charts",
      "category": "general",
      "display_order": 10,
      "is_required": false,
      "is_active": true
    }
  ],
  "meta": {
    "categories": {
      "general": "General Information",
      "appointment": "Appointment Settings",
      "notification": "Notification Settings",
      "financial": "Financial Settings",
      "display": "Display Settings",
      "social": "Social Media"
    },
    "types": ["string", "boolean", "integer", "json"]
  }
}
```

#### 2. Create New Setting Definition

```http
POST /api/setting-definitions
Authorization: Bearer {super_admin_token}
Content-Type: application/json

{
  "setting_key": "new_setting_name",
  "setting_type": "string",
  "default_value": "default value",
  "description": "What this setting does",
  "category": "general",
  "display_order": 1,
  "is_required": false
}
```

**Response:**

```json
{
  "success": true,
  "message": "Setting definition created and synced to 5 clinics",
  "data": {
    "id": 28,
    "setting_key": "new_setting_name",
    ...
  }
}
```

#### 3. Update Setting Definition

```http
PUT /api/setting-definitions/{id}
Authorization: Bearer {super_admin_token}
Content-Type: application/json

{
  "description": "Updated description",
  "default_value": "new default"
}
```

**Note:** Updates metadata for all clinics but doesn't change existing values.

#### 4. Delete Setting Definition

```http
DELETE /api/setting-definitions/{id}?remove_from_clinics=true
Authorization: Bearer {super_admin_token}
```

**Query Params:**

- `remove_from_clinics=true` - Also delete from all clinics
- `remove_from_clinics=false` - Keep in clinics (default)

#### 5. Sync All Definitions to All Clinics

```http
POST /api/setting-definitions/sync-all
Authorization: Bearer {super_admin_token}
```

Ensures all clinics have all active definitions.

---

### Doctor Endpoints (Clinic Settings)

#### 1. Get All Clinic Settings (Grouped by Category)

```http
GET /api/clinic-settings
Authorization: Bearer {doctor_token}
```

**Response:**

```json
{
  "success": true,
  "message": "Clinic settings retrieved successfully",
  "data": {
    "general": {
      "label": "General Information",
      "settings": [
        {
          "id": 1,
          "setting_key": "clinic_name",
          "setting_value": "My Dental Clinic",
          "setting_type": "string",
          "description": "Official clinic name",
          "is_required": true,
          "display_order": 1
        },
        {
          "id": 10,
          "setting_key": "tooth_colors",
          "setting_value": [
            {
              "id": 1,
              "name": "White",
              "color": "#FFFFFF",
              "hex_code": "#FFFFFF",
              "is_active": true
            }
          ],
          "setting_type": "json",
          "description": "Available tooth colors",
          "is_required": false,
          "display_order": 10
        }
      ]
    },
    "appointment": {
      "label": "Appointment Settings",
      "settings": [...]
    }
  }
}
```

#### 2. Update Single Setting Value

```http
PUT /api/clinic-settings/tooth_colors
Authorization: Bearer {doctor_token}
Content-Type: application/json

{
  "setting_value": [
    {
      "id": 1,
      "name": "Pearl White",
      "color": "#F5F5F5",
      "hex_code": "#F5F5F5",
      "is_active": true
    },
    {
      "id": 2,
      "name": "Custom Yellow",
      "color": "#FFEB3B",
      "hex_code": "#FFEB3B",
      "is_active": true
    }
  ]
}
```

**Response:**

```json
{
  "success": true,
  "message": "Clinic setting updated successfully",
  "data": {
    "id": 10,
    "setting_key": "tooth_colors",
    "setting_value": [...],
    "setting_type": "json"
  }
}
```

#### 3. Bulk Update Settings

```http
POST /api/clinic-settings/bulk-update
Authorization: Bearer {doctor_token}
Content-Type: application/json

{
  "settings": [
    {
      "key": "clinic_name",
      "value": "Updated Clinic Name"
    },
    {
      "key": "phone",
      "value": "+1234567890"
    },
    {
      "key": "enable_sms",
      "value": true
    }
  ]
}
```

---

## 🚀 Setup Instructions

### 1. Run Migrations

```bash
cd /Users/haideraltemimy/Documents/GitHub/SmartClinic-API-Laravel

# Run the new migration
php artisan migrate
```

This creates the `setting_definitions` table.

### 2. Seed Setting Definitions

```bash
# Seed the master definitions
php artisan db:seed --class=SettingDefinitionsSeeder
```

This creates 29 default setting definitions including tooth colors and statuses.

### 3. Sync to Existing Clinics

```bash
# Option A: Via API (recommended)
curl -X POST http://localhost:8000/api/setting-definitions/sync-all \
  -H "Authorization: Bearer {super_admin_token}"

# Option B: Via Artisan (create custom command)
php artisan settings:sync-all-clinics
```

### 4. Update Permissions

```bash
# Sync permissions
php artisan db:seed --class=RolesAndPermissionsSeeder
```

---

## 💡 Usage Examples

### Example 1: Super Admin Adds New Color

```javascript
// Add a new tooth color option for all clinics
const response = await fetch("/api/setting-definitions", {
  method: "POST",
  headers: {
    Authorization: `Bearer ${superAdminToken}`,
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    setting_key: "tooth_surface_colors",
    setting_type: "json",
    default_value: JSON.stringify([
      {
        id: 1,
        name: "Smooth",
        color: "#E8E8E8",
        is_active: true,
      },
      {
        id: 2,
        name: "Rough",
        color: "#C0C0C0",
        is_active: true,
      },
    ]),
    description: "Tooth surface texture colors",
    category: "general",
    display_order: 12,
  }),
});

// This automatically creates the setting for ALL clinics!
```

### Example 2: Doctor Customizes Tooth Colors

```javascript
// Get current tooth colors
const settings = await fetch("/api/clinic-settings", {
  headers: { Authorization: `Bearer ${doctorToken}` },
}).then((r) => r.json());

const toothColors = settings.data.general.settings.find(
  (s) => s.setting_key === "tooth_colors",
);

// Modify the colors
const customColors = toothColors.setting_value.map((color) => {
  if (color.name === "White") {
    return { ...color, color: "#FAFAFA", name: "Pearl White" };
  }
  return color;
});

// Add a new custom color
customColors.push({
  id: 11,
  name: "Custom Green",
  color: "#00FF00",
  hex_code: "#00FF00",
  is_active: true,
});

// Update the setting
await fetch("/api/clinic-settings/tooth_colors", {
  method: "PUT",
  headers: {
    Authorization: `Bearer ${doctorToken}`,
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    setting_value: customColors,
  }),
});
```

### Example 3: Display Tooth Color Picker

```javascript
// Fetch tooth colors for UI
const getToothColors = async () => {
  const response = await fetch("/api/clinic-settings/tooth_colors", {
    headers: { Authorization: `Bearer ${token}` },
  });

  const data = await response.json();
  const colors = data.data.setting_value;

  // Render color picker
  return colors
    .filter((c) => c.is_active)
    .map(
      (color) => `
      <div class="color-option" data-id="${color.id}">
        <div class="color-swatch" style="background: ${color.color}"></div>
        <span>${color.name}</span>
      </div>
    `,
    )
    .join("");
};
```

---

## ✅ Testing Checklist

### Super Admin Tests

- [ ] Create new setting definition
- [ ] Verify it syncs to all existing clinics
- [ ] Update setting definition
- [ ] Delete setting definition
- [ ] Sync all definitions to all clinics
- [ ] Get categories and types

### Doctor Tests

- [ ] View all settings grouped by category
- [ ] Update tooth_colors setting
- [ ] Update tooth_statuses setting
- [ ] Bulk update multiple settings
- [ ] Upload logo
- [ ] Try to create new setting key (should fail)

### Integration Tests

- [ ] Register new clinic - verify all settings are auto-created
- [ ] Super Admin adds new definition - verify it appears for all clinics
- [ ] Doctor updates value - verify it only changes for their clinic
- [ ] Delete definition - verify it removes from all clinics (if flag set)

---

## 🎨 Frontend Integration Tips

### 1. Tooth Color Selector Component

```vue
<template>
  <div class="tooth-color-selector">
    <h3>Select Tooth Color</h3>
    <div class="color-grid">
      <div
        v-for="color in toothColors"
        :key="color.id"
        @click="selectColor(color)"
        :class="{ selected: selectedColor?.id === color.id }"
        class="color-item"
      >
        <div class="color-circle" :style="{ background: color.color }"></div>
        <span>{{ color.name }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      toothColors: [],
      selectedColor: null,
    };
  },
  async mounted() {
    const response = await fetch("/api/clinic-settings/tooth_colors", {
      headers: { Authorization: `Bearer ${this.token}` },
    });
    const data = await response.json();
    this.toothColors = data.data.setting_value.filter((c) => c.is_active);
  },
  methods: {
    selectColor(color) {
      this.selectedColor = color;
      this.$emit("color-selected", color);
    },
  },
};
</script>
```

### 2. Settings Management Page

```javascript
// Group settings by category for easy management
const SettingsPage = () => {
  const [settings, setSettings] = useState({});

  useEffect(() => {
    fetch("/api/clinic-settings", {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((r) => r.json())
      .then((data) => setSettings(data.data));
  }, []);

  return (
    <div className="settings-page">
      {Object.entries(settings).map(([category, categoryData]) => (
        <div key={category} className="category-section">
          <h2>{categoryData.label}</h2>
          {categoryData.settings.map((setting) => (
            <SettingField
              key={setting.id}
              setting={setting}
              onUpdate={handleUpdate}
            />
          ))}
        </div>
      ))}
    </div>
  );
};
```

---

## 🔒 Security & Permissions

### Permissions Required:

- `manage-setting-definitions` - Super Admin only
- `view-clinic-settings` - All roles with access
- `edit-clinic-settings` - Doctors with edit rights

### Access Control:

- Super Admin: Full access to setting definitions
- Clinic Super Doctor: Can view and edit their clinic's values
- Doctor: Can view their clinic's values
- Secretary: Configurable access

---

## 📝 Summary

**What was implemented:**

1. ✅ **Two-level settings system**
   - Super Admin defines available settings
   - Doctors customize values for their clinic

2. ✅ **Auto-sync mechanism**
   - New clinics get all settings automatically
   - New definitions sync to existing clinics

3. ✅ **Tooth colors & statuses**
   - Pre-defined color palette
   - Customizable per clinic
   - JSON structure for flexibility

4. ✅ **Clean architecture**
   - Separate controllers for each level
   - Service layer for sync logic
   - Repository pattern for data access

5. ✅ **Complete API**
   - CRUD for setting definitions (Super Admin)
   - Read/Update for clinic settings (Doctors)
   - Grouped by category for UI

**Result:** Doctors can customize tooth colors and other settings while Super Admin controls what settings are available system-wide! 🎉
