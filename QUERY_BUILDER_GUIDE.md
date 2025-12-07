# Laravel Query Builder Integration Guide

This project uses **Spatie's Laravel Query Builder** for clean, maintainable filtering, sorting, and including relationships.

## Installation

```bash
composer require spatie/laravel-query-builder
```

✅ Already installed in this project!

## Documentation

- [Official Package Documentation](https://spatie.be/docs/laravel-query-builder/v6/introduction)

## Features Used

### 1. **Filters** - Filter results by field values

### 2. **Sorts** - Sort results by any allowed column

### 3. **Includes** - Include related models

### 4. **Appends** - Add custom attributes

---

## Usage in Patients API

### Basic Query Building

```php
// In PatientRepository.php
protected function queryBuilder(): QueryBuilder
{
    return QueryBuilder::for(Patient::class)
        ->allowedFilters([
            'first_name',
            'last_name',
            'email',
            'phone',
            'gender',
            'blood_type',
            'city',
            'state',
            'country',
            'is_active',
        ])
        ->allowedSorts([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'date_of_birth',
            'created_at',
            'updated_at',
        ]);
}
```

---

## API Query Examples

### Filter Examples

**Filter by gender:**

```bash
GET /api/patients?filter[gender]=male
```

**Filter by blood type:**

```bash
GET /api/patients?filter[blood_type]=O+
```

**Filter by active status:**

```bash
GET /api/patients?filter[is_active]=1
```

**Filter by city:**

```bash
GET /api/patients?filter[city]=Cairo
```

**Multiple filters (AND):**

```bash
GET /api/patients?filter[gender]=male&filter[blood_type]=O+&filter[city]=Cairo
```

### Sorting Examples

**Sort by first name ascending:**

```bash
GET /api/patients?sort=first_name
```

**Sort by first name descending:**

```bash
GET /api/patients?sort=-first_name
```

**Sort by creation date (newest first):**

```bash
GET /api/patients?sort=-created_at
```

**Multiple sorts:**

```bash
GET /api/patients?sort=-created_at,first_name
```

### Search Filter

**Search across multiple fields:**

```bash
GET /api/patients?search=john
```

Searches in: `first_name`, `last_name`, `email`, `phone`

### Combined Queries

**Complex query example:**

```bash
GET /api/patients?search=cairo&filter[gender]=female&filter[is_active]=1&sort=-created_at&per_page=20&page=2
```

This retrieves:

- Patients matching "cairo" in search fields
- Female gender
- Active patients only
- Sorted by newest first
- 20 results per page
- Page 2

---

## Response Format

```json
{
  "success": true,
  "message": "Patients retrieved successfully",
  "data": [
    {
      "id": 1,
      "first_name": "Ahmed",
      "last_name": "Hassan",
      ...
    }
  ],
  "pagination": {
    "total": 150,
    "per_page": 15,
    "current_page": 1,
    "last_page": 10,
    "from": 1,
    "to": 15
  }
}
```

---

## How QueryBuilder Works

### In the Repository

```php
public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
{
    $builder = $this->queryBuilder(); // Get configured QueryBuilder

    // Apply custom search filter
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $builder->where(function ($query) use ($search) {
            $query->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // QueryBuilder automatically applies filter[], sort[], include[] from request
    return $builder->paginate($perPage);
}
```

### Request Lifecycle

1. Client sends request: `GET /api/patients?filter[gender]=male&sort=-created_at`
2. Controller receives request
3. Service passes to Repository
4. QueryBuilder automatically processes:
   - `filter[gender]=male` → applies `where('gender', 'male')`
   - `sort=-created_at` → applies `orderBy('created_at', 'desc')`
5. Repository returns paginated results
6. Resource transforms each patient
7. Controller returns JSON response

---

## Available Filters

| Filter       | Values                           | Example                            |
| ------------ | -------------------------------- | ---------------------------------- |
| `gender`     | male, female, other              | `?filter[gender]=male`             |
| `blood_type` | O+, O-, A+, A-, B+, B-, AB+, AB- | `?filter[blood_type]=O+`           |
| `is_active`  | 0, 1                             | `?filter[is_active]=1`             |
| `city`       | Any city                         | `?filter[city]=Cairo`              |
| `state`      | Any state                        | `?filter[state]=Cairo`             |
| `country`    | Any country                      | `?filter[country]=Egypt`           |
| `phone`      | Any phone                        | `?filter[phone]=01001234567`       |
| `email`      | Any email                        | `?filter[email]=ahmed@example.com` |
| `first_name` | Any name                         | `?filter[first_name]=Ahmed`        |
| `last_name`  | Any name                         | `?filter[last_name]=Hassan`        |

---

## Available Sorts

| Sort Field      | Direction |
| --------------- | --------- |
| `id`            | asc, desc |
| `first_name`    | asc, desc |
| `last_name`     | asc, desc |
| `email`         | asc, desc |
| `phone`         | asc, desc |
| `date_of_birth` | asc, desc |
| `created_at`    | asc, desc |
| `updated_at`    | asc, desc |

**Example:**

```bash
GET /api/patients?sort=-created_at,first_name
```

---

## Advanced Examples

### Search with filters and sorting

```bash
GET /api/patients?search=ahmed&filter[gender]=male&filter[city]=Cairo&sort=-created_at&per_page=10
```

### All males older than a certain date

```bash
GET /api/patients?filter[gender]=male&sort=-date_of_birth&per_page=20
```

### Active patients by creation date

```bash
GET /api/patients?filter[is_active]=1&sort=-created_at&per_page=30
```

### Pagination with filters

```bash
GET /api/patients?filter[city]=Cairo&per_page=15&page=2
```

---

## Custom Filters

To add custom filters, extend the QueryBuilder configuration:

```php
protected function queryBuilder(): QueryBuilder
{
    return QueryBuilder::for(Patient::class)
        ->allowedFilters([
            'gender',
            // Add custom filter class
            Filter::custom('age_range', new AgeRangeFilter()),
        ])
        ->allowedSorts([...]);
}
```

Create custom filter:

```php
class AgeRangeFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        // Custom logic here
        return $query->whereBetween('date_of_birth', $value);
    }
}
```

---

## Best Practices

✅ **Always define allowed filters** - For security, only expose necessary fields
✅ **Index filtered columns** - Ensure database indexes exist on filtered fields
✅ **Validate user input** - Use Form Requests for additional validation
✅ **Limit sort options** - Only expose sortable fields that make sense
✅ **Document API** - Keep API documentation updated with filter options
✅ **Test thoroughly** - Test filter combinations and edge cases

---

## Troubleshooting

### Filter not working

- Check if field is in `allowedFilters()`
- Verify column name matches exactly
- Check database column exists

### Sort not working

- Check if field is in `allowedSorts()`
- Verify column name matches exactly

### Performance issues

- Add database indexes to filtered columns
- Use `select()` to limit columns
- Use `include()` cautiously with relationships

---

## See Also

- [Spatie Query Builder Docs](https://spatie.be/docs/laravel-query-builder/v6/introduction)
- [Laravel Query Builder](https://laravel.com/docs/queries)
- [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) - Full API endpoint documentation
- [CLEAN_ARCHITECTURE.md](./CLEAN_ARCHITECTURE.md) - Architecture overview
