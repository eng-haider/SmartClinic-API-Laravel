# Recipes API Documentation

## Overview

Medical prescription management system for creating and tracking patient medications.

**Base URL:** `http://localhost:8000/api/recipes`

**Authentication:** Required (Bearer Token)

---

## Endpoints

### 1. List All Recipes

Get a paginated list of medical recipes/prescriptions.

**Endpoint:** `GET /api/recipes`

**Authentication:** Required

**Request Headers:**

```
Content-Type: application/json
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| per_page | integer | Items per page (default: 15) | `per_page=20` |
| page | integer | Page number (default: 1) | `page=2` |
| filter[patient_id] | integer | Filter by patient ID | `filter[patient_id]=1` |
| filter[doctors_id] | integer | Filter by doctor ID | `filter[doctors_id]=2` |
| sort | string | Sort field | `sort=-created_at` |
| include | string | Load relationships | `include=patient,doctor,recipeItems` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Recipes retrieved successfully",
  "data": [
    {
      "id": 1,
      "patient_id": 1,
      "doctors_id": 2,
      "notes": "Post-surgery prescription",
      "patient": {
        "id": 1,
        "name": "John Doe",
        "phone": "01001234567"
      },
      "doctor": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "recipe_items": [
        {
          "id": 1,
          "recipe_id": 1,
          "medication_name": "Amoxicillin",
          "dosage": "500mg",
          "frequency": "3 times daily",
          "duration": "7 days",
          "created_at": "2026-01-15T10:00:00.000000Z"
        },
        {
          "id": 2,
          "recipe_id": 1,
          "medication_name": "Ibuprofen",
          "dosage": "400mg",
          "frequency": "As needed",
          "duration": "5 days",
          "created_at": "2026-01-15T10:00:00.000000Z"
        }
      ],
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z"
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 4,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

Get all recipes:

```bash
curl -X GET http://localhost:8000/api/recipes \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get patient's recipes:

```bash
curl -X GET "http://localhost:8000/api/recipes?filter[patient_id]=1&include=recipeItems" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/recipes`
- Params: Add query parameters as needed
- Authorization: Bearer Token

---

### 2. Get Single Recipe

Retrieve details of a specific recipe with all medications.

**Endpoint:** `GET /api/recipes/{id}`

**Authentication:** Required

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Recipe ID |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Recipe retrieved successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "doctors_id": 2,
    "notes": "Post-surgery prescription",
    "patient": {
      "id": 1,
      "name": "John Doe",
      "phone": "01001234567",
      "age": 30
    },
    "doctor": {
      "id": 2,
      "name": "Dr. Ahmed Hassan",
      "role": "doctor"
    },
    "recipe_items": [
      {
        "id": 1,
        "medication_name": "Amoxicillin",
        "dosage": "500mg",
        "frequency": "3 times daily",
        "duration": "7 days"
      },
      {
        "id": 2,
        "medication_name": "Ibuprofen",
        "dosage": "400mg",
        "frequency": "As needed for pain",
        "duration": "5 days"
      }
    ],
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Recipe not found"
}
```

**cURL Example:**

```bash
curl -X GET http://localhost:8000/api/recipes/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: GET
- URL: `{{base_url}}/recipes/{{recipe_id}}`
- Authorization: Bearer Token

---

### 3. Create Recipe

Create a new medical prescription with medications.

**Endpoint:** `POST /api/recipes`

**Authentication:** Required

**Request Body:**

```json
{
  "patient_id": 1,
  "doctors_id": 2,
  "notes": "Post-surgery prescription - Take with food",
  "recipe_items": [
    {
      "medication_name": "Amoxicillin",
      "dosage": "500mg",
      "frequency": "3 times daily",
      "duration": "7 days"
    },
    {
      "medication_name": "Ibuprofen",
      "dosage": "400mg",
      "frequency": "Every 6 hours as needed",
      "duration": "5 days"
    },
    {
      "medication_name": "Vitamin C",
      "dosage": "1000mg",
      "frequency": "Once daily",
      "duration": "14 days"
    }
  ]
}
```

**Field Descriptions:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| patient_id | integer | Yes | Patient ID (must exist) |
| doctors_id | integer | Yes | Doctor/User ID (must exist) |
| notes | string | No | General prescription notes |
| recipe_items | array | Yes | Array of medications (min: 1) |
| recipe_items._.medication_name | string | Yes | Name of medication |
| recipe_items._.dosage | string | Yes | Dosage amount (e.g., "500mg") |
| recipe_items._.frequency | string | Yes | How often to take |
| recipe_items._.duration | string | Yes | How long to take |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Recipe created successfully",
  "data": {
    "id": 1,
    "patient_id": 1,
    "doctors_id": 2,
    "notes": "Post-surgery prescription - Take with food",
    "recipe_items": [
      {
        "id": 1,
        "medication_name": "Amoxicillin",
        "dosage": "500mg",
        "frequency": "3 times daily",
        "duration": "7 days"
      }
    ],
    "created_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "patient_id": ["The patient id field is required."],
    "recipe_items": ["The recipe items field must contain at least 1 item."],
    "recipe_items.0.medication_name": ["The medication name field is required."]
  }
}
```

**cURL Example:**

```bash
curl -X POST http://localhost:8000/api/recipes \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "patient_id": 1,
    "doctors_id": 2,
    "notes": "Post-surgery prescription",
    "recipe_items": [
      {
        "medication_name": "Amoxicillin",
        "dosage": "500mg",
        "frequency": "3 times daily",
        "duration": "7 days"
      }
    ]
  }'
```

**Postman:**

- Method: POST
- URL: `{{base_url}}/recipes`
- Body (raw JSON): See request body above
- Authorization: Bearer Token
- Tests Script:

```javascript
if (pm.response.code === 201) {
  pm.environment.set("recipe_id", pm.response.json().data.id);
}
```

---

### 4. Update Recipe

Update an existing recipe and its medications.

**Endpoint:** `PUT /api/recipes/{id}`

**Authentication:** Required

**Request Body:**

```json
{
  "notes": "Updated prescription notes - Patient allergic to penicillin",
  "recipe_items": [
    {
      "medication_name": "Azithromycin",
      "dosage": "500mg",
      "frequency": "Once daily",
      "duration": "5 days"
    },
    {
      "medication_name": "Ibuprofen",
      "dosage": "400mg",
      "frequency": "Every 6 hours",
      "duration": "5 days"
    }
  ]
}
```

**Note:** When updating recipe_items, all items are replaced. Include all medications you want to keep.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Recipe updated successfully",
  "data": {
    "id": 1,
    "notes": "Updated prescription notes - Patient allergic to penicillin",
    "recipe_items": [
      {
        "id": 3,
        "medication_name": "Azithromycin",
        "dosage": "500mg",
        "frequency": "Once daily",
        "duration": "5 days"
      }
    ],
    "updated_at": "2026-01-15T11:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X PUT http://localhost:8000/api/recipes/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "notes": "Updated prescription",
    "recipe_items": [
      {
        "medication_name": "Azithromycin",
        "dosage": "500mg",
        "frequency": "Once daily",
        "duration": "5 days"
      }
    ]
  }'
```

**Postman:**

- Method: PUT
- URL: `{{base_url}}/recipes/{{recipe_id}}`
- Body (raw JSON): See request body above
- Authorization: Bearer Token

---

### 5. Delete Recipe

Delete a recipe and all its medications.

**Endpoint:** `DELETE /api/recipes/{id}`

**Authentication:** Required

**Success Response (200):**

```json
{
  "success": true,
  "message": "Recipe deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Recipe not found"
}
```

**cURL Example:**

```bash
curl -X DELETE http://localhost:8000/api/recipes/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Postman:**

- Method: DELETE
- URL: `{{base_url}}/recipes/{{recipe_id}}`
- Authorization: Bearer Token

---

## Advanced Filtering Examples

### Get Patient's Prescription History

```bash
GET /recipes?filter[patient_id]=1&include=recipeItems,doctor&sort=-created_at
```

### Get Doctor's Prescriptions

```bash
GET /recipes?filter[doctors_id]=2&include=patient,recipeItems&sort=-created_at&per_page=20
```

### Recent Prescriptions

```bash
GET /recipes?sort=-created_at&per_page=10&include=patient,doctor,recipeItems
```

---

## Frontend Integration Example

### React Service

```javascript
import api from "./api";

export const recipeService = {
  async getAll(params = {}) {
    return await api.get("/recipes", { params });
  },

  async getById(id) {
    return await api.get(`/recipes/${id}`);
  },

  async create(recipeData) {
    return await api.post("/recipes", recipeData);
  },

  async update(id, recipeData) {
    return await api.put(`/recipes/${id}`, recipeData);
  },

  async delete(id) {
    return await api.delete(`/recipes/${id}`);
  },

  async getPatientRecipes(patientId) {
    return await api.get("/recipes", {
      params: {
        "filter[patient_id]": patientId,
        include: "recipeItems,doctor",
        sort: "-created_at",
      },
    });
  },

  async print(id) {
    // Download or open printable prescription
    return await api.get(`/recipes/${id}/print`, {
      responseType: "blob",
    });
  },
};
```

### React Component - Create Prescription

```jsx
import { useState } from "react";
import { recipeService } from "../services/recipeService";

export default function CreatePrescription({ patientId, doctorId, onSuccess }) {
  const [notes, setNotes] = useState("");
  const [medications, setMedications] = useState([
    { medication_name: "", dosage: "", frequency: "", duration: "" },
  ]);
  const [loading, setLoading] = useState(false);

  const addMedication = () => {
    setMedications([
      ...medications,
      { medication_name: "", dosage: "", frequency: "", duration: "" },
    ]);
  };

  const updateMedication = (index, field, value) => {
    const updated = [...medications];
    updated[index][field] = value;
    setMedications(updated);
  };

  const removeMedication = (index) => {
    setMedications(medications.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await recipeService.create({
        patient_id: patientId,
        doctors_id: doctorId,
        notes,
        recipe_items: medications,
      });

      alert("Prescription created successfully!");
      onSuccess(response.data);
    } catch (error) {
      alert("Error creating prescription: " + error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <h3>Create Prescription</h3>

      <div>
        <label>Prescription Notes:</label>
        <textarea
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="General instructions..."
          rows="3"
        />
      </div>

      <h4>Medications</h4>
      {medications.map((med, index) => (
        <div key={index} className="medication-row">
          <input
            type="text"
            placeholder="Medication Name"
            value={med.medication_name}
            onChange={(e) =>
              updateMedication(index, "medication_name", e.target.value)
            }
            required
          />
          <input
            type="text"
            placeholder="Dosage (e.g., 500mg)"
            value={med.dosage}
            onChange={(e) => updateMedication(index, "dosage", e.target.value)}
            required
          />
          <input
            type="text"
            placeholder="Frequency (e.g., 3 times daily)"
            value={med.frequency}
            onChange={(e) =>
              updateMedication(index, "frequency", e.target.value)
            }
            required
          />
          <input
            type="text"
            placeholder="Duration (e.g., 7 days)"
            value={med.duration}
            onChange={(e) =>
              updateMedication(index, "duration", e.target.value)
            }
            required
          />
          {medications.length > 1 && (
            <button type="button" onClick={() => removeMedication(index)}>
              Remove
            </button>
          )}
        </div>
      ))}

      <button type="button" onClick={addMedication}>
        + Add Medication
      </button>

      <button type="submit" disabled={loading}>
        {loading ? "Creating..." : "Create Prescription"}
      </button>
    </form>
  );
}
```

### Vue Component - Patient Prescription History

```vue
<template>
  <div>
    <h2>Prescription History</h2>

    <div v-if="loading">Loading...</div>

    <div v-else class="recipes-list">
      <div v-for="recipe in recipes" :key="recipe.id" class="recipe-card">
        <div class="recipe-header">
          <span class="date">{{ formatDate(recipe.created_at) }}</span>
          <span class="doctor">Prescribed by: {{ recipe.doctor.name }}</span>
        </div>

        <div class="notes" v-if="recipe.notes">
          <strong>Notes:</strong> {{ recipe.notes }}
        </div>

        <div class="medications">
          <h4>Medications:</h4>
          <table>
            <thead>
              <tr>
                <th>Medication</th>
                <th>Dosage</th>
                <th>Frequency</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in recipe.recipe_items" :key="item.id">
                <td>{{ item.medication_name }}</td>
                <td>{{ item.dosage }}</td>
                <td>{{ item.frequency }}</td>
                <td>{{ item.duration }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="actions">
          <button @click="printPrescription(recipe.id)">Print</button>
          <button @click="editPrescription(recipe.id)">Edit</button>
          <button @click="deletePrescription(recipe.id)" class="danger">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { recipeService } from "../services/recipeService";

const props = defineProps({
  patientId: {
    type: Number,
    required: true,
  },
});

const recipes = ref([]);
const loading = ref(false);

const fetchRecipes = async () => {
  loading.value = true;
  try {
    const response = await recipeService.getPatientRecipes(props.patientId);
    recipes.value = response.data;
  } catch (error) {
    console.error("Error fetching recipes:", error);
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString();
};

const printPrescription = async (id) => {
  try {
    await recipeService.print(id);
  } catch (error) {
    console.error("Error printing prescription:", error);
  }
};

const editPrescription = (id) => {
  // Navigate to edit page or open modal
  console.log("Edit recipe:", id);
};

const deletePrescription = async (id) => {
  if (confirm("Delete this prescription?")) {
    await recipeService.delete(id);
    await fetchRecipes();
  }
};

onMounted(() => {
  fetchRecipes();
});
</script>
```

---

## Validation Rules

### Recipe

| Field        | Rules                        |
| ------------ | ---------------------------- |
| patient_id   | required, exists:patients,id |
| doctors_id   | required, exists:users,id    |
| notes        | nullable, string, max:5000   |
| recipe_items | required, array, min:1       |

### Recipe Items

| Field           | Rules                     |
| --------------- | ------------------------- |
| medication_name | required, string, max:255 |
| dosage          | required, string, max:100 |
| frequency       | required, string, max:255 |
| duration        | required, string, max:100 |

---

## Business Logic

### Recipe Management

- A recipe must have at least one medication
- When updating, all recipe_items are replaced
- Deleting a recipe also deletes all its medications (cascade)

### Common Medication Patterns

**Dosage Examples:**

- "500mg", "1 tablet", "5ml", "2 capsules"

**Frequency Examples:**

- "3 times daily", "Every 6 hours", "Once daily", "As needed", "Before meals"

**Duration Examples:**

- "7 days", "2 weeks", "Until finished", "1 month"

---

## Common Use Cases

### 1. Create Simple Prescription

```javascript
const recipe = await recipeService.create({
  patient_id: 1,
  doctors_id: 2,
  notes: "Take with food",
  recipe_items: [
    {
      medication_name: "Amoxicillin",
      dosage: "500mg",
      frequency: "3 times daily",
      duration: "7 days",
    },
  ],
});
```

### 2. Create Complex Prescription

```javascript
const recipe = await recipeService.create({
  patient_id: 1,
  doctors_id: 2,
  notes: "Post-operative care. Call if symptoms persist.",
  recipe_items: [
    {
      medication_name: "Amoxicillin",
      dosage: "500mg",
      frequency: "3 times daily with meals",
      duration: "10 days",
    },
    {
      medication_name: "Ibuprofen",
      dosage: "400mg",
      frequency: "Every 6 hours as needed for pain",
      duration: "5 days",
    },
    {
      medication_name: "Omeprazole",
      dosage: "20mg",
      frequency: "Once daily before breakfast",
      duration: "14 days",
    },
  ],
});
```

### 3. View Patient History

```javascript
const history = await recipeService.getPatientRecipes(patientId);
```

---

## Notes

- Recipes do not use soft deletes - they are permanently deleted
- Each recipe can have multiple medications (recipe_items)
- All recipe_items are automatically deleted when a recipe is deleted
- No price or payment tracking for recipes
- Ideal for generating printable prescriptions
- Store detailed instructions in the notes field

---

**Last Updated:** January 15, 2026  
**API Version:** 1.0
