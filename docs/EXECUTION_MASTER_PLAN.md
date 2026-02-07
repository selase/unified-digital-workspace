# Unified Digital Workspace - Master Execution Plan

## Overview

This is the master execution plan for building the **Unified Digital Workspace (UDW)** - a modular, multi-tenant, enterprise-grade Laravel SaaS platform following Test-Driven Development (TDD) principles.

## Foundation

**IMPORTANT**: The UDW is built on the **Multi-Tenant SaaS Starter Kit** which provides:

- ✅ Multi-tenancy (Tracks 00-01 equivalent)
- ✅ RBAC with tenant scoping (Track 02 equivalent)
- ✅ Billing, subscriptions, usage metering
- ✅ Health checks, backups, observability
- ✅ CI/CD foundation

**Our focus** is on:
- Configuring the starterkit for UDW needs
- Building the module system (Track 03)
- Porting HRMS as the first module (Track 04)
- Building additional modules (Tracks 05-11)
- Deployment and operations (Track 12)

## Project Structure

```
unified-digital-workspace/
├── docs/
│   ├── README.md
│   ├── EXECUTION_MASTER_PLAN.md (this file)
│   ├── PROJECT_STRUCTURE.md
│   ├── tracks/
│   │   ├── TRACK_00_FOUNDATION.md      [Starterkit provides most of this]
│   │   ├── TRACK_01_TENANCY_CORE.md    [Starterkit provides this]
│   │   ├── TRACK_02_RBAC_SYSTEM.md     [Starterkit provides this]
│   │   ├── TRACK_03_MODULE_SYSTEM.md   [BUILD THIS]
│   │   ├── TRACK_04_HRMS_CORE.md       [BUILD THIS - Port HRMS]
│   │   ├── TRACK_05_SCHEDULING.md      [BUILD THIS]
│   │   ├── TRACK_13_CMS_CORE.md        [BUILD THIS]
│   │   └── ...
│   ├── architecture/
│   ├── guidelines/
│   └── reference/
├── hrms/                               [REFERENCE - Original HRMS code]
├── metronic-tailwind-html-demos/       [REFERENCE - UI theme]
└── [starterkit files...]               [BASE - Working codebase]
```

## Execution Principles

### 1. Starterkit-First Approach
- **DO NOT** recreate what the starterkit provides
- **USE** starterkit's tenancy, RBAC, billing as-is
- **EXTEND** with modules and UDW-specific features
- **REFERENCE** starterkit's `manual.md` for implementation details

### 2. Test-Driven Development (TDD)
- Red-Green-Refactor cycle for all features
- Minimum 80% code coverage per module
- Integration tests for cross-module functionality
- E2E tests for critical user journeys

### 3. Modular Architecture
- Each module is self-contained
- Modules can be enabled/disabled per tenant
- Pricing tiers defined per module
- Clear module boundaries and contracts

### 4. Enterprise Standards
- Laravel Pint formatting
- PHPStan level 8 static analysis
- Pest test suite
- Database transactions in tests
- Factory-based test data

---

## Execution Tracks

### Track 00: Foundation & Setup
**Duration:** 1 week
**Status:** ✅ Mostly provided by starterkit
**Link:** [TRACK_00_FOUNDATION.md](tracks/TRACK_00_FOUNDATION.md)

**What starterkit provides:**
- ✅ Laravel 12 + PHP 8.4
- ✅ Pest testing framework
- ✅ Pint, Rector
- ✅ Spatie packages (Permission, Activity Log, Health, Backup)
- ✅ Database configuration for multi-tenancy

**What we need to do:**
- [ ] Configure PHPStan for UDW
- [ ] Set up GitHub Actions CI/CD
- [ ] Configure PostgreSQL as primary database
- [ ] Update documentation

---

### Track 01: Tenancy Core
**Duration:** N/A
**Status:** ✅ Provided by starterkit
**Link:** [TRACK_01_TENANCY_CORE.md](tracks/TRACK_01_TENANCY_CORE.md)

**What starterkit provides:**
- ✅ Tenant model and migrations
- ✅ TenantResolver (subdomain, domain, header, session)
- ✅ TenantDatabaseManager (shared, dedicated, BYO)
- ✅ TenantStorageManager (S3 partitioning)
- ✅ BelongsToTenant trait and TenantScope
- ✅ Tenant provisioning
- ✅ Tenant isolation tests

**What we need to do:**
- [ ] Review and understand existing implementation
- [ ] Verify PostgreSQL compatibility
- [ ] Add any UDW-specific tenant fields if needed

---

### Track 02: RBAC System
**Duration:** N/A
**Status:** ✅ Provided by starterkit
**Link:** [TRACK_02_RBAC_SYSTEM.md](tracks/TRACK_02_RBAC_SYSTEM.md)

**What starterkit provides:**
- ✅ Spatie Permission with teams enabled
- ✅ tenant_id as team_foreign_key
- ✅ Custom Role and Permission models
- ✅ RolePermissions library for assignments
- ✅ System roles (Superadmin, Org Superadmin, Org Admin)
- ✅ Permission categories
- ✅ setPermissionsTeamId() in ResolveTenant

**What we need to do:**
- [ ] Add HRMS-specific permissions to seeders
- [ ] Define role hierarchy for HRMS
- [ ] Map HRMS boolean flags to roles (for data migration)

---

### Track 03: Module System
**Duration:** 2-3 weeks
**Status:** ✅ Complete
**Link:** [TRACK_03_MODULE_SYSTEM.md](tracks/TRACK_03_MODULE_SYSTEM.md) and infra summary at `docs/tracks/TRACK_03_MODULE_INFRASTRUCTURE.md`

**Key Deliverables:**
- [ ] Module manifest structure
- [ ] ModuleManager service
- [ ] Module discovery and registration
- [ ] Module enable/disable per tenant
- [ ] EnsureModuleEnabled middleware
- [ ] Module dependencies and conflicts
- [ ] Pricing tier integration
- [ ] Module migrations orchestration
- [ ] Module service provider pattern
- [ ] Module testing framework

**Dependencies:** Tracks 00-02 (provided by starterkit)

---

### Track 04: HRMS Core Module
**Duration:** 4-5 weeks
**Status:** ✅ Complete
**Link:** [TRACK_04_HRMS_CORE.md](tracks/TRACK_04_HRMS_CORE.md)

**Key Deliverables:**
- [ ] Port HRMS models with BelongsToTenant
- [ ] Employee management
- [ ] Organization structure (departments, units, directorates)
- [ ] Leave management
- [ ] Recruitment system
- [ ] Appraisals
- [ ] Promotions
- [ ] Salary & allowances
- [ ] HRMS permissions and policies
- [ ] HRMS tests (>80% coverage)

**Dependencies:** Track 03

---

### Track 05: Scheduling Module
**Duration:** 4 weeks
**Status:** 🔲 In Progress
**Link:** [TRACK_05_SCHEDULING.md](tracks/TRACK_05_SCHEDULING.md)

**Key Deliverables:**
- [ ] Shift management
- [ ] Shift patterns with rotation
- [ ] Pattern generator service
- [ ] Shift assignments
- [ ] Leave integration
- [ ] Conflict detection
- [ ] Calendar views
- [ ] Coverage reports

**Dependencies:** Track 03, Track 04 (for employees)

---

### Track 06: Incident Management Module
**Duration:** 5 weeks
**Status:** ✅ Complete
**Link:** [TRACK_06_INCIDENT_MANAGEMENT.md](tracks/TRACK_06_INCIDENT_MANAGEMENT.md)

**Key Deliverables:**
- [ ] Incident CRUD with classification
- [ ] Assignment workflows
- [ ] Delegation and CC functionality
- [ ] Escalation (manual and automatic)
- [ ] Progress reports and tasks
- [ ] Deadline extensions
- [ ] Reminder system
- [ ] SLA tracking
- [ ] Public submission form

**Dependencies:** Track 03, Track 02

---

### Track 07: Document Management Module
**Duration:** 4 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_07_DOCUMENT_MANAGEMENT.md](tracks/TRACK_07_DOCUMENT_MANAGEMENT.md)

**Key Deliverables:**
- [ ] Document CRUD
- [ ] Polymorphic visibility control
- [ ] Document quiz system
- [ ] Quiz analytics
- [ ] Document versioning
- [ ] Audit trail
- [ ] Preview and download

**Dependencies:** Track 03

---

### Track 13: CMS Core Schema
**Duration:** 3 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_13_CMS_CORE.md](tracks/TRACK_13_CMS_CORE.md)

**Key Deliverables:**
- [ ] Post types, posts, categories, tags
- [ ] Media metadata and variants
- [ ] Revisions and post meta
- [ ] Menus and settings
- [ ] API resources and tests

**Dependencies:** Track 03

---

### Track 08: Quality Monitoring Module
**Duration:** 5 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_08_QUALITY_MONITORING.md](tracks/TRACK_08_QUALITY_MONITORING.md)

**Key Deliverables:**
- [ ] Workplan management
- [ ] Strategic objectives and activities
- [ ] KPI definitions and tracking
- [ ] Progress updates workflow
- [ ] Performance reviews
- [ ] Dashboards
- [ ] Reporting engine
- [ ] Alerts and notifications

**Dependencies:** Track 03, Track 04

---

### Track 09: Forums & Messaging Module
**Duration:** 3 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_09_FORUMS_MESSAGING.md](tracks/TRACK_09_FORUMS_MESSAGING.md)

**Key Deliverables:**
- [ ] Discussion channels
- [ ] Threads and replies
- [ ] Best reply marking
- [ ] Likes/reactions
- [ ] Moderation tools
- [ ] Intranet hub
- [ ] Group messaging

**Dependencies:** Track 03

---

### Track 10: Project Management Module
**Duration:** 4 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_10_PROJECT_MANAGEMENT.md](tracks/TRACK_10_PROJECT_MANAGEMENT.md)

**Key Deliverables:**
- [ ] Projects and milestones
- [ ] Tasks with dependencies
- [ ] Kanban and list views
- [ ] Timesheets
- [ ] Gantt charts
- [ ] Resource allocation
- [ ] Reporting

**Dependencies:** Track 03

---

### Track 11: Memos Module
**Duration:** 3 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_11_MEMOS.md](tracks/TRACK_11_MEMOS.md)

**Key Deliverables:**
- [ ] Memo drafting and routing
- [ ] Approval workflows
- [ ] E-signature integration
- [ ] Recipient tracking
- [ ] Versioning
- [ ] Audit trail

**Dependencies:** Track 03, Track 07

---

### Track 12: Deployment & Operations
**Duration:** 2 weeks
**Status:** 🔲 To Build
**Link:** [TRACK_12_DEPLOYMENT.md](tracks/TRACK_12_DEPLOYMENT.md)

**Key Deliverables:**
- [ ] Production infrastructure (SaaS)
- [ ] On-premise deployment scripts
- [ ] Backup and restore procedures
- [ ] Monitoring and alerting
- [ ] Health checks
- [ ] Update mechanism
- [ ] Customization isolation

**Dependencies:** All tracks

---

## Track Dependencies (Visual)

```
Starterkit Foundation (Tracks 00-02)
              ↓
       Track 03 (Module System)
              ↓
┌─────────────┼─────────────┬─────────────┬─────────────┬─────────────┐
↓             ↓             ↓             ↓             ↓             ↓
Track 04    Track 06    Track 07    Track 13    Track 09    Track 10
(HRMS)      (Incidents) (Documents) (CMS Core) (Forums)    (Projects)
↓                           ↓
Track 05                Track 11
(Scheduling)            (Memos)
↓
Track 08
(Quality)
              ↓
       Track 12 (Deployment)
```

---

## Module Pricing Tiers

### Free Tier
- Basic HRMS (employees, departments only)
- Basic document management (no quizzes)
- Forums (limited channels)

### Standard Tier ($99/month)
- Full HRMS (leave, recruitment, appraisals)
- Document management with quizzes
- Incident management (basic)
- Forums (unlimited)
- Memos

### Professional Tier ($199/month)
- Everything in Standard
- Scheduling module
- Advanced incident management (escalations, SLA)
- Project management

### Enterprise Tier ($399/month)
- Everything in Professional
- Quality Monitoring & Evaluation
- Custom modules
- Priority support
- On-premise deployment option

---

## Success Metrics

### Code Quality
- ✅ 80%+ test coverage per module
- ✅ PHPStan level 8 passing
- ✅ Laravel Pint formatting
- ✅ Zero security vulnerabilities

### Performance
- ✅ Page load < 2 seconds (P95)
- ✅ API response < 500ms (P95)
- ✅ Database queries optimized (no N+1)

### Reliability
- ✅ 99.9% uptime SLA
- ✅ Automated backups daily
- ✅ Recovery time < 4 hours

---

## Development Workflow

### Daily Workflow
1. Pull latest from `dev` branch
2. Create feature branch: `feature/TRACK-XX-task-name`
3. Write failing test (TDD Red)
4. Implement feature (TDD Green)
5. Refactor (TDD Refactor)
6. Run quality checks: `composer pint && composer phpstan && composer test`
7. Commit with conventional message
8. Push and create PR
9. Code review
10. Merge to `dev`

### Branch Strategy
```
main (production)
  ↑
staging (pre-production)
  ↑
dev (development)
  ↑
feature/TRACK-XX-description
```

---

## Quick Reference

### Starterkit Documentation
- **Full Manual**: `/manual.md` - Comprehensive starterkit documentation
- **Tenancy**: `app/Services/Tenancy/` - All tenancy services
- **RBAC**: `app/Models/Role.php`, `app/Models/Permission.php`
- **Middleware**: `app/Http/Middleware/ResolveTenant.php`

### HRMS Reference (for porting)
- **Models**: `/hrms/app/Models/`
- **Migrations**: `/hrms/database/migrations/`
- **Controllers**: `/hrms/app/Http/Controllers/Tenant/`
- **Routes**: `/hrms/routes/tenant.php`

### UI Theme Reference
- **Metronic**: `/metronic-tailwind-html-demos/`

---

## Next Steps

1. ✅ Understand starterkit architecture (read `manual.md`)
2. ⏳ Configure starterkit for UDW (Track 00 tasks)
3. ⏳ Build module system (Track 03)
4. ⏳ Port HRMS as first module (Track 04)

---

## Related Documents

- [Project Structure](PROJECT_STRUCTURE.md)
- [Modular Architecture](architecture/MODULAR_ARCHITECTURE.md)
- [Customization Strategy](architecture/CUSTOMIZATION_STRATEGY.md)
- [Enterprise Guidelines](guidelines/ENTERPRISE_GUIDELINES.md)
- [TDD Workflow](guidelines/TDD_WORKFLOW.md)
- [Git Workflow](guidelines/GIT_WORKFLOW.md)
- [Starterkit Features](reference/STARTERKIT_FEATURES.md)
- [HRMS Inventory](reference/HRMS_INVENTORY.md)
