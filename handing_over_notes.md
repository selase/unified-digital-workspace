# Unified Digital Workspace (UDW) - Handover Notes

## 1) What This Application Is

UDW is a modular, multi-tenant SaaS platform built on Laravel 12 for institutions that need one workspace for operational modules (HRMS, CMS, incident, documents, memos, forums, projects, quality monitoring, etc).

It supports:

- A global (landlord) control plane for superadmin functions.
- Tenant workspaces with strict tenant scoping.
- Per-tenant module enablement and feature entitlement.
- Shared DB, dedicated DB-per-tenant, and BYO DB patterns.
- Metronic Tailwind UI shell (demo1-inspired) for consistent navigation and component styling.

---

## 2) Big Goal (North Star)

Deliver an enterprise-grade institutional platform where:

- Superadmin can manage tenants, subscriptions, pricing, billing, governance, and platform health.
- Each tenant can enable only relevant modules and get a cohesive, role-based workspace.
- Each module behaves like a complete mini-application (CRUD + workflow + reporting + notifications + auditability).
- UI remains consistent and scalable as modules grow.

Practical target state:

- Stable tenancy and entitlement enforcement.
- Complete operational module workflows for tenant users.
- Production-ready confidence via tests (Pest), linting (Pint), static checks (PHPStan), and staged rollout discipline.

---

## 3) Product Scope (Current Program)

Primary program scope used in this repository:

- Tenancy stability and hardening.
- HRMS.
- CMS.
- Document Management.
- Memo Management.
- Quality Monitoring.
- Incident Management track branch merged into main flow.
- Metronic demo1-based UI migration for superadmin + tenant shells and module pages.

Reference: `docs/EXECUTION_MASTER_PLAN.md`

---

## 4) Architecture Summary

### 4.1 Core platform foundation

This codebase uses an existing multi-tenant starter architecture with UDW extensions.

Key areas:

- Tenant resolution/context: `app/Http/Middleware/ResolveTenant.php`, `app/Services/Tenancy/TenantContext.php`, `app/Services/Tenancy/TenantResolver.php`
- Tenant DB orchestration: `app/Services/Tenancy/TenantDatabaseManager.php`
- Tenant provisioning/migration: `app/Services/Tenancy/TenantProvisioner.php`, `app/Services/Tenancy/TenantMigrator.php`
- RBAC/permissions: `app/Models/Role.php`, `app/Models/Permission.php`, Spatie teams integration
- Module gate middleware: `app/Http/Middleware/EnsureModuleEnabled.php`

### 4.2 Module system

- Discovery and lifecycle: `app/Services/ModuleManager.php`
- Provider bootstrap: `app/Providers/ModuleServiceProvider.php`
- Base module provider behavior: `app/Modules/Concerns/ModuleServiceProvider.php`
- Module commands:
    - `app/Console/Commands/ModuleListCommand.php`
    - `app/Console/Commands/ModuleEnableCommand.php`
    - `app/Console/Commands/ModuleDisableCommand.php`
    - `app/Console/Commands/ModuleMigrateCommand.php`

### 4.3 Navigation shell

- Dynamic sidebar + top menus per route/module context:
    - `app/Services/Navigation/WorkspaceNavigation.php`
- Main app shell:
    - `resources/views/layouts/metronic/app.blade.php`
- Auth shell:
    - `resources/views/layouts/metronic/auth.blade.php`

---

## 5) Multi-Tenancy and Migration Strategy (Critical)

This is the most important operational decision to preserve.

### 5.1 Landlord DB should contain

- Global users/tenants.
- Global roles/permissions catalog.
- Tenant-module enablement (`tenant_modules`) and global entitlements.
- Billing/subscription/usage control plane entities.

### 5.2 Tenant DB should contain

- Module business data (HRMS, CMS, incidents, documents, memos, etc.).
- Tenant-scoped operational records and workflow state.

### 5.3 Where module migrations belong

Module migrations should run against **tenant** connection and reside in module folders:

- `app/Modules/*/Database/Migrations`

Landlord migrations stay in:

- `database/migrations/landlord`

Tenant base migrations stay in:

- `database/migrations/tenant`

### 5.4 On tenant onboarding / initialization

For a new tenant, initialization should:

1. Create/resolve tenant DB connection profile.
2. Run base tenant migrations.
3. Run migrations for each enabled module.
4. Seed module defaults for enabled modules as needed.

Current command support:

- `php artisan module:enable <slug> --tenant=<tenant-uuid>` (includes migration attempt)
- `php artisan module:migrate <slug> --tenant=<tenant-uuid>`
- `php artisan module:migrate --all-tenants`

---

## 6) Module Inventory and Where to Find Code

Modules currently present under `app/Modules`:

- `Core`
- `HrmsCore`
- `CmsCore`
- `DocumentManagement`
- `Memos`
- `IncidentManagement`
- `Forums`
- `ProjectManagement`
- `QualityMonitoring`

For each module, look at:

- `Config/module.php` (manifest: slug, features, permissions, tier, dependencies)
- `Database/Migrations/`
- `Http/Controllers/`
- `Http/Requests/`
- `Routes/web.php` + `Routes/api.php`
- `Views/`
- `Database/Seeders/`

Notable web CRUD flow coverage exists in:

- `tests/Feature/Modules/Web/OperationalModuleCrudTest.php`

---

## 7) Routing Model (Superadmin vs Tenant)

### 7.1 Superadmin/global routes

- `routes/web.php`
- Includes admin dashboard, tenant management, global billing analytics, health, user/role management.

### 7.2 Tenant/subdomain routes

- `routes/subdomain.php`
- Includes tenant dashboard, settings, billing, users/roles, tenant finance, tenant API keys, LLM config.

### 7.3 Module routes

Registered through module service providers and mounted as:

- Web: `/<module-slug>/...`
- API: `/api/<module-slug>/...`
  With middleware:
- `auth` + `module:<slug>` for web
- `auth:sanctum` + `module:<slug>` for API

---

## 8) Seeders and Demo Data

Base seed flow:

- `database/seeders/DatabaseSeeder.php`
    - Role, permission, package, tenant, user bootstrap.

Demo/completion seed flow:

- `database/seeders/CompletedModulesDemoSeeder.php`
    - Enables major modules for `purpledot`.
    - Grants module permissions to tenant user.
    - Runs module seeders for demo walkthrough.

Default login users (from `database/seeders/UserSeeder.php`):

- Superadmin: `hiselase@gmail.com` / `password`
- Purpledot tenant user: `dev@wearepurpledot.com` / `password`

---

## 9) UI System (Metronic) - Current Direction

### 9.1 Source of truth for design assets

- Metronic demo files: `metronic-tailwind-html-demos/dist/html/demo1/`
- Runtime app assets: `public/assets/metronic/`

### 9.2 Current shell files

- `resources/views/layouts/metronic/app.blade.php`
- `resources/views/layouts/metronic/auth.blade.php`

### 9.3 Navigation behavior target

- Left sidebar contains dashboards, tenant links, and module entries.
- Top mega menu should show module-context groups and submenu links when a module is active.
- Top-right controls should use demo1 idioms (search, notifications, chat, app switcher, profile dropdown).

### 9.4 High-value demo1 references to reuse now

- Shell + mega menu + top-right behavior:
    - `metronic-tailwind-html-demos/dist/html/demo1/index.html`
- Table patterns:
    - `metronic-tailwind-html-demos/dist/html/demo1/network/user-table/team-crew.html`
    - `metronic-tailwind-html-demos/dist/html/demo1/account/members/team-members.html`
- Auth page pattern:
    - `metronic-tailwind-html-demos/dist/html/demo1/authentication/classic/sign-in.html`
- Account/settings card patterns:
    - `metronic-tailwind-html-demos/dist/html/demo1/account/home/`

### 9.5 Charting library in Metronic

Metronic demo integration uses ApexCharts.

- CSS: `public/assets/metronic/vendors/apexcharts/apexcharts.css`
- JS: `public/assets/metronic/vendors/apexcharts/apexcharts.min.js`

---

## 10) Known Engineering Constraints and Guardrails

- Do not reintroduce legacy Bootstrap classes on Metronic pages.
- Preserve module middleware checks (`module:<slug>`) on all module routes.
- Keep tenant scoping explicit for all module models and queries.
- Avoid direct DB facade shortcuts when Eloquent relation scope can be used.
- Keep UUID-vs-int key usage consistent across landlord vs tenant boundaries.

Common failure classes already seen:

- Route-model binding selecting wrong DB connection.
- Foreign key/user reference type mismatch (UUID in one side, bigint in the other).
- Tenant table schema mismatch (selecting non-existent columns).
- UI regressions from shell JS resetting/modals/drawers incorrectly.

---

## 11) Execution Phases (Suggested from Here)

### Phase A - Stabilize shell and module navigation

- Lock top mega menu behavior to module context.
- Finalize sidebar + topbar interaction contracts.
- Remove accidental layout borders/visual artifacts.

### Phase B - Complete module web workflow parity

For each module (start with HRMS, CMS, Incident):

- Finish list/create/show/edit/delete screens.
- Add status/workflow actions and domain actions.
- Add exports/reporting panels.
- Add empty states + permission-aware actions.

### Phase C - Notifications and workflow depth

- Add event-driven notifications per module.
- Add reminders/escalation jobs where applicable.
- Add activity timeline/audit widgets.

### Phase D - Hardening and quality gates

- Expand focused feature tests per module flow.
- Expand browser-level smoke checks for menus/drawers/dropdowns/forms.
- Run lint + static checks before each merge.

### Phase E - Deployment track (after feature freeze)

- AWS/Forge hardening.
- Backup/restore and tenant-level DR rehearsal.
- Monitoring, alerts, and runbooks.

---

## 12) What a Novice Should Do First (Step-by-Step)

1. Read this file fully.
2. Read `docs/EXECUTION_MASTER_PLAN.md`.
3. Read `manual.md` (starter platform behavior).
4. Run base seeders and confirm login works.
5. Run `CompletedModulesDemoSeeder` and explore module hubs.
6. Open `WorkspaceNavigation` and understand how menu links are built.
7. Open `layouts/metronic/app.blade.php` and trace shell regions:
    - sidebar
    - top mega menu
    - top-right controls
8. Pick one module page, align it to a specific demo1 reference page, then test.

---

## 13) LLM Handoff Context Pack

If handing this project to another LLM, provide this minimal context:

- This is Laravel 12 + PHP 8.4 multi-tenant SaaS.
- Tenancy modes: shared / db_per_tenant / BYO.
- Landlord DB is control plane; tenant DB stores module data.
- Modules live under `app/Modules/*` with their own migrations/routes/views.
- Module enablement and feature entitlement are tenant-specific.
- UI target is Metronic Tailwind demo1 shell and components.
- Do not remove module middleware or tenant scoping.
- Preserve top menu contextual behavior based on active module.
- Validate every change with targeted Pest tests and Pint.

Recommended first prompts for an incoming LLM:

- "Audit `WorkspaceNavigation` and `layouts/metronic/app.blade.php` to ensure module-context top menu and hover submenus render on all module routes."
- "For CMS and HRMS, list missing web pages compared to complete CRUD + workflow expectations, then implement in priority order with tests."
- "Run tenancy integrity checks for UUID/int mismatches and wrong-connection queries in module controllers and model binding."

---

## 14) Operational Commands (Quick Reference)

Environment and install:

- `composer install`
- `npm install`
- `npm run build` (or `npm run dev` during active frontend changes)

Database and seed:

- `php artisan migrate --database=landlord`
- `php artisan db:seed`
- `php artisan db:seed --class=CompletedModulesDemoSeeder`

Module operations:

- `php artisan module:list`
- `php artisan module:enable <slug> --tenant=<tenant-uuid>`
- `php artisan module:disable <slug> --tenant=<tenant-uuid>`
- `php artisan module:migrate <slug> --tenant=<tenant-uuid>`

Quality checks:

- `vendor/bin/pint --dirty`
- `php artisan test --compact`
- `php artisan test --compact tests/Feature/MetronicInteractionSmokeTest.php`
- `php artisan test --compact tests/Feature/Modules/Web/OperationalModuleCrudTest.php`

---

## 15) Where To Find What (Fast Lookup)

- Master plan: `docs/EXECUTION_MASTER_PLAN.md`
- Architecture: `docs/architecture/MODULAR_ARCHITECTURE.md`
- Customization strategy: `docs/architecture/CUSTOMIZATION_STRATEGY.md`
- Starter platform manual: `manual.md`
- Module manager: `app/Services/ModuleManager.php`
- Tenant services: `app/Services/Tenancy/`
- Tenant resolver middleware: `app/Http/Middleware/ResolveTenant.php`
- Module middleware: `app/Http/Middleware/EnsureModuleEnabled.php`
- Navigation mapping: `app/Services/Navigation/WorkspaceNavigation.php`
- Metronic app layout: `resources/views/layouts/metronic/app.blade.php`
- Metronic auth layout: `resources/views/layouts/metronic/auth.blade.php`
- Global routes: `routes/web.php`
- Tenant routes: `routes/subdomain.php`
- Module routes: `app/Modules/*/Routes/`
- Demo seed orchestration: `database/seeders/CompletedModulesDemoSeeder.php`
- Demo design source: `metronic-tailwind-html-demos/dist/html/demo1/`

---

## 16) Recommended Immediate Next Actions

1. Freeze shell contract in one checklist: sidebar visibility, top mega menu, top-right controls, overlays.
2. Run a targeted UI sweep for module create/edit pages against demo1 patterns.
3. Close high-impact CRUD and workflow gaps in HRMS, CMS, Incident first.
4. Add explicit regression tests for:
    - module-context top menu visibility
    - profile dropdown and drawer interactions
    - tenant route context rendering
5. Only then proceed to deployment/ops track.

---

## 17) Final Notes

- This is already a substantial platform with strong modular foundations.
- The current highest-value work is consistency and completeness: UI parity + full module workflows + regression protection.
- Keep architecture decisions stable (landlord control plane, tenant data plane, module isolation) while polishing the user experience.
