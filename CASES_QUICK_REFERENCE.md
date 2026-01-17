# Cases Module - Quick Reference

## 📁 Files Created (24 total)

### Core Files

```
app/Models/Case.php
app/Models/Status.php
app/Models/CaseCategory.php
app/Repositories/CaseRepository.php
app/Repositories/Contracts/CaseRepositoryInterface.php
app/Services/CaseService.php
app/Http/Controllers/CaseController.php
app/Http/Requests/CaseRequest.php
app/Http/Requests/UpdateCaseStatusRequest.php
app/Http/Resources/CaseResource.php
app/Http/Resources/StatusResource.php
app/Http/Resources/CaseCategoryResource.php
database/migrations/2025_12_09_000001_create_statuses_table.php
database/migrations/2025_12_09_000002_create_case_categories_table.php
database/migrations/2025_12_09_000003_create_cases_table.php
database/factories/CaseFactory.php
database/factories/StatusFactory.php
database/factories/CaseCategoryFactory.php
database/seeders/CaseDataSeeder.php
```

### Updated Files

```
app/Providers/AppServiceProvider.php (added CaseRepository binding)
routes/api.php (added 17 case routes)
app/Models/Patient.php (added cases relationship)
app/Models/User.php (added cases relationship)
```

## 🚀 API Endpoints (17)

| Method    | Endpoint                      | Description    |
| --------- | ----------------------------- | -------------- |
| GET       | `/api/cases`                  | List all cases |
| POST      | `/api/cases`                  | Create case    |
| GET       | `/api/cases/{id}`             | Get case       |
| PUT/PATCH | `/api/cases/{id}`             | Update case    |
| DELETE    | `/api/cases/{id}`             | Delete case    |
| POST      | `/api/cases/{id}/restore`     | Restore case   |
| DELETE    | `/api/cases/{id}/force`       | Force delete   |
| GET       | `/api/cases/patient/{id}`     | By patient     |
| GET       | `/api/cases/doctor/{id}`      | By doctor      |
| GET       | `/api/cases/status/{id}`      | By status      |
| GET       | `/api/cases/category/{id}`    | By category    |
| GET       | `/api/cases/payment/paid`     | Paid cases     |
| GET       | `/api/cases/payment/unpaid`   | Unpaid cases   |
| PATCH     | `/api/cases/{id}/mark-paid`   | Mark paid      |
| PATCH     | `/api/cases/{id}/mark-unpaid` | Mark unpaid    |
| PATCH     | `/api/cases/{id}/status`      | Update status  |
| GET       | `/api/cases-statistics`       | Get statistics |

## 🔑 Required Fields (Create/Update)

```json
{
  "patient_id": "required|exists:patients,id",
  "doctor_id": "required|exists:users,id",
  "case_categores_id": "required|exists:case_categories,id",
  "status_id": "required|exists:statuses,id",
  "notes": "nullable|string|max:5000",
  "price": "nullable|integer|min:0",
  "tooth_num": "nullable|string|max:500",
  "item_cost": "nullable|integer|min:0",
  "root_stuffing": "nullable|string|max:500",
  "is_paid": "nullable|boolean"
}
```

## 📊 Seeded Data

**Statuses (5):**

- New, In Progress, Completed, Cancelled, On Hold

**Categories (10):**

- General Examination, Teeth Cleaning, Tooth Filling, Tooth Extraction
- Root Canal, Crown, Orthodontics, Implant, Whitening, Oral Surgery

## 🎯 Quick Test Commands

```bash
# Clear caches
php artisan route:clear && php artisan cache:clear

# View routes
php artisan route:list --path=cases

# Run seeder
php artisan db:seed --class=CaseDataSeeder

# Create test case (requires JWT token)
curl -X POST http://127.0.0.1:8000/api/cases \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "patient_id": 1,
    "doctor_id": 1,
    "case_categores_id": 3,
    "status_id": 1,
    "price": 50000,
    "notes": "Root canal treatment"
  }'

# Get statistics
curl -X GET http://127.0.0.1:8000/api/cases-statistics \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🏗️ Architecture Pattern

```
Controller → Repository → Model
     ↓           ↓
  Request    Interface
     ↓
 Resource
```

## ✅ Features Implemented

- ✅ Full CRUD operations
- ✅ Soft delete support
- ✅ Advanced filtering (QueryBuilder)
- ✅ Payment tracking
- ✅ Revenue statistics
- ✅ Relationship management
- ✅ JWT authentication
- ✅ Request validation
- ✅ Resource transformation
- ✅ Clean architecture
- ✅ Repository pattern
- ✅ Comprehensive documentation

## 📚 Documentation Files

- `CASES_API_DOCUMENTATION.md` - Complete API docs with examples
- `CASES_IMPLEMENTATION_SUMMARY.md` - Detailed implementation summary
- `CASES_QUICK_REFERENCE.md` - This quick reference guide
