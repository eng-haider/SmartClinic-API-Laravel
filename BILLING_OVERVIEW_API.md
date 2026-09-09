# Billing overview API

These read-only endpoints support the single patient table and inline payment details. They are registered under both `/api/bills` and `/api/tenant/bills`. Tenant requests use the existing `X-Tenant-ID` or `X-Clinic-ID` header. All endpoints require a valid JWT and `view-all-bills` permission.

All lists return `success`, `data`, and `pagination` (`total`, `per_page`, `current_page`, `last_page`, `from`, `to`). Summary values cover **all matching records**, regardless of the current page. Amounts are integers in the same currency units as `cases.price` and `bills.price`.

## Patient balances

`GET /api/tenant/bills/patient-balances`

Optional query parameters:

| Parameter | Values |
| --- | --- |
| `search` | Patient name or phone substring, up to 255 characters |
| `date_from`, `date_to` | Optional payment creation dates (`YYYY-MM-DD`), inclusive; select patients with paid case bills in this period |
| `doctor_id` | Positive case doctor ID |
| `payment_status` | `paid` (all selected cases settled), `unpaid` (any selected case has a balance) |
| `sort` | `name`, `case_count`, `total_price`, `paid_amount`, `unpaid_amount`, `last_payment_at`, `period_paid_amount`; prefix `-` for descending |
| `page` | Positive page number; default 1 |
| `per_page` | 1–500; default 15 |

Default order is `-last_payment_at`, with patients without payments last. Patient ID descending breaks sorting ties.

```json
{
  "success": true,
  "data": [{
    "id": 12,
    "name": "Patient name",
    "phone": "07701234567",
    "case_count": 2,
    "total_price": 300000,
    "paid_amount": 100000,
    "unpaid_amount": 200000,
    "last_payment_at": "2026-09-09 10:30:00",
    "payment_status": "unpaid"
  }],
  "pagination": {"total": 1, "per_page": 15, "current_page": 1, "last_page": 1, "from": 1, "to": 1},
  "summary": {"total_price": 300000, "paid_amount": 100000, "unpaid_amount": 200000, "patient_count": 1}
}
```

Balances cover the entire lifetime of active cases, including cases with no bills. Each case price is counted once. Only active bills with `is_paid = true` contribute to `paid_amount`; unpaid bill rows do not represent the case's outstanding balance. `unpaid_amount` is the sum of `max(case price - paid case bills, 0)` **for each case separately**. Therefore an overpayment on one case cannot hide debt on another, and `total_price - paid_amount` may differ from `unpaid_amount` when a case is overpaid. The cached `cases.is_paid` field is not used to derive balances.

Patients without active cases and soft-deleted patients/cases/bills are excluded. `doctor_id` selects cases assigned to that doctor before balances and payment status are calculated. With dates selected, only patients with a paid case bill in that period are returned. Patient registration and case creation dates do not determine membership. `period_paid_amount` is added to each row and the summary; `paid_amount`, `total_price`, and `unpaid_amount` remain lifetime values for the selected doctor. Latest-payment sorting uses the latest matching payment. Without dates, patients with no payments remain visible. The period-paid value must never be subtracted from total case price to compute debt.

## Payments

`GET /api/tenant/bills/payments`

Returns active **paid** case bills in the existing `BillResource` shape, with patient, doctor, and billable case/category relationships. It supports `search`, `doctor_id`, `page`, and `per_page` as above, plus `payment_status` (the patient’s lifetime case balance status, used to keep exports consistent with the patient table), and:

| Parameter | Values |
| --- | --- |
| `patient_id` | Positive patient ID |
| `date_from` | `YYYY-MM-DD`, inclusive from midnight |
| `date_to` | `YYYY-MM-DD`, inclusive through the entire date; must not precede `date_from` |
| `sort` | `created_at`, `-created_at` (default), `price`, `-price` |

The summary is `{"paid_amount": 100000, "payment_count": 4}` for all matching payments. Dates can be supplied independently. Bill ID descending breaks sorting ties.

The bill schema has **no `paid_at` timestamp**. Payment date filtering, displayed `created_at`, and patient `last_payment_at` use bill creation time as the recorded payment date. Marking an older bill paid does not change its original recorded date.

Doctor filtering consistently uses the **case's doctor**, even when a bill was recorded by a different doctor. The `doctor` resource still identifies the bill's doctor. The patient is resolved from the case, which also supports legacy bills with a missing `patient_id`. Legacy case morph values `App\\Models\\Case`, `App\\Models\\CaseModel`, `Case`, and `CaseModel` are supported.

## Patient bill history

`GET /api/tenant/bills/patient-balances/{patientId}/bills`

Returns active paid **and unpaid** case bill records for the selected patient, in the same resource and pagination shape as payments. It accepts payment list filters and sorting; the path patient ID takes precedence over any query patient ID. An unknown or deleted patient returns 404. Cases with no bill rows still contribute to balances but have no bill history entries.

Each response also includes a top-level `patient_balance` object with the same fields as one patient balances row (`id`, `name`, `phone`, `case_count`, `total_price`, `paid_amount`, `unpaid_amount`, `last_payment_at`, `payment_status`). This balance is freshly calculated for the path patient and the selected `doctor_id`, over their entire case history. History search, date, pagination, and sorting filters do not restrict this lifetime balance. If that doctor has no active cases for the patient, the object retains patient identity with zero totals/count, `last_payment_at: null`, and `payment_status: "paid"`.

Use `patient_balance` to refresh the dialog and patient Excel summary after bill changes, rather than retaining the earlier list row. There is no aggregate `summary` property on the history endpoint.

## Excel exports and verification

Clients can retrieve every filtered row by requesting successive pages with `per_page=500` while preserving filters and sorting. An export must not use only the visible table page. Pagination is ordered consistently, but concurrent bill updates can change page boundaries; these read endpoints do not provide a database snapshot spanning multiple requests.

Invalid supported filters return standard Laravel 422 validation errors. No database migrations are required for these endpoints.

Run the focused regression suite with:

```sh
php vendor/bin/phpunit tests/Feature/BillingOverviewTest.php
```

The tests create an isolated in-memory SQLite database and do not migrate, reset, or modify any clinic database.

The single-page UI exports a summary, all matching patients, and all matching paid bills in one workbook. Inline details call `/bills/payments` with `patient_id` and the same doctor/date scope. The existing paid/unpaid history endpoint remains available for other callers.
