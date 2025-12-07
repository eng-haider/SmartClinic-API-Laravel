# Clean Architecture - Patient Module

This is a complete implementation of clean architecture for the Patient module using Laravel.

## Architecture Overview

```
Request → Controller → Service → Repository → Database
Response ← Resource ← Service ← Repository ← Model
```

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── PatientController.php          # Handles HTTP requests/responses
│   ├── Requests/
│   │   └── PatientRequest.php             # Form request validation
│   └── Resources/
│       └── PatientResource.php            # API response transformation
├── Services/
│   └── PatientService.php                 # Business logic layer
├── Repositories/
│   ├── Contracts/
│   │   └── PatientRepositoryInterface.php # Repository interface
│   ├── BaseRepository.php                 # Base repository class
│   └── PatientRepository.php              # Patient repository implementation
├── Models/
│   └── Patient.php                        # Eloquent model
└── Providers/
    └── AppServiceProvider.php             # Service provider (bindings)
```

## Layer Responsibilities

### 1. **Controller Layer** (`PatientController.php`)

- Handles HTTP requests and responses
- Delegates business logic to services
- Returns JSON responses with proper status codes
- Minimal logic, only routing and response formatting

### 2. **Request Validation** (`PatientRequest.php`)

- Centralized validation rules
- Custom error messages
- Reusable across store and update actions

### 3. **Resource Layer** (`PatientResource.php`)

- Transforms model data into API response format
- Consistent JSON structure
- Supports collections with `PatientResource::collection()`

### 4. **Service Layer** (`PatientService.php`)

- Contains all business logic
- Orchestrates repository calls
- Handles validation and business rules
- Throws meaningful exceptions

### 5. **Repository Layer** (`PatientRepository.php`)

- Database abstraction layer
- Query building and optimization
- Single responsibility: data access only
- Uses Laravel QueryBuilder patterns
- Implements `PatientRepositoryInterface`

### 6. **Base Repository** (`BaseRepository.php`)

- Reusable base class for all repositories
- Common CRUD operations
- Extensible for other modules

### 7. **Model** (`Patient.php`)

- Eloquent model definition
- Relationships and scopes
- Casts and attributes
- No business logic

## Benefits

✅ **Separation of Concerns** - Each layer has a single responsibility
✅ **Testability** - Services and repositories are easily mockable
✅ **Reusability** - Services can be used by different controllers or commands
✅ **Maintainability** - Easy to locate and modify code
✅ **Scalability** - Easy to add new features without affecting existing code
✅ **Clean Code** - Controllers are thin and easy to read

## Usage Examples

### Creating a Patient

```php
// In PatientController
$patient = $this->patientService->createPatient($request->validated());
```

### Getting Patients with Filters

```php
$filters = ['search' => 'John', 'city' => 'Cairo'];
$patients = $this->patientService->getAllPatients($filters, 15);
```

### Updating a Patient

```php
$patient = $this->patientService->updatePatient($id, $validated);
```

### Deleting a Patient

```php
$this->patientService->deletePatient($id);
```

## Dependency Injection

Repository is injected via constructor in Service:

```php
public function __construct(private PatientRepositoryInterface $patientRepository)
{
}
```

Service is injected via constructor in Controller:

```php
public function __construct(private PatientService $patientService)
{
}
```

The binding is registered in `AppServiceProvider`:

```php
$this->app->bind(PatientRepositoryInterface::class, PatientRepository::class);
```

## Adding New Features

To add a new feature (e.g., patient search by phone):

1. **Add method to Repository**

```php
public function getByPhone(string $phone): ?Patient
{
    return $this->query()->where('phone', $phone)->first();
}
```

2. **Add method to Service**

```php
public function searchByPhone(string $phone): ?Patient
{
    return $this->patientRepository->getByPhone($phone);
}
```

3. **Add endpoint in Controller**

```php
public function searchByPhone(Request $request): JsonResponse
{
    $patient = $this->patientService->searchByPhone($request->input('phone'));
    // Return response...
}
```

## Testing

Create tests for each layer:

```php
// Tests/Unit/PatientServiceTest.php
class PatientServiceTest extends TestCase
{
    public function test_create_patient()
    {
        $service = new PatientService(new PatientRepository());
        $patient = $service->createPatient([...]);
        $this->assertInstanceOf(Patient::class, $patient);
    }
}

// Tests/Feature/PatientControllerTest.php
class PatientControllerTest extends TestCase
{
    public function test_index_returns_patients()
    {
        $response = $this->getJson('/api/patients');
        $response->assertStatus(200);
    }
}
```

## Query Builder Features

The repository uses Laravel QueryBuilder for:

- Flexible filtering
- Sorting
- Pagination
- Search with multiple fields
- Date range filtering
- Index optimization with proper columns

## Notes

- All exceptions are caught and returned as JSON responses
- Validation is centralized in FormRequest
- Resources handle all data transformation
- Service layer validates business rules
- Repository is responsible for data access only
