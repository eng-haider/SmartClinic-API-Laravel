# مرجع سريع - نظام تعدد المستأجرين

# Quick Reference - Multi-Tenancy System

## 🚀 البدء السريع

### 1. إنشاء عيادة جديدة

```bash
curl -X POST http://localhost/api/tenants \
  -H "Content-Type: application/json" \
  -d '{"name": "عيادة الأمل", "address": "بغداد"}'
```

### 2. تسجيل الدخول في العيادة

```bash
curl -X POST http://localhost/api/tenant/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: clinic_alamal_xxx" \
  -d '{"email": "doctor@clinic.com", "password": "password"}'
```

### 3. استخدام API العيادة

```bash
curl http://localhost/api/tenant/patients \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: clinic_alamal_xxx"
```

---

## 📌 Headers المطلوبة

| Header          | الوصف            | مطلوب            |
| --------------- | ---------------- | ---------------- |
| `X-Tenant-ID`   | معرف العيادة     | ✅ نعم           |
| `Authorization` | Bearer Token     | للمسارات المحمية |
| `Content-Type`  | application/json | للـ POST/PUT     |

---

## 🔗 مسارات API

### إدارة العيادات (Central)

| Method | Path                | الوصف          |
| ------ | ------------------- | -------------- |
| GET    | `/api/tenants`      | قائمة العيادات |
| POST   | `/api/tenants`      | إنشاء عيادة    |
| GET    | `/api/tenants/{id}` | تفاصيل عيادة   |
| PUT    | `/api/tenants/{id}` | تحديث عيادة    |
| DELETE | `/api/tenants/{id}` | حذف عيادة      |

### API العيادة (Tenant)

| Method | Path                        | الوصف      |
| ------ | --------------------------- | ---------- |
| POST   | `/api/tenant/auth/login`    | تسجيل دخول |
| POST   | `/api/tenant/auth/register` | تسجيل جديد |
| GET    | `/api/tenant/patients`      | المرضى     |
| GET    | `/api/tenant/cases`         | الحالات    |
| GET    | `/api/tenant/reservations`  | الحجوزات   |
| GET    | `/api/tenant/bills`         | الفواتير   |

---

## 💻 أوامر Artisan

```bash
# ترحيلات جميع العيادات
php artisan tenants:migrate

# ترحيلات عيادة محددة
php artisan tenants:migrate --tenants=clinic_xxx

# بذور جميع العيادات
php artisan tenants:seed

# قائمة العيادات
php artisan tenants:list
```

---

## ⚠️ ملاحظات مهمة

1. **كل عيادة = قاعدة بيانات منفصلة**
2. **الـ Token خاص بعيادة واحدة فقط**
3. **يجب إرسال `X-Tenant-ID` في كل طلب**
4. **حذف العيادة يحذف جميع بياناتها**
