# Track 04: HRMS Core Module

> **Status:** Complete
> **Started:** 2026-01-31
> **Last Updated:** 2026-02-02

## Overview

Port existing HRMS codebase as a module (`hrms-core`) within the multi-tenant system. Single module with internal domain namespacing covering employees, leave, salary, appraisals, promotions, and recruitment.

## Files in This Track

- `README.md` - This file (overview)
- `PLAN.md` - Detailed implementation plan
- `STATUS.md` - Phase-by-phase progress tracking

## Quick Status

| Phase | Name                  | Status       |
| ----- | --------------------- | ------------ |
| 1     | Foundation            | [x] Complete |
| 2     | Employee Domain       | [x] Complete |
| 3     | Leave Management      | [x] Complete |
| 4     | Salary & Organization | [x] Complete |
| 5     | Appraisal System      | [x] Complete |
| 6     | Promotion System      | [x] Complete |
| 7     | Recruitment System    | [x] Complete |
| 8     | Integration & Quality | [x] Complete |

## Model Count by Domain

| Domain       | Models | Tests         |
| ------------ | ------ | ------------- |
| Organization | 6      | ✓             |
| Employees    | 11     | ✓             |
| Leave        | 4      | ✓             |
| Salary       | 7      | ✓             |
| Appraisal    | 13     | ✓             |
| Promotion    | 1      | ✓             |
| Recruitment  | 14     | ✓             |
| **Total**    | **56** | **301 tests** |

## Dependencies

- Track 01: Foundation
- Track 02: Module System
- Track 03: Module Infrastructure

## Key Commands

```bash
# Run all HRMS tests
php artisan test --filter=HrmsCore

# Run migrations
php artisan module:migrate hrms-core

# Check code style
vendor/bin/pint --dirty
```
