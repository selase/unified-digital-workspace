## HRMS Core Module

The HRMS Core module provides tenant-scoped HR capabilities for UDW, including organization structure, employee records, leave, salary, appraisals, promotions, and recruitment. It is designed to run on the starterkit's multi-tenant foundation and to be enabled per tenant.

### Key Capabilities

- Organization and grade structure
- Employee profiles and job history
- Leave categories, requests, and balances
- Salary levels, steps, and allowances
- Appraisal cycles and workflows
- Promotion workflow
- Recruitment lifecycle (requisitions, postings, candidates, interviews, offers, onboarding)

### Module Enablement

Enable the module for a tenant using the ModuleManager:

```php
app(\App\Services\ModuleManager::class)->enableForTenant('hrms-core', $tenant);
```

Routes are registered for all modules, but access is guarded by module enablement middleware.

### Database Connection and PostgreSQL Notes

- All HRMS tables live on the `landlord` connection.
- Enums are stored as strings for PostgreSQL compatibility.
- Avoid unsigned integers and enum column types in migrations.

### API

HRMS exposes read-only JSON APIs under versioned routes:

```
GET /api/hrms-core/v1/employees
GET /api/hrms-core/v1/leave-requests
GET /api/hrms-core/v1/appraisals
GET /api/hrms-core/v1/job-postings
GET /api/hrms-core/v1/candidates
```

All endpoints return JSON resources with tenant-scoped data and optional relationship loading.

### Migrations

Migrations are located in:

```
app/Modules/HrmsCore/Database/Migrations/
```

Run module migrations:

```bash
php artisan module:migrate hrms-core
```

### Tests

Run HRMS tests:

```bash
php artisan test --filter=HrmsCore
```

API integration tests:

```bash
php artisan test --compact tests/Feature/Modules/HrmsCore/Api/HrmsCoreApiTest.php
```

### Code Quality

```bash
vendor/bin/pint --dirty
vendor/bin/phpstan analyse app/Modules/HrmsCore
```
