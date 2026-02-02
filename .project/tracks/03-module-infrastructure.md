# Track 03: Module Infrastructure

> **Status:** Complete
> **Completed:** 2026-01-31

## Overview

Supporting infrastructure for the module system: middleware, commands, service providers.

## Tasks

- [x] `EnsureModuleEnabled` middleware
- [x] `ModuleServiceProvider` base class
- [x] `module:list` Artisan command
- [x] `module:enable` Artisan command
- [x] `module:disable` Artisan command
- [x] `module:migrate` Artisan command
- [x] Module exception classes
- [x] Module route registration

## Key Files

- `app/Http/Middleware/EnsureModuleEnabled.php`
- `app/Providers/ModuleServiceProvider.php`
- `app/Console/Commands/ModuleListCommand.php`
- `app/Console/Commands/ModuleEnableCommand.php`
- `app/Console/Commands/ModuleDisableCommand.php`
- `app/Console/Commands/ModuleMigrateCommand.php`
- `app/Exceptions/ModuleNotFoundException.php`
- `app/Exceptions/ModuleDependencyException.php`
- `app/Exceptions/ModuleConflictException.php`

## Artisan Commands

```bash
# List all modules and their status
php artisan module:list

# Enable a module for a tenant
php artisan module:enable hrms-core --tenant=<uuid>

# Disable a module for a tenant
php artisan module:disable hrms-core --tenant=<uuid>

# Run module migrations
php artisan module:migrate hrms-core
```

## Dependencies

- Track 01: Foundation
- Track 02: Module System

## Notes

- Middleware checks if module is enabled before allowing access
- Service providers auto-register routes, views, migrations
- Commands support `--tenant` flag for tenant-specific operations
