# Archived Pre-Tenancy Migrations

## What is this folder?

This folder contains migrations that were used **before** the multi-tenancy system was implemented.

## Why were these moved?

These migrations created tenant-specific tables (patients, cases, bills, etc.) in the **central database**.

After implementing multi-tenancy:

- Each clinic gets its own **separate database**
- Tenant-specific tables should be created in **tenant databases**, not the central one
- Clean tenant migrations are now in `database/migrations/tenant/`

## Files in this folder:

### Table Creation Migrations (now in tenant folder)

- `create_patients_table.php`
- `create_cases_table.php`
- `create_bills_table.php`
- `create_reservations_table.php`
- `create_recipes_table.php`
- `create_notes_table.php`
- `create_images_table.php`
- `create_clinic_settings_table.php`
- `create_clinic_expense_categories_table.php`
- `create_clinic_expenses_table.php`
- `create_statuses_table.php`
- `create_case_categories_table.php`
- `create_from_where_comes_table.php`
- `create_recipe_items_table.php`
- `create_permission_tables.php`
- `create_clinics_table.php` - Now replaced by `tenants` table

### Update/Alter Migrations (consolidated in tenant migrations)

All the update migrations that modified tenant tables:

- Patient table updates
- Case table updates
- Bill table updates
- Reservation table updates
- Recipe table updates
- etc.

These are **not needed** anymore because the tenant migrations in `database/migrations/tenant/` already include all the final schema.

## Can I delete these?

**Not recommended immediately!** Keep them for reference in case:

1. You need to understand the evolution of the schema
2. You have existing production data that needs migration
3. You want to compare old vs new structure

After you've successfully migrated all data and the tenancy system is stable in production, you can safely delete this folder.

## Migration Path

**Old System (Pre-Tenancy):**

```
Single Database
├── patients (all clinics)
├── cases (all clinics)
├── bills (all clinics)
└── ... (filtered by clinic_id)
```

**New System (Multi-Tenancy):**

```
Central Database
├── tenants (clinic info)
└── domains (clinic domains)

Tenant Database (clinic_xxx)
├── patients (only this clinic)
├── cases (only this clinic)
├── bills (only this clinic)
└── ... (isolated data)
```

---

**Date Archived:** February 1, 2026  
**Reason:** Transition to multi-tenancy architecture
