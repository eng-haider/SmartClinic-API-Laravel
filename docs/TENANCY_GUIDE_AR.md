# دليل نظام تعدد المستأجرين (Multi-Tenancy) - SmartClinic API

## 📋 جدول المحتويات

1. [مقدمة](#مقدمة)
2. [هيكل النظام](#هيكل-النظام)
3. [التثبيت والإعداد](#التثبيت-والإعداد)
4. [إدارة العيادات (Tenants)](#إدارة-العيادات)
5. [استخدام API](#استخدام-api)
6. [قواعد البيانات](#قواعد-البيانات)
7. [أمثلة عملية](#أمثلة-عملية)
8. [الأخطاء الشائعة وحلولها](#الأخطاء-الشائعة-وحلولها)

---

## مقدمة

### ما هو نظام تعدد المستأجرين؟

نظام تعدد المستأجرين (Multi-Tenancy) يسمح بتشغيل تطبيق واحد لخدمة عدة عيادات، حيث يكون لكل عيادة:

- **قاعدة بيانات منفصلة**: بيانات كل عيادة معزولة تماماً
- **إعدادات خاصة**: كل عيادة لها إعداداتها الخاصة
- **مستخدمين مستقلين**: الأطباء والموظفين لكل عيادة منفصلين

### فوائد هذا النظام

| الميزة                 | الشرح                                  |
| ---------------------- | -------------------------------------- |
| 🔒 **أمان البيانات**   | بيانات كل عيادة في قاعدة بيانات منفصلة |
| 📊 **قابلية التوسع**   | إضافة عيادات جديدة بسهولة              |
| ⚙️ **مرونة الإعدادات** | كل عيادة تخصص إعداداتها                |
| 💰 **توفير التكلفة**   | تطبيق واحد لجميع العيادات              |

---

## هيكل النظام

### البنية العامة

```
┌─────────────────────────────────────────────────────────┐
│                    التطبيق المركزي                        │
│                   (Central Application)                  │
├─────────────────────────────────────────────────────────┤
│  قاعدة البيانات المركزية (Central Database)              │
│  - جدول العيادات (tenants)                              │
│  - جدول النطاقات (domains)                              │
└─────────────────────────────────────────────────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   عيادة الأمل    │ │   عيادة النور   │ │  عيادة الشفاء   │
│  (tenant_amal)  │ │ (tenant_noor)   │ │ (tenant_shifa)  │
├─────────────────┤ ├─────────────────┤ ├─────────────────┤
│ - المرضى       │ │ - المرضى       │ │ - المرضى       │
│ - الحالات      │ │ - الحالات      │ │ - الحالات      │
│ - الحجوزات     │ │ - الحجوزات     │ │ - الحجوزات     │
│ - الفواتير     │ │ - الفواتير     │ │ - الفواتير     │
│ - المستخدمين   │ │ - المستخدمين   │ │ - المستخدمين   │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

### الملفات الرئيسية

| الملف                                               | الوصف                         |
| --------------------------------------------------- | ----------------------------- |
| `config/tenancy.php`                                | إعدادات نظام تعدد المستأجرين  |
| `app/Models/Tenant.php`                             | نموذج العيادة (Tenant Model)  |
| `app/Http/Middleware/InitializeTenancyByHeader.php` | Middleware لتحديد العيادة     |
| `routes/tenant.php`                                 | مسارات API الخاصة بالعيادات   |
| `database/migrations/tenant/`                       | ترحيلات قواعد بيانات العيادات |

---

## التثبيت والإعداد

### الخطوة 1: إعداد ملف .env

```env
# إعدادات قاعدة البيانات المركزية
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartclinic_central
DB_USERNAME=root
DB_PASSWORD=your_password

# مهم: تأكد من أن المستخدم لديه صلاحيات إنشاء قواعد بيانات جديدة
```

### الخطوة 2: تشغيل الترحيلات المركزية

```bash
# تشغيل ترحيلات قاعدة البيانات المركزية
php artisan migrate

# هذا سينشئ:
# - جدول tenants (العيادات)
# - جدول domains (النطاقات)
```

### الخطوة 3: إنشاء أول عيادة

```bash
# عبر Tinker
php artisan tinker

# إنشاء عيادة جديدة
$tenant = App\Models\Tenant::create([
    'id' => 'clinic_amal',
    'name' => 'عيادة الأمل',
    'address' => 'بغداد - الكرادة',
]);

# سيتم تلقائياً:
# 1. إنشاء قاعدة بيانات جديدة: tenant_clinic_amal
# 2. تشغيل جميع الترحيلات
# 3. تشغيل البذور الأساسية
```

---

## إدارة العيادات

### إنشاء عيادة جديدة عبر API

```http
POST /api/tenants
Content-Type: application/json

{
    "id": "clinic_noor",           // اختياري - يُنشأ تلقائياً
    "name": "عيادة النور",          // مطلوب
    "address": "بغداد - المنصور",  // اختياري
    "whatsapp_phone": "+964xxx",   // اختياري
    "logo": "path/to/logo.png"     // اختياري
}
```

**الاستجابة:**

```json
{
  "success": true,
  "message": "Tenant created successfully. Database has been provisioned.",
  "message_ar": "تم إنشاء العيادة بنجاح. تم إعداد قاعدة البيانات.",
  "data": {
    "id": "clinic_noor",
    "name": "عيادة النور",
    "address": "بغداد - المنصور",
    "created_at": "2026-02-01T12:00:00.000000Z"
  }
}
```

### جلب قائمة العيادات

```http
GET /api/tenants
```

```http
GET /api/tenants?search=النور&per_page=10
```

### تحديث بيانات عيادة

```http
PUT /api/tenants/{id}
Content-Type: application/json

{
    "name": "عيادة النور الجديدة",
    "whatsapp_phone": "+964xxxxxxx",
    "send_msg": true
}
```

### حذف عيادة

```http
DELETE /api/tenants/{id}
```

⚠️ **تحذير**: حذف العيادة سيحذف قاعدة البيانات الخاصة بها وجميع البيانات!

---

## استخدام API

### طريقة تحديد العيادة

لاستخدام API الخاص بعيادة معينة، يجب إرسال **معرف العيادة** في رأس الطلب (Header):

```http
X-Tenant-ID: clinic_amal
# أو
X-Clinic-ID: clinic_amal
```

### مثال كامل: تسجيل دخول في عيادة

```http
POST /api/tenant/auth/login
Content-Type: application/json
X-Tenant-ID: clinic_amal

{
    "email": "doctor@amal-clinic.com",
    "password": "password123"
}
```

### مثال: جلب المرضى من عيادة معينة

```http
GET /api/tenant/patients
Authorization: Bearer {jwt_token}
X-Tenant-ID: clinic_amal
```

### مسارات API الرئيسية للعيادات

| المسار                           | الوصف                   |
| -------------------------------- | ----------------------- |
| `POST /api/tenant/auth/login`    | تسجيل الدخول            |
| `POST /api/tenant/auth/register` | إنشاء حساب جديد         |
| `GET /api/tenant/auth/me`        | معلومات المستخدم الحالي |
| `GET /api/tenant/patients`       | قائمة المرضى            |
| `POST /api/tenant/patients`      | إضافة مريض              |
| `GET /api/tenant/cases`          | قائمة الحالات           |
| `POST /api/tenant/cases`         | إضافة حالة              |
| `GET /api/tenant/reservations`   | قائمة الحجوزات          |
| `POST /api/tenant/reservations`  | إضافة حجز               |
| `GET /api/tenant/bills`          | قائمة الفواتير          |
| `POST /api/tenant/bills`         | إضافة فاتورة            |

---

## قواعد البيانات

### جداول قاعدة البيانات المركزية

```sql
-- جدول العيادات
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(500),
    rx_img VARCHAR(255),
    whatsapp_template_sid VARCHAR(255),
    whatsapp_message_count INT DEFAULT 0,
    whatsapp_phone VARCHAR(20),
    show_image_case BOOLEAN DEFAULT FALSE,
    doctor_mony INT DEFAULT 0,
    teeth_v2 BOOLEAN DEFAULT FALSE,
    send_msg BOOLEAN DEFAULT FALSE,
    show_rx_id BOOLEAN DEFAULT FALSE,
    logo VARCHAR(255),
    api_whatsapp BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    data JSON
);

-- جدول النطاقات
CREATE TABLE domains (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    domain VARCHAR(255) NOT NULL UNIQUE,
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### جداول قاعدة بيانات كل عيادة

عند إنشاء عيادة جديدة، يتم إنشاء الجداول التالية تلقائياً:

| الجدول                      | الوصف                        |
| --------------------------- | ---------------------------- |
| `users`                     | المستخدمين (أطباء، سكرتارية) |
| `patients`                  | المرضى                       |
| `cases`                     | الحالات الطبية               |
| `case_categories`           | تصنيفات الحالات              |
| `statuses`                  | حالات الإجراءات              |
| `reservations`              | الحجوزات                     |
| `bills`                     | الفواتير                     |
| `recipes`                   | الوصفات الطبية               |
| `recipe_items`              | عناصر الوصفات                |
| `notes`                     | الملاحظات                    |
| `images`                    | الصور                        |
| `clinic_settings`           | إعدادات العيادة              |
| `clinic_expenses`           | المصروفات                    |
| `clinic_expense_categories` | تصنيفات المصروفات            |
| `roles`                     | الأدوار                      |
| `permissions`               | الصلاحيات                    |

---

## أمثلة عملية

### مثال 1: إنشاء عيادة وتسجيل أول طبيب

```bash
# الخطوة 1: إنشاء العيادة
curl -X POST http://localhost/api/tenants \
  -H "Content-Type: application/json" \
  -d '{
    "name": "عيادة الشفاء",
    "address": "البصرة - العشار"
  }'

# الاستجابة ستحتوي على معرف العيادة، مثال: "clinic_alshifa_abc123"
```

```bash
# الخطوة 2: تسجيل طبيب في العيادة
curl -X POST http://localhost/api/tenant/auth/register \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: clinic_alshifa_abc123" \
  -d '{
    "name": "د. أحمد محمد",
    "email": "ahmed@shifa-clinic.com",
    "password": "SecurePassword123"
  }'
```

```bash
# الخطوة 3: تسجيل الدخول
curl -X POST http://localhost/api/tenant/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: clinic_alshifa_abc123" \
  -d '{
    "email": "ahmed@shifa-clinic.com",
    "password": "SecurePassword123"
  }'

# احتفظ بـ token من الاستجابة
```

### مثال 2: إضافة مريض وحالة

```bash
# إضافة مريض
curl -X POST http://localhost/api/tenant/patients \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {your_token}" \
  -H "X-Tenant-ID: clinic_alshifa_abc123" \
  -d '{
    "name": "محمد علي",
    "phone": "07701234567",
    "age": 35,
    "sex": 1,
    "address": "البصرة"
  }'

# سيعيد id المريض، مثال: 1
```

```bash
# إضافة حالة للمريض
curl -X POST http://localhost/api/tenant/cases \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {your_token}" \
  -H "X-Tenant-ID: clinic_alshifa_abc123" \
  -d '{
    "patient_id": 1,
    "case_categores_id": 3,
    "status_id": 1,
    "notes": "حشوة ضرس",
    "price": 50000,
    "tooth_num": "16"
  }'
```

### مثال 3: استخدام JavaScript/Axios

```javascript
import axios from "axios";

// إعداد Axios مع معرف العيادة
const tenantApi = axios.create({
  baseURL: "http://localhost/api/tenant",
  headers: {
    "Content-Type": "application/json",
    "X-Tenant-ID": "clinic_alshifa_abc123",
  },
});

// تسجيل الدخول
async function login(email, password) {
  try {
    const response = await tenantApi.post("/auth/login", { email, password });
    const token = response.data.data.token;

    // إضافة Token للطلبات القادمة
    tenantApi.defaults.headers.common["Authorization"] = `Bearer ${token}`;

    return response.data;
  } catch (error) {
    console.error("Login failed:", error.response.data);
  }
}

// جلب المرضى
async function getPatients() {
  const response = await tenantApi.get("/patients");
  return response.data.data;
}

// إضافة مريض
async function addPatient(patientData) {
  const response = await tenantApi.post("/patients", patientData);
  return response.data;
}

// استخدام
login("ahmed@shifa-clinic.com", "SecurePassword123")
  .then(() => getPatients())
  .then((patients) => console.log("المرضى:", patients));
```

### مثال 4: التبديل بين العيادات

```javascript
// إنشاء كلاس للتعامل مع عدة عيادات
class ClinicAPI {
  constructor(tenantId) {
    this.tenantId = tenantId;
    this.token = null;
    this.api = axios.create({
      baseURL: "http://localhost/api/tenant",
      headers: {
        "Content-Type": "application/json",
        "X-Tenant-ID": tenantId,
      },
    });
  }

  setToken(token) {
    this.token = token;
    this.api.defaults.headers.common["Authorization"] = `Bearer ${token}`;
  }

  async login(email, password) {
    const response = await this.api.post("/auth/login", { email, password });
    this.setToken(response.data.data.token);
    return response.data;
  }

  async getPatients() {
    return (await this.api.get("/patients")).data;
  }
}

// استخدام مع عدة عيادات
const clinicAmal = new ClinicAPI("clinic_amal");
const clinicNoor = new ClinicAPI("clinic_noor");

// تسجيل الدخول في كلا العيادتين
await clinicAmal.login("doctor@amal.com", "password");
await clinicNoor.login("doctor@noor.com", "password");

// جلب المرضى من كل عيادة
const amalPatients = await clinicAmal.getPatients();
const noorPatients = await clinicNoor.getPatients();
```

---

## الأخطاء الشائعة وحلولها

### خطأ: "Tenant ID is required"

**السبب**: لم يتم إرسال معرف العيادة في رأس الطلب

**الحل**: أضف `X-Tenant-ID` أو `X-Clinic-ID` للطلب

```http
X-Tenant-ID: clinic_amal
```

### خطأ: "Tenant not found"

**السبب**: معرف العيادة غير موجود

**الحل**: تحقق من صحة المعرف أو أنشئ العيادة أولاً

```bash
# التحقق من العيادات الموجودة
curl http://localhost/api/tenants
```

### خطأ: "SQLSTATE[HY000]: Access denied for user"

**السبب**: المستخدم في MySQL ليس لديه صلاحية إنشاء قواعد بيانات

**الحل**: امنح الصلاحيات اللازمة

```sql
GRANT ALL PRIVILEGES ON *.* TO 'your_user'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

### خطأ: "Table doesn't exist"

**السبب**: الترحيلات لم تُنفذ على قاعدة بيانات العيادة

**الحل**: نفذ الترحيلات يدوياً

```http
POST /api/tenants/{tenant_id}/migrate
```

### خطأ: "Unauthenticated" في مسارات العيادة

**السبب**: Token غير صالح أو منتهي الصلاحية

**الحل**:

1. تأكد من إرسال Token في رأس `Authorization`
2. تحقق من أن Token خاص بنفس العيادة
3. جدد Token عند انتهاء صلاحيته

```http
POST /api/tenant/auth/refresh
Authorization: Bearer {old_token}
X-Tenant-ID: clinic_amal
```

---

## أوامر Artisan المفيدة

```bash
# تشغيل ترحيلات لجميع العيادات
php artisan tenants:migrate

# تشغيل ترحيلات لعيادة محددة
php artisan tenants:migrate --tenants=clinic_amal

# التراجع عن ترحيلات
php artisan tenants:migrate-rollback

# تشغيل البذور لجميع العيادات
php artisan tenants:seed

# تشغيل البذور لعيادة محددة
php artisan tenants:seed --tenants=clinic_amal

# عرض قائمة العيادات
php artisan tenants:list
```

---

## ملخص سريع

| العملية             | الطريقة                                                   |
| ------------------- | --------------------------------------------------------- |
| إنشاء عيادة         | `POST /api/tenants`                                       |
| تسجيل دخول في عيادة | `POST /api/tenant/auth/login` + `X-Tenant-ID`             |
| جلب بيانات من عيادة | أي مسار `/api/tenant/*` + `X-Tenant-ID` + `Authorization` |
| تحديد العيادة       | رأس `X-Tenant-ID` أو `X-Clinic-ID`                        |

---

## الدعم والمساعدة

للمزيد من المساعدة:

- راجع وثائق [stancl/tenancy](https://tenancyforlaravel.com/docs)
- تواصل مع فريق التطوير
