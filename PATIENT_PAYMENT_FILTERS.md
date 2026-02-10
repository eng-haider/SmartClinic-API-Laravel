# Patient Payment Status Filters

## Overview

Added filters to quickly find patients based on their case payment status.

## Available Filters

### 1. Has Unpaid Cases (`has_unpaid_cases`)

Returns patients who have **at least one unpaid case**.

**Usage:**

```http
GET /api/tenant/patients?filter[has_unpaid_cases]=true
```

**Use Case:**

- Find patients who still owe money
- Send payment reminders
- View outstanding accounts

### 2. All Cases Paid (`all_cases_paid`)

Returns patients who have **all their cases paid** (must have at least one case).

**Usage:**

```http
GET /api/tenant/patients?filter[all_cases_paid]=true
```

**Use Case:**

- Find patients with good payment history
- Generate completion reports
- View fully settled accounts

## Query Performance

Both filters use optimized `whereHas` and `whereDoesntHave` queries with minimal subquery overhead:

```sql
-- has_unpaid_cases: Fast EXISTS query
SELECT * FROM patients
WHERE EXISTS (
    SELECT * FROM cases
    WHERE cases.patient_id = patients.id
    AND cases.is_paid = 0
)

-- all_cases_paid: Fast NOT EXISTS + EXISTS query
SELECT * FROM patients
WHERE NOT EXISTS (
    SELECT * FROM cases
    WHERE cases.patient_id = patients.id
    AND cases.is_paid = 0
) AND EXISTS (
    SELECT * FROM cases
    WHERE cases.patient_id = patients.id
)
```

## Examples

### Get All Patients with Unpaid Cases

```http
GET /api/tenant/patients?filter[has_unpaid_cases]=true&per_page=20
```

**Response:**

```json
{
  "success": true,
  "message": "Patients retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Ahmed Ali",
      "phone": "0770 028 1899",
      "cases": [
        {
          "id": 5,
          "price": 200000,
          "is_paid": false
        }
      ]
    }
  ]
}
```

### Get Patients with All Cases Paid

```http
GET /api/tenant/patients?filter[all_cases_paid]=true&include=cases
```

### Combine with Other Filters

```http
GET /api/tenant/patients?filter[has_unpaid_cases]=true&filter[city]=Baghdad&sort=-created_at
```

### Include Case Details

```http
GET /api/tenant/patients?filter[has_unpaid_cases]=true&include=cases
```

## Frontend Implementation

### Example 1: Show Unpaid Patients Badge

```javascript
const response = await fetch(
  "/api/tenant/patients?filter[has_unpaid_cases]=true",
);
const { data, pagination } = await response.json();

console.log(`${pagination.total} patients have unpaid cases`);
```

### Example 2: Filter Dropdown

```javascript
const paymentFilters = [
  { value: "", label: "All Patients" },
  { value: "has_unpaid_cases", label: "Has Unpaid Cases" },
  { value: "all_cases_paid", label: "All Cases Paid" },
];

function filterPatients(filterType) {
  const url = filterType
    ? `/api/tenant/patients?filter[${filterType}]=true`
    : "/api/tenant/patients";

  fetch(url)
    .then((res) => res.json())
    .then((data) => {
      // Display patients
    });
}
```

### Example 3: Dashboard Stats

```javascript
async function getDashboardStats() {
  const [unpaidResponse, paidResponse] = await Promise.all([
    fetch("/api/tenant/patients?filter[has_unpaid_cases]=true&per_page=1"),
    fetch("/api/tenant/patients?filter[all_cases_paid]=true&per_page=1"),
  ]);

  const unpaidData = await unpaidResponse.json();
  const paidData = await paidResponse.json();

  return {
    patientsWithUnpaidCases: unpaidData.pagination.total,
    patientsWithAllPaid: paidData.pagination.total,
  };
}
```

## Notes

1. **Filter values must be `true`** - The filter activates when the value is true
2. **Indexed queries** - Uses `is_paid` index on cases table for fast performance
3. **Works with pagination** - All filters support `per_page` parameter
4. **Combine with other filters** - Can be used alongside name, phone, city filters
5. **Case sensitivity** - All filters are case-insensitive

## Database Indexes

For optimal performance, ensure these indexes exist:

```sql
-- On cases table
CREATE INDEX idx_cases_patient_id_is_paid ON cases(patient_id, is_paid);
CREATE INDEX idx_cases_is_paid ON cases(is_paid);
```

These indexes are automatically created by Laravel migrations.
