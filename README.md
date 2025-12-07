# SmartClinic API - Patient Module

A **clean, production-ready** Laravel API for managing patient records with professional architecture patterns.

## ✨ Features

✅ **RESTful API** - Full CRUD operations for patients
✅ **Query Builder** - Spatie's laravel-query-builder for advanced filtering/sorting
✅ **Clean Architecture** - Repositories, Services, and Resources
✅ **Validation** - Form Request validation with custom messages
✅ **Pagination** - Built-in pagination with metadata
✅ **Error Handling** - Consistent JSON error responses
✅ **Type Safety** - Full type hints and return types
✅ **Documentation** - Comprehensive API and architecture docs
✅ **Testing** - Feature and unit test examples
✅ **Security** - Unique phone/email validation, SQL injection prevention

---

## 🏗️ Architecture

```
Request → Controller → Service → Repository → Database
Response ← Resource ← Service ← Repository ← Model
```

### Layers

1. **Controller** - HTTP request/response handling
2. **Service** - Business logic and validation
3. **Repository** - Data access abstraction
4. **Model** - Database representation
5. **Resource** - Response transformation
6. **Request** - Input validation rules

---

## 📦 Installation

### 1. Clone Repository

```bash
git clone <repository-url>
cd SmartClinic-API-Laravel
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Database Setup

```bash
# Run migrations (creates patients table)
php artisan migrate

# Optional: Seed with test data
php artisan db:seed --class=PatientSeeder
```

### 5. Start Development Server

```bash
php artisan serve
# API available at http://localhost:8000/api
```

---

## 🚀 Quick API Examples

### Create Patient

```bash
curl -X POST http://localhost:8000/api/patients \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ahmed",
    "last_name": "Hassan",
    "phone": "01001234567",
    "date_of_birth": "1990-05-15",
    "gender": "male",
    "email": "ahmed@example.com"
  }'
```

### Get All Patients

```bash
curl http://localhost:8000/api/patients
```

### Filter Patients

```bash
# Filter by gender
curl "http://localhost:8000/api/patients?filter[gender]=male"

# Filter by city
curl "http://localhost:8000/api/patients?filter[city]=Cairo"

# Multiple filters
curl "http://localhost:8000/api/patients?filter[gender]=male&filter[city]=Cairo"
```

### Sort Patients

```bash
# Sort ascending
curl "http://localhost:8000/api/patients?sort=first_name"

# Sort descending
curl "http://localhost:8000/api/patients?sort=-created_at"

# Multiple sorts
curl "http://localhost:8000/api/patients?sort=-created_at,first_name"
```

### Search Patients

```bash
curl "http://localhost:8000/api/patients?search=ahmed&filter[gender]=male&sort=-created_at"
```

### Get Single Patient

```bash
curl http://localhost:8000/api/patients/1
```

### Update Patient

```bash
curl -X PUT http://localhost:8000/api/patients/1 \
  -H "Content-Type: application/json" \
  -d '{"first_name": "Ahmed Updated"}'
```

### Delete Patient

```bash
curl -X DELETE http://localhost:8000/api/patients/1
```

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
