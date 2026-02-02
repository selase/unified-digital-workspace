# Track 02: Module System

> **Status:** Complete
> **Completed:** 2026-01-31

## Overview

Core module management system allowing modules to be enabled/disabled per tenant.

## Tasks

- [x] ModuleManager service implementation
- [x] Module configuration structure (`module.php`)
- [x] TenantModule pivot model
- [x] Module registration system
- [x] Feature flags per module
- [x] Permission definitions per module
- [x] Tenant-module relationship

## Key Files

- `app/Services/ModuleManager.php` - Core module management
- `app/Models/TenantModule.php` - Tenant-module pivot
- `app/Modules/Core/Config/module.php` - Example module config

## Module Config Structure

```php
// app/Modules/{ModuleName}/Config/module.php
return [
    'name' => 'Module Name',
    'slug' => 'module-slug',
    'version' => '1.0.0',
    'tier' => 'professional',
    'is_billable' => true,
    'depends_on' => ['core'],
    'features' => [...],
    'permissions' => [...],
];
```

## Dependencies

- Track 01: Foundation

## Notes

- Modules live in `app/Modules/{ModuleName}/`
- Each module has its own migrations, models, controllers
- Modules can declare dependencies on other modules
