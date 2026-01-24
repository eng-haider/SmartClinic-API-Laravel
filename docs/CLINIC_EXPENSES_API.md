# Clinic Expenses & Categories API Documentation

## 📋 Overview

This API manages clinic expenses and their categories. The system provides complete CRUD operations with advanced filtering, statistics, and payment tracking capabilities.

**Base URLs:**

- Expenses: `http://localhost:8000/api/clinic-expenses`
- Categories: `http://localhost:8000/api/clinic-expense-categories`

**Authentication:** Required (Bearer Token)

### 🔒 Important: Automatic Field Population

**The following fields are automatically populated from the authenticated user and should NOT be sent in requests:**

- **`clinic_id`**: Automatically set from the logged-in user's clinic
- **`doctor_id`**: Automatically set from the logged-in user's ID (for expenses only)

This ensures data integrity and prevents users from creating expenses for other clinics or impersonating other doctors.

---

## 🎯 Frontend Implementation Guide for Copilot

### UI/UX Requirements

#### **Main Layout Structure:**

Create a **modern expense management dashboard** with the following sections:

1. **Category Cards Section (Top)**

   - Display expense categories as interactive cards/blocks
   - Each card shows:
     - Category name
     - Category description (if available)
     - Total expenses in this category
     - Total amount spent in this category
     - Number of unpaid expenses
     - Active/Inactive status indicator (badge/chip)
   - Cards should be:
     - Clickable/selectable
     - Visually distinct when selected
     - Responsive (grid layout: 3-4 cards per row on desktop, 1-2 on mobile)
     - Color-coded by status (active=green accent, inactive=gray)

2. **Expenses List Section (Below Categories)**

   - Shows expenses filtered by selected category
   - Display when a category is clicked
   - Table or card layout with columns:
     - Expense name
     - Quantity
     - Price per unit
     - Total amount (quantity × price)
     - Date
     - Payment status (Paid/Unpaid badge)
     - Doctor name (who created it)
     - Actions (Edit, Delete, Mark as Paid/Unpaid)
   - Include:
     - Search bar
     - Date range filter
     - Payment status filter (All, Paid, Unpaid)
     - Sort options (date, amount, name)
     - Pagination

3. **Action Buttons:**

   - **Add Category Button** (floating or in header)
   - **Add Expense Button** (appears when a category is selected)
     - This button should be prominent and clearly associated with the selected category
   - **Export/Print Button** for reports

4. **Statistics Dashboard (Optional but Recommended):**
   - Summary cards showing:
     - Total expenses (current month)
     - Total paid amount
     - Total unpaid amount
     - Number of expense categories
   - Charts:
     - Pie chart showing expenses by category
     - Line chart showing expenses over time
     - Bar chart comparing paid vs unpaid by category

#### **Modal/Drawer Forms:**

1. **Add/Edit Category Form:**

   - Fields:
     - Name (required)
     - Description (optional, textarea)
     - Active status (toggle/switch)
   - Validation messages
   - Submit and Cancel buttons

2. **Add/Edit Expense Form:**
   - Fields:
     - Category (dropdown, pre-selected if opened from category)
     - Name (required)
     - Quantity (number, default: 1)
     - Price (required, currency input)
     - Date (date picker, default: today)
     - Payment status (checkbox or toggle)
     - Doctor (dropdown, optional)
   - Show calculated total (quantity × price)
   - Validation messages
   - Submit and Cancel buttons

#### **Color Scheme & Design:**

- Use professional medical/clinic colors (blues, greens, white)
- Status badges:
  - Paid: Green (#10B981)
  - Unpaid: Red/Orange (#EF4444 or #F59E0B)
  - Active: Blue (#3B82F6)
  - Inactive: Gray (#6B7280)
- Icons: Use medical/financial icons (💰, 📊, 📝, ✅, ❌)

#### **User Flow:**

1. User lands on expense management page
2. Sees all category cards with overview stats
3. Clicks on a category card
4. Category card highlights/expands
5. Expenses list appears below showing that category's expenses
6. "Add Expense" button becomes active
7. User can add expense to that category
8. User can mark expenses as paid/unpaid directly from list
9. User can filter, search, and sort expenses

#### **Additional Features (Nice to Have):**

- Drag and drop to reorder categories
- Bulk actions (delete multiple, mark multiple as paid)
- Quick stats on hover over category cards
- Recent expenses widget
- Month/Year selector for filtering
- Export to PDF/Excel
- Print receipt/report functionality

---

## 📂 Expense Categories Endpoints

### 1. List All Expense Categories

Get a paginated list of expense categories with filtering.

**Endpoint:** `GET /api/clinic-expense-categories`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

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
| search | string | Search in name, description | `search=utilities` |
| filter[is_active] | boolean | Filter by active status | `filter[is_active]=1` |
| filter[clinic_id] | integer | Filter by clinic (super-admin only) | `filter[clinic_id]=1` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-created_at` |
| include | string | Include relationships (expenses) | `include=expenses` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Utilities",
      "description": "Electricity, water, internet bills",
      "is_active": true,
      "clinic": {
        "id": 1,
        "name": "Main Clinic"
      },
      "expenses_count": 15,
      "total_expenses_amount": 5000.0,
      "creator": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z"
    },
    {
      "id": 2,
      "name": "Medical Supplies",
      "description": "Gloves, masks, syringes",
      "is_active": true,
      "clinic": {
        "id": 1,
        "name": "Main Clinic"
      },
      "expenses_count": 23,
      "total_expenses_amount": 12000.0,
      "creator": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "created_at": "2026-01-10T10:00:00.000000Z",
      "updated_at": "2026-01-10T10:00:00.000000Z"
    }
  ],
  "pagination": {
    "total": 8,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 8
  }
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expense-categories?per_page=20&include=expenses" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 2. Get Active Expense Categories

Get only active expense categories for the current clinic (useful for dropdowns).

**Endpoint:** `GET /api/clinic-expense-categories-active`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Active expense categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Utilities",
      "description": "Electricity, water, internet bills",
      "is_active": true
    },
    {
      "id": 2,
      "name": "Medical Supplies",
      "description": "Gloves, masks, syringes",
      "is_active": true
    }
  ]
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expense-categories-active" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 3. Create Expense Category

Create a new expense category.

**Endpoint:** `POST /api/clinic-expense-categories`

**Authentication:** Required

**Permissions:** `create-expense`

**Request Body:**

```json
{
  "name": "Office Supplies",
  "description": "Paper, pens, printer ink, etc.",
  "is_active": true
}
```

**Note:** `clinic_id` is automatically set from the authenticated user's clinic. You don't need to send it in the request.

**Validation Rules:**
| Field | Type | Required | Rules |
|-------|------|----------|-------|
| name | string | Yes | max:255 |
| description | text | No | max:1000 |
| is_active | boolean | No | default: true |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Expense category created successfully",
  "data": {
    "id": 3,
    "name": "Office Supplies",
    "description": "Paper, pens, printer ink, etc.",
    "is_active": true,
    "clinic": {
      "id": 1,
      "name": "Main Clinic"
    },
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "created_at": "2026-01-24T14:30:00.000000Z",
    "updated_at": "2026-01-24T14:30:00.000000Z"
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "The name field is required."
}
```

**cURL Example:**

```bash
curl -X POST "http://localhost:8000/api/clinic-expense-categories" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Office Supplies",
    "description": "Paper, pens, printer ink, etc.",
    "is_active": true
  }'
```

---

### 4. Get Single Expense Category

Retrieve a specific expense category by ID.

**Endpoint:** `GET /api/clinic-expense-categories/{id}`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense category retrieved successfully",
  "data": {
    "id": 1,
    "name": "Utilities",
    "description": "Electricity, water, internet bills",
    "is_active": true,
    "clinic": {
      "id": 1,
      "name": "Main Clinic"
    },
    "expenses_count": 15,
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "updator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Expense category not found"
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expense-categories/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 5. Update Expense Category

Update an existing expense category.

**Endpoint:** `PUT /api/clinic-expense-categories/{id}`

**Authentication:** Required

**Permissions:** `edit-expense`

**Request Body:**

```json
{
  "name": "Utilities & Bills",
  "description": "Electricity, water, internet, and phone bills",
  "is_active": true
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense category updated successfully",
  "data": {
    "id": 1,
    "name": "Utilities & Bills",
    "description": "Electricity, water, internet, and phone bills",
    "is_active": true,
    "clinic": {
      "id": 1,
      "name": "Main Clinic"
    },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-24T15:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X PUT "http://localhost:8000/api/clinic-expense-categories/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Utilities & Bills",
    "description": "Electricity, water, internet, and phone bills",
    "is_active": true
  }'
```

---

### 6. Delete Expense Category

Soft delete an expense category.

**Endpoint:** `DELETE /api/clinic-expense-categories/{id}`

**Authentication:** Required

**Permissions:** `delete-expense`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense category deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "Expense category not found"
}
```

**cURL Example:**

```bash
curl -X DELETE "http://localhost:8000/api/clinic-expense-categories/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## 💰 Clinic Expenses Endpoints

### 1. List All Expenses

Get a paginated list of clinic expenses with advanced filtering.

**Endpoint:** `GET /api/clinic-expenses`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

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
| search | string | Search in name | `search=electricity` |
| filter[clinic_expense_category_id] | integer | Filter by category | `filter[clinic_expense_category_id]=1` |
| filter[is_paid] | boolean | Filter by payment status | `filter[is_paid]=0` |
| filter[clinic_id] | integer | Filter by clinic (super-admin only) | `filter[clinic_id]=1` |
| filter[doctor_id] | integer | Filter by doctor | `filter[doctor_id]=2` |
| filter[date_from] | date | Start date (YYYY-MM-DD) | `filter[date_from]=2026-01-01` |
| filter[date_to] | date | End date (YYYY-MM-DD) | `filter[date_to]=2026-01-31` |
| sort | string | Sort field (prefix with `-` for desc) | `sort=-date` |
| include | string | Include relationships | `include=category,doctor,clinic` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expenses retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Electricity Bill - January",
      "quantity": 1,
      "price": 500.0,
      "total": 500.0,
      "date": "2026-01-15",
      "is_paid": true,
      "category": {
        "id": 1,
        "name": "Utilities"
      },
      "clinic": {
        "id": 1,
        "name": "Main Clinic"
      },
      "doctor": {
        "id": 2,
        "name": "Dr. Ahmed Hassan",
        "phone": "01009876543"
      },
      "creator": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "created_at": "2026-01-15T10:00:00.000000Z",
      "updated_at": "2026-01-15T10:00:00.000000Z"
    },
    {
      "id": 2,
      "name": "Medical Gloves",
      "quantity": 100,
      "price": 2.5,
      "total": 250.0,
      "date": "2026-01-20",
      "is_paid": false,
      "category": {
        "id": 2,
        "name": "Medical Supplies"
      },
      "clinic": {
        "id": 1,
        "name": "Main Clinic"
      },
      "doctor": null,
      "creator": {
        "id": 2,
        "name": "Dr. Ahmed Hassan"
      },
      "created_at": "2026-01-20T14:30:00.000000Z",
      "updated_at": "2026-01-20T14:30:00.000000Z"
    }
  ],
  "pagination": {
    "total": 45,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3,
    "from": 1,
    "to": 15
  }
}
```

**cURL Examples:**

Get all expenses:

```bash
curl -X GET "http://localhost:8000/api/clinic-expenses" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

Get unpaid expenses in a specific category:

```bash
curl -X GET "http://localhost:8000/api/clinic-expenses?filter[clinic_expense_category_id]=1&filter[is_paid]=0" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 2. Create Expense

Create a new clinic expense.

**Endpoint:** `POST /api/clinic-expenses`

**Authentication:** Required

**Permissions:** `create-expense`

**Request Body:**

```json
{
  "name": "Internet Bill - January",
  "quantity": 1,
  "price": 300.0,
  "clinic_expense_category_id": 1,
  "date": "2026-01-24",
  "is_paid": false
}
```

**Note:** `clinic_id` and `doctor_id` are automatically set from the authenticated user. You don't need to send them in the request.

**Validation Rules:**
| Field | Type | Required | Rules |
|-------|------|----------|-------|
| name | string | Yes | max:255 |
| quantity | integer | No | min:1, default: 1 |
| price | decimal | Yes | min:0 |
| clinic_expense_category_id | integer | No | exists in clinic_expense_categories |
| date | date | Yes | format: Y-m-d |
| is_paid | boolean | No | default: false |

**Success Response (201):**

```json
{
  "success": true,
  "message": "Expense created successfully",
  "data": {
    "id": 3,
    "name": "Internet Bill - January",
    "quantity": 1,
    "price": 300.0,
    "total": 300.0,
    "date": "2026-01-24",
    "is_paid": false,
    "category": {
      "id": 1,
      "name": "Utilities"
    },
    "clinic": {
      "id": 1,
      "name": "Main Clinic"
    },
    "doctor": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "created_at": "2026-01-24T16:00:00.000000Z",
    "updated_at": "2026-01-24T16:00:00.000000Z"
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "The price field is required."
}
```

**cURL Example:**

```bash
curl -X POST "http://localhost:8000/api/clinic-expenses" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Internet Bill - January",
    "quantity": 1,
    "price": 300.00,
    "clinic_expense_category_id": 1,
    "date": "2026-01-24",
    "is_paid": false
  }'
```

---

### 3. Get Single Expense

Retrieve a specific expense by ID.

**Endpoint:** `GET /api/clinic-expenses/{id}`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense retrieved successfully",
  "data": {
    "id": 1,
    "name": "Electricity Bill - January",
    "quantity": 1,
    "price": 500.0,
    "total": 500.0,
    "date": "2026-01-15",
    "is_paid": true,
    "category": {
      "id": 1,
      "name": "Utilities",
      "description": "Electricity, water, internet bills"
    },
    "clinic": {
      "id": 1,
      "name": "Main Clinic"
    },
    "doctor": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "creator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "updator": {
      "id": 2,
      "name": "Dr. Ahmed Hassan"
    },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expenses/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 4. Update Expense

Update an existing expense.

**Endpoint:** `PUT /api/clinic-expenses/{id}`

**Authentication:** Required

**Permissions:** `edit-expense`

**Request Body:**

```json
{
  "name": "Electricity Bill - January (Updated)",
  "quantity": 1,
  "price": 550.0,
  "clinic_expense_category_id": 1,
  "date": "2026-01-15",
  "is_paid": true
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense updated successfully",
  "data": {
    "id": 1,
    "name": "Electricity Bill - January (Updated)",
    "quantity": 1,
    "price": 550.0,
    "total": 550.0,
    "date": "2026-01-15",
    "is_paid": true,
    "category": {
      "id": 1,
      "name": "Utilities"
    },
    "created_at": "2026-01-15T10:00:00.000000Z",
    "updated_at": "2026-01-24T16:30:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X PUT "http://localhost:8000/api/clinic-expenses/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Electricity Bill - January (Updated)",
    "quantity": 1,
    "price": 550.00,
    "clinic_expense_category_id": 1,
    "date": "2026-01-15",
    "is_paid": true
  }'
```

---

### 5. Delete Expense

Soft delete an expense.

**Endpoint:** `DELETE /api/clinic-expenses/{id}`

**Authentication:** Required

**Permissions:** `delete-expense`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense deleted successfully"
}
```

**cURL Example:**

```bash
curl -X DELETE "http://localhost:8000/api/clinic-expenses/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 6. Mark Expense as Paid

Mark a specific expense as paid.

**Endpoint:** `PATCH /api/clinic-expenses/{id}/mark-paid`

**Authentication:** Required

**Permissions:** `edit-expense`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense marked as paid",
  "data": {
    "id": 2,
    "name": "Medical Gloves",
    "quantity": 100,
    "price": 2.5,
    "total": 250.0,
    "is_paid": true,
    "date": "2026-01-20",
    "updated_at": "2026-01-24T17:00:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X PATCH "http://localhost:8000/api/clinic-expenses/2/mark-paid" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 7. Mark Expense as Unpaid

Mark a specific expense as unpaid.

**Endpoint:** `PATCH /api/clinic-expenses/{id}/mark-unpaid`

**Authentication:** Required

**Permissions:** `edit-expense`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense marked as unpaid",
  "data": {
    "id": 2,
    "name": "Medical Gloves",
    "quantity": 100,
    "price": 2.5,
    "total": 250.0,
    "is_paid": false,
    "date": "2026-01-20",
    "updated_at": "2026-01-24T17:05:00.000000Z"
  }
}
```

**cURL Example:**

```bash
curl -X PATCH "http://localhost:8000/api/clinic-expenses/2/mark-unpaid" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 8. Get Expense Statistics

Get comprehensive statistics about expenses for a clinic.

**Endpoint:** `GET /api/clinic-expenses-statistics`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| clinic_id | integer | Clinic ID (required for super-admin) | `clinic_id=1` |
| start_date | date | Start date (YYYY-MM-DD) | `start_date=2026-01-01` |
| end_date | date | End date (YYYY-MM-DD) | `end_date=2026-01-31` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expense statistics retrieved successfully",
  "data": {
    "total_expenses": 45,
    "total_amount": 15750.0,
    "paid_amount": 10500.0,
    "unpaid_amount": 5250.0,
    "paid_count": 30,
    "unpaid_count": 15,
    "by_category": [
      {
        "category_id": 1,
        "category_name": "Utilities",
        "total_amount": 5000.0,
        "count": 15
      },
      {
        "category_id": 2,
        "category_name": "Medical Supplies",
        "total_amount": 10750.0,
        "count": 30
      }
    ],
    "period": {
      "start_date": "2026-01-01",
      "end_date": "2026-01-31"
    }
  }
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expenses-statistics?start_date=2026-01-01&end_date=2026-01-31" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 9. Get Unpaid Expenses

Get all unpaid expenses for a clinic.

**Endpoint:** `GET /api/clinic-expenses-unpaid`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

**Query Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| clinic_id | integer | Clinic ID (required for super-admin) | `clinic_id=1` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Unpaid expenses retrieved successfully",
  "data": [
    {
      "id": 2,
      "name": "Medical Gloves",
      "quantity": 100,
      "price": 2.5,
      "total": 250.0,
      "date": "2026-01-20",
      "is_paid": false,
      "category": {
        "id": 2,
        "name": "Medical Supplies"
      }
    },
    {
      "id": 4,
      "name": "Office Paper",
      "quantity": 10,
      "price": 50.0,
      "total": 500.0,
      "date": "2026-01-22",
      "is_paid": false,
      "category": {
        "id": 3,
        "name": "Office Supplies"
      }
    }
  ]
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expenses-unpaid" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

### 10. Get Expenses by Date Range

Get expenses within a specific date range.

**Endpoint:** `GET /api/clinic-expenses-by-date-range`

**Authentication:** Required

**Permissions:** `view-clinic-expenses`

**Query Parameters:**
| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| start_date | date | Yes | Start date (YYYY-MM-DD) | `start_date=2026-01-01` |
| end_date | date | Yes | End date (YYYY-MM-DD) | `end_date=2026-01-31` |

**Success Response (200):**

```json
{
  "success": true,
  "message": "Expenses retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Electricity Bill - January",
      "quantity": 1,
      "price": 500.0,
      "total": 500.0,
      "date": "2026-01-15",
      "is_paid": true,
      "category": {
        "id": 1,
        "name": "Utilities"
      }
    },
    {
      "id": 2,
      "name": "Medical Gloves",
      "quantity": 100,
      "price": 2.5,
      "total": 250.0,
      "date": "2026-01-20",
      "is_paid": false,
      "category": {
        "id": 2,
        "name": "Medical Supplies"
      }
    }
  ]
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "The start date field is required."
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/clinic-expenses-by-date-range?start_date=2026-01-01&end_date=2026-01-31" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## 🔐 Authentication & Permissions

### Required Permissions

| Action                   | Permission             |
| ------------------------ | ---------------------- |
| View expenses/categories | `view-clinic-expenses` |
| Create expense/category  | `create-expense`       |
| Edit expense/category    | `edit-expense`         |
| Delete expense/category  | `delete-expense`       |

### Role-Based Access

- **Super Admin**: Can view/manage all clinics' expenses
- **Clinic Admin/Doctor**: Can only view/manage their own clinic's expenses
- **Other Roles**: Need specific permissions assigned

---

## 📊 Data Models

### Expense Category Model

```json
{
  "id": 1,
  "name": "Utilities",
  "description": "Electricity, water, internet bills",
  "is_active": true,
  "clinic_id": 1,
  "creator_id": 2,
  "updator_id": 2,
  "created_at": "2026-01-15T10:00:00.000000Z",
  "updated_at": "2026-01-15T10:00:00.000000Z",
  "deleted_at": null
}
```

### Expense Model

```json
{
  "id": 1,
  "name": "Electricity Bill - January",
  "quantity": 1,
  "price": 500.0,
  "clinic_expense_category_id": 1,
  "clinic_id": 1,
  "date": "2026-01-15",
  "is_paid": true,
  "doctor_id": 2,
  "creator_id": 2,
  "updator_id": 2,
  "created_at": "2026-01-15T10:00:00.000000Z",
  "updated_at": "2026-01-15T10:00:00.000000Z",
  "deleted_at": null
}
```

---

## 🎨 Frontend Implementation Examples

### React/Next.js Example (Category Cards)

```jsx
// ExpenseCategoriesGrid.jsx
import { useState } from "react";
import { Card, Badge, Button } from "your-ui-library";

export default function ExpenseCategoriesGrid({
  categories,
  onSelectCategory,
}) {
  const [selectedId, setSelectedId] = useState(null);

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      {categories.map((category) => (
        <Card
          key={category.id}
          className={`cursor-pointer transition-all ${
            selectedId === category.id ? "ring-2 ring-blue-500 shadow-lg" : ""
          }`}
          onClick={() => {
            setSelectedId(category.id);
            onSelectCategory(category);
          }}
        >
          <div className="flex justify-between items-start mb-2">
            <h3 className="text-lg font-semibold">{category.name}</h3>
            <Badge color={category.is_active ? "green" : "gray"}>
              {category.is_active ? "Active" : "Inactive"}
            </Badge>
          </div>

          <p className="text-sm text-gray-600 mb-3">{category.description}</p>

          <div className="grid grid-cols-2 gap-2 text-sm">
            <div>
              <span className="text-gray-500">Total Expenses:</span>
              <p className="font-bold">{category.expenses_count || 0}</p>
            </div>
            <div>
              <span className="text-gray-500">Total Amount:</span>
              <p className="font-bold text-green-600">
                ${category.total_expenses_amount?.toFixed(2) || "0.00"}
              </p>
            </div>
          </div>
        </Card>
      ))}
    </div>
  );
}
```

### API Service Example

```javascript
// services/expenseService.js
import axios from "axios";

const API_BASE_URL = "http://localhost:8000/api";

export const expenseService = {
  // Categories
  async getCategories(params = {}) {
    const response = await axios.get(
      `${API_BASE_URL}/clinic-expense-categories`,
      {
        params,
        headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
      }
    );
    return response.data;
  },

  async getActiveCategories() {
    const response = await axios.get(
      `${API_BASE_URL}/clinic-expense-categories-active`,
      {
        headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
      }
    );
    return response.data;
  },

  async createCategory(data) {
    const response = await axios.post(
      `${API_BASE_URL}/clinic-expense-categories`,
      data,
      {
        headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
      }
    );
    return response.data;
  },

  // Expenses
  async getExpenses(params = {}) {
    const response = await axios.get(`${API_BASE_URL}/clinic-expenses`, {
      params,
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    });
    return response.data;
  },

  async createExpense(data) {
    const response = await axios.post(`${API_BASE_URL}/clinic-expenses`, data, {
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    });
    return response.data;
  },

  async markAsPaid(id) {
    const response = await axios.patch(
      `${API_BASE_URL}/clinic-expenses/${id}/mark-paid`,
      {},
      { headers: { Authorization: `Bearer ${localStorage.getItem("token")}` } }
    );
    return response.data;
  },

  async getStatistics(params = {}) {
    const response = await axios.get(
      `${API_BASE_URL}/clinic-expenses-statistics`,
      {
        params,
        headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
      }
    );
    return response.data;
  },
};
```

---

## 🐛 Error Handling

### Common Error Codes

| Status | Description                               |
| ------ | ----------------------------------------- |
| 400    | Bad Request - Missing required parameters |
| 401    | Unauthorized - Invalid or missing token   |
| 403    | Forbidden - Insufficient permissions      |
| 404    | Not Found - Resource doesn't exist        |
| 422    | Validation Error - Invalid data           |
| 500    | Server Error - Contact support            |

### Error Response Format

```json
{
  "success": false,
  "message": "Error description here"
}
```

---

## 💡 Best Practices

1. **Always include the Bearer token** in the Authorization header
2. **Filter by category** when displaying expenses in category view
3. **Use the statistics endpoint** for dashboard summaries
4. **Implement pagination** for better performance
5. **Handle date formats** consistently (YYYY-MM-DD)
6. **Show loading states** while fetching data
7. **Validate forms** on the frontend before submission
8. **Cache active categories** for dropdown menus
9. **Implement optimistic UI updates** for mark as paid/unpaid actions
10. **Use debouncing** for search inputs

---

## 📱 Mobile Responsive Considerations

- Use card layout instead of tables on mobile
- Make category cards stack vertically
- Use bottom sheets for forms on mobile
- Implement swipe gestures for quick actions (mark as paid, delete)
- Use floating action button (FAB) for add actions

---

## 🚀 Quick Start for Frontend Developers

1. **Install axios** or your preferred HTTP client
2. **Set up authentication** and store the JWT token
3. **Create the expense service** using the examples above
4. **Build the category grid component** first
5. **Add the expense list component** with filters
6. **Implement the statistics dashboard**
7. **Add forms** for creating/editing
8. **Test all CRUD operations**

---

## 📞 Support

For questions or issues, please contact the backend team or refer to the main API documentation.

**Document Version:** 1.0  
**Last Updated:** January 24, 2026  
**API Version:** v1
