# Track 01: Foundation

> **Status:** Complete
> **Completed:** 2026-01-31

## Overview

Basic Laravel 12 starterkit setup with multi-tenancy support.

## Tasks

- [x] Laravel 12 installation with PHP 8.4
- [x] PostgreSQL 16 database configuration
- [x] Multi-tenant architecture setup
- [x] `BelongsToTenant` trait implementation
- [x] `TenantScope` global scope
- [x] `TenantContext` service
- [x] Tenant model and factory
- [x] Base authentication (Laravel Breeze)
- [x] Landlord/Tenant database connections

## Key Files

- `app/Traits/BelongsToTenant.php` - Tenant scoping trait
- `app/Scopes/TenantScope.php` - Global scope for tenant isolation
- `app/Services/Tenancy/TenantContext.php` - Current tenant context
- `app/Models/Tenant.php` - Tenant model
- `config/database.php` - Landlord/tenant connections

## Dependencies

None (this is the foundation)

## Notes

- Uses Laravel Herd for local PHP (never use other PHP managers)
- PostgreSQL for production, SQLite for testing
- Tenant data isolated via `tenant_id` column
