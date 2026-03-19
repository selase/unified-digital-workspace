# Unified Digital Workspace - Comprehensive Execution Plan

> **Generated:** 2026-02-18 | **Status:** Active
> This plan is independent of what currently exists and serves as the authoritative execution guide.
> Tick checkboxes as phases/tasks are completed.

---

## Vision & Intent

Build an **enterprise-grade, modular, multi-tenant SaaS platform** where institutions get one workspace for all operational modules (HRMS, CMS, Incidents, Documents, Memos, Forums, Projects, Quality Monitoring). Each module behaves like a complete mini-application with full CRUD, workflow, reporting, notifications, and audit trails. The UI is consistent across all modules using Metronic Tailwind CSS (demo1).

**North Star:** A tenant admin enables modules, assigns roles, and their team immediately has a cohesive, production-ready operational workspace.

---

## Current State Assessment

### What Exists (Completed)
- [x] Multi-tenant SaaS starter kit (tenancy, RBAC, billing, usage metering, LLM, feature flags, webhooks, 2FA)
- [x] Module system infrastructure (ModuleManager, discovery, enable/disable, middleware, commands)
- [x] 9 modules with models, migrations, API controllers, API resources, factories
- [x] Metronic shell integration (sidebar, top nav, auth layout, drawers, theme switching)
- [x] 170+ database tables provisioned in PostgreSQL
- [x] 150+ test files covering tenancy, billing, auth, module APIs
- [x] Demo seeder (`CompletedModulesDemoSeeder`) enabling all modules for purpledot tenant
- [x] Navigation system (`WorkspaceNavigation`) with module-context menus

### What's Incomplete (Gaps)
- [ ] Full web CRUD pages for most modules (many have only hub/index views)
- [ ] Dashboard widgets and analytics charts (superadmin + tenant)
- [ ] Cross-module notification engine
- [ ] Workflow depth (approvals, escalations, status transitions) in web UI
- [ ] Export/reporting panels per module
- [ ] Empty states and permission-aware action buttons
- [ ] Activity timeline/audit widgets per module
- [ ] Browser-level regression tests for UI interactions
- [ ] Deployment & operations pipeline (Track 12)
- [ ] Scheduling module (Track 05) not started
- [ ] CRM module (referenced in feature map) not started

---

## Execution Phases

### Phase 1: Shell & Navigation Hardening
> **Goal:** Lock the Metronic shell contract so all subsequent module work has a stable foundation.
> **Priority:** CRITICAL | **Effort:** 1 week

- [ ] **1.1** Audit and fix sidebar navigation for all module routes
  - Verify active state highlighting works for every registered route
  - Fix any module entries that don't show in sidebar when enabled
  - Test sidebar collapse/expand behavior on mobile and desktop

- [ ] **1.2** Finalize top mega menu behavior
  - Top menu shows module-context groups when a module route is active
  - Sub-menus render correctly on hover/click
  - Non-module routes (dashboard, settings) show appropriate top menu

- [ ] **1.3** Fix top-right controls
  - Search modal functional
  - Notifications dropdown renders (even if empty state)
  - Profile dropdown: avatar, name, role, logout
  - Theme switcher (light/dark) persists correctly

- [ ] **1.4** Remove visual artifacts
  - No stale overlays/backdrops when navigating between pages
  - No accidental borders or layout shifts
  - Modals and drawers close cleanly

- [ ] **1.5** Write shell regression tests
  - Pest test: sidebar renders for authenticated users
  - Pest test: top menu changes per module context
  - Pest test: profile dropdown and theme toggle work
  - Pest test: mobile hamburger menu opens sidebar drawer

---

### Phase 2: Module Web Workflow Parity (Tier 1 - Core Modules)
> **Goal:** Complete full web CRUD + workflow pages for the 3 highest-traffic modules.
> **Priority:** HIGH | **Effort:** 3-4 weeks

#### 2A: HRMS Core Web Pages
- [ ] **2A.1** Employee Directory (index page)
  - Metronic `team-crew` table pattern with search, filters, pagination
  - Columns: avatar, name, department, grade, status, actions
  - Permission-aware: create button visible only if `hrms-core.employees.create`

- [ ] **2A.2** Employee Create page
  - Multi-section form (personal info, job details, bank details, emergency contacts)
  - Metronic form card pattern with validation feedback
  - File upload for photo

- [ ] **2A.3** Employee Show page
  - Tabbed profile: Overview, Job History, Leave, Documents, Activity
  - Metronic `user-profile` pattern
  - Action buttons: Edit, Promote, Terminate (permission-gated)

- [ ] **2A.4** Employee Edit page
  - Pre-filled form matching create layout
  - Inline validation with error messages

- [ ] **2A.5** Department Directory page
  - Tree/list view of departments, directorates, units, centers
  - CRUD actions per node

- [ ] **2A.6** Leave Management pages
  - Leave requests index with status filters (pending, approved, rejected)
  - Leave request create/edit forms
  - Leave calendar view (optional, phase 3)
  - Leave balance summary cards

- [ ] **2A.7** Recruitment pages
  - Job postings index with status badges
  - Job posting create/edit forms
  - Candidate applications list with pipeline stages
  - Interview scheduling (basic)

- [ ] **2A.8** HRMS tests
  - Feature test: employee CRUD web flow
  - Feature test: leave request submission and approval
  - Feature test: recruitment job posting lifecycle

#### 2B: CMS Core Web Pages
- [ ] **2B.1** Posts Library (index page)
  - Table with columns: title, type, status, author, date, actions
  - Status badges (draft, published, archived)
  - Bulk actions (publish, archive, delete)

- [ ] **2B.2** Post Create page
  - Rich editor for content (Trix or similar)
  - Sidebar: post type, categories, tags, featured image, SEO meta
  - Draft/Publish toggle

- [ ] **2B.3** Post Show page
  - Rendered content preview
  - Revision history sidebar
  - Metadata display

- [ ] **2B.4** Post Edit page
  - Same as create with pre-filled data
  - Revision comparison (diff view - optional, phase 3)

- [ ] **2B.5** Media Library page
  - Grid/list toggle for media items
  - Upload with drag-and-drop
  - Filter by type (image, video, document)
  - Media detail modal

- [ ] **2B.6** Menu Builder page
  - Drag-and-drop menu item ordering
  - Nested menu support
  - Menu location assignment

- [ ] **2B.7** CMS tests
  - Feature test: post create/publish/edit/delete web flow
  - Feature test: media upload and association
  - Feature test: category/tag management

#### 2C: Incident Management Web Pages
- [ ] **2C.1** Incidents List (index page)
  - Table with: reference, title, category, priority, status, assignee, SLA countdown
  - Color-coded priority badges
  - Quick filters: My Assigned, Open, Overdue, Resolved

- [ ] **2C.2** Incident Create page
  - Form: title, description, category, priority, reporter info, attachments
  - Auto-assign rules (optional)

- [ ] **2C.3** Incident Show page
  - Timeline of events (created, assigned, escalated, resolved)
  - Task checklist within incident
  - Comments section
  - Attachment gallery
  - SLA progress indicator
  - Action buttons: Assign, Escalate, Resolve, Close

- [ ] **2C.4** Incident Edit page
  - Update details, reassign, change priority/status

- [ ] **2C.5** Task Board page
  - Kanban-style board (To Do, In Progress, Done)
  - Or table view of tasks linked to incidents

- [ ] **2C.6** Reports page
  - Summary cards: Open, Resolved Today, Avg Resolution Time, SLA Compliance %
  - Chart: incidents by category (bar), trend over time (line)

- [ ] **2C.7** Incident tests
  - Feature test: incident create/assign/escalate/resolve web flow
  - Feature test: SLA deadline enforcement
  - Feature test: public incident submission

---

### Phase 3: Module Web Workflow Parity (Tier 2 - Supporting Modules)
> **Goal:** Complete web pages for remaining 5 modules.
> **Priority:** HIGH | **Effort:** 3-4 weeks

#### 3A: Document Management Web Pages
- [ ] **3A.1** Document Library (index)
  - Table/grid with: name, type, version, visibility, updated date, actions
  - Folder navigation (if applicable)
  - Upload button + drag-and-drop zone

- [ ] **3A.2** Document Create/Upload page
  - File upload with metadata form (title, description, visibility, tags)
  - Access control settings (department, role, specific users)

- [ ] **3A.3** Document Show page
  - Preview (PDF inline, images inline, other types with download)
  - Version history with diff or rollback
  - Audit trail (who viewed, downloaded, edited)
  - Quiz section if quiz attached

- [ ] **3A.4** Quiz Management pages
  - Quiz creation form (questions, options, correct answers, pass score)
  - Quiz analytics dashboard (pass rate, avg score, attempts)

- [ ] **3A.5** Audit Trail page
  - Filterable log of document access events

- [ ] **3A.6** Document Management tests

#### 3B: Memos Web Pages
- [ ] **3B.1** Memo Index page
  - Table: subject, from, to, status (draft, sent, read, acknowledged), date
  - Quick filters: Inbox, Sent, Drafts, Requiring Signature

- [ ] **3B.2** Memo Create page
  - Rich text editor for memo body
  - Recipient picker (users, departments, roles)
  - Attachment support
  - Signature requirement toggle

- [ ] **3B.3** Memo Show page
  - Formatted memo display
  - Recipient tracking (opened, pending, acknowledged)
  - Digital signature area
  - Minutes and action items section

- [ ] **3B.4** Memo Edit page (drafts only)

- [ ] **3B.5** Memos tests

#### 3C: Forums Web Pages
- [ ] **3C.1** Channel Directory page
  - Channel cards with: name, description, thread count, member count
  - Create channel button (admin only)

- [ ] **3C.2** Thread List page (per channel)
  - Thread rows: title, author, replies, reactions, last activity
  - Pinned threads at top
  - Create thread button

- [ ] **3C.3** Thread Show page
  - Original post with full content
  - Replies in chronological order
  - Reaction buttons
  - Reply editor
  - Best answer marking (for Q&A channels)

- [ ] **3C.4** Messages page
  - Inbox/conversation list
  - Message thread view
  - Compose new message

- [ ] **3C.5** Moderation Queue page
  - Reported content table
  - Approve/reject/ban actions

- [ ] **3C.6** Forums tests

#### 3D: Project Management Web Pages
- [ ] **3D.1** Projects Index page
  - Project cards or table: name, status, progress %, members, deadline
  - Create project button

- [ ] **3D.2** Project Show page (Project Workspace)
  - Tabs: Overview, Tasks, Milestones, Timeline, Team, Time Entries
  - Overview: progress bar, key metrics, recent activity

- [ ] **3D.3** Task Board (Kanban view)
  - Columns: Backlog, To Do, In Progress, Review, Done
  - Drag-and-drop between columns
  - Task cards: title, assignee, priority, due date

- [ ] **3D.4** Task Detail modal/page
  - Description, checklist, comments, attachments
  - Dependencies visualization
  - Time tracking

- [ ] **3D.5** Milestones page
  - Timeline/Gantt-style view
  - Milestone cards with linked tasks

- [ ] **3D.6** Project Management tests

#### 3E: Quality Monitoring Web Pages
- [ ] **3E.1** Workplans Index page
  - Table: name, period, status, objectives count, KPIs count
  - Create workplan button

- [ ] **3E.2** Workplan Show page
  - Objectives with expandable activities
  - KPI indicators with progress bars
  - Variance alerts

- [ ] **3E.3** KPI Dashboard page
  - KPI cards with sparkline charts
  - Filters by objective, period, status
  - Traffic light indicators (green/amber/red)

- [ ] **3E.4** Reports page
  - Performance summary report
  - Variance analysis
  - Export to PDF/Excel

- [ ] **3E.5** Quality Monitoring tests

---

### Phase 4: Dashboards & Analytics
> **Goal:** Build meaningful dashboards for superadmin and tenant users.
> **Priority:** MEDIUM | **Effort:** 2 weeks

- [ ] **4.1** Superadmin Dashboard
  - Stat cards: Total Tenants, Active Subscriptions, MRR, Active Users
  - Chart: new tenants over time (line)
  - Chart: revenue by package tier (donut)
  - Recent activity feed
  - Health status summary
  - Use Metronic `dashboards/` pattern with ApexCharts

- [ ] **4.2** Tenant Dashboard
  - Stat cards: Employees (HRMS), Open Incidents, Active Projects, Unread Memos
  - Module quick-access cards (only enabled modules)
  - Recent activity feed across all modules
  - Announcements/posts from CMS
  - Use Metronic `index.html` dashboard pattern

- [ ] **4.3** Module-specific dashboard widgets
  - HRMS: headcount by department, leave summary, upcoming birthdays
  - Incidents: open vs resolved trend, SLA compliance gauge
  - Projects: burndown chart, tasks by status
  - Quality: KPI traffic light summary, variance alerts count

- [ ] **4.4** Dashboard tests

---

### Phase 5: Notifications & Workflow Engine
> **Goal:** Add event-driven notifications and cross-module workflow depth.
> **Priority:** MEDIUM | **Effort:** 2-3 weeks

- [ ] **5.1** Notification infrastructure
  - Laravel notification system (database + mail channels)
  - Notification model with tenant scoping
  - Notification bell in top-right header with count badge
  - Notification dropdown panel
  - Mark as read/unread
  - "View all" link to notifications index page

- [ ] **5.2** Module notification triggers
  - HRMS: leave request submitted/approved/rejected, promotion decision
  - Incidents: assigned to you, escalated, SLA approaching, resolved
  - Memos: new memo received, signature requested
  - Documents: document shared with you, quiz assigned
  - Projects: task assigned, task due soon, milestone reached
  - Forums: reply to your thread, mention

- [ ] **5.3** Approval workflows
  - Leave approval chain (employee -> supervisor -> HR)
  - Memo signing workflow (sequential signers)
  - Promotion approval (department head -> HR -> admin)
  - Configurable per tenant

- [ ] **5.4** Escalation engine
  - Incident auto-escalation on SLA breach
  - Overdue task escalation
  - Unapproved leave escalation
  - Configurable escalation rules per module

- [ ] **5.5** Activity timeline widget
  - Reusable Blade component showing chronological activity
  - Used in: Employee profile, Incident detail, Project workspace, Memo detail
  - Integrates with Spatie Activity Log

- [ ] **5.6** Notification & workflow tests

---

### Phase 6: Shared UI Components & Patterns
> **Goal:** Build reusable Blade components for consistent UI across all modules.
> **Priority:** MEDIUM | **Effort:** 1-2 weeks

- [ ] **6.1** Reusable Blade components
  - `x-metronic-table` - Standard table with sorting, search, filters, pagination
  - `x-metronic-card` - Card with header, body, footer, action buttons
  - `x-metronic-stat-card` - Metric card with icon, value, trend indicator
  - `x-metronic-form-section` - Form section with title, description
  - `x-metronic-modal` - Standard modal with confirm/cancel
  - `x-metronic-empty-state` - Empty state with icon, message, CTA button
  - `x-metronic-status-badge` - Color-coded status badge
  - `x-metronic-avatar` - User avatar with fallback to initials/gravatar
  - `x-metronic-breadcrumb` - Breadcrumb navigation
  - `x-metronic-file-upload` - Drag-and-drop file upload zone
  - `x-metronic-timeline` - Activity timeline feed

- [ ] **6.2** Alpine.js interaction patterns
  - Confirm delete dialog
  - Inline edit toggle
  - Filter toggle panel
  - Tab switching
  - Dropdown menus
  - Toast notifications

- [ ] **6.3** Permission-aware action buttons
  - Buttons conditionally rendered based on user permissions
  - Disabled state with tooltip for insufficient permissions
  - Consistent across all modules

- [ ] **6.4** Empty states for every module list page
  - Icon, descriptive message, CTA to create first item
  - Different messages per module context

- [ ] **6.5** Component tests (Blade rendering)

---

### Phase 7: Export, Reporting & Search
> **Goal:** Add data export, reporting, and search capabilities across modules.
> **Priority:** MEDIUM | **Effort:** 2 weeks

- [ ] **7.1** Export infrastructure
  - CSV export for all major list views (employees, incidents, projects, etc.)
  - PDF export for detail views (employee profile, incident report, memo)
  - Excel export using `maatwebsite/excel` (already installed)
  - Export buttons on all index pages

- [ ] **7.2** Module reports
  - HRMS: headcount report, leave utilization, recruitment pipeline
  - Incidents: resolution time analysis, category breakdown, SLA compliance
  - Projects: project status summary, resource utilization, timeline adherence
  - Quality: KPI performance report, variance analysis, trend report

- [ ] **7.3** Global search
  - Search bar in top header (already in shell)
  - Search across modules: employees, incidents, documents, memos, projects
  - Results grouped by module
  - Quick navigation to result

- [ ] **7.4** Report & export tests

---

### Phase 8: Testing & Quality Hardening
> **Goal:** Achieve comprehensive test coverage and code quality gates.
> **Priority:** HIGH | **Effort:** 2 weeks (ongoing throughout)

- [ ] **8.1** Module web CRUD tests
  - Every module: index renders, create form submits, show page loads, edit saves, delete works
  - Test file: `tests/Feature/Modules/Web/` per module
  - Use `OperationalModuleCrudTest.php` pattern for consistency

- [ ] **8.2** Permission enforcement tests
  - Unauthorized users get 403 on all protected routes
  - Module middleware blocks access when module disabled
  - Tenant isolation: users cannot access other tenant data

- [ ] **8.3** Browser interaction tests
  - Sidebar navigation renders correctly
  - Modals open and close
  - Dropdowns function
  - Forms submit with validation
  - Datatables render with data

- [ ] **8.4** API endpoint tests
  - All API v1 endpoints return correct status codes
  - Pagination works
  - Filters work
  - Validation returns proper error messages

- [ ] **8.5** Code quality gates
  - `vendor/bin/pint --dirty` passes before every commit
  - `php artisan test --compact` passes before every PR
  - PHPStan level 8 compliance (stretch goal)

- [ ] **8.6** Seed data integrity
  - `CompletedModulesDemoSeeder` runs cleanly on fresh DB
  - All module seeders produce valid, navigable demo data
  - Module seeders are idempotent

---

### Phase 9: Superadmin & Platform Management
> **Goal:** Complete the superadmin experience for managing the SaaS platform.
> **Priority:** MEDIUM | **Effort:** 2 weeks

- [ ] **9.1** Tenant Management pages
  - Tenant index: name, domain, status, package, users count, modules enabled
  - Tenant show: detail view with modules, users, billing, health
  - Tenant create: provisioning wizard
  - Module enable/disable per tenant from admin UI

- [ ] **9.2** Billing & Subscription pages
  - Subscription management: view, upgrade, downgrade, cancel
  - Invoice generation and viewing
  - Payment history
  - Revenue analytics dashboard

- [ ] **9.3** User & Role Management
  - Global user directory
  - Role and permission management with matrix view
  - User impersonation (for debugging)

- [ ] **9.4** Platform Health
  - Tenant health dashboard (green/yellow/red per tenant)
  - Application health checks
  - Database connection status
  - Queue worker status

- [ ] **9.5** Audit Trail
  - Global activity log with filters
  - Security event log
  - Login history

- [ ] **9.6** Superadmin tests

---

### Phase 10: Scheduling Module (Track 05)
> **Goal:** Build the scheduling module for shift/roster management.
> **Priority:** LOW (deferred) | **Effort:** 3-4 weeks

- [ ] **10.1** Schema design
  - Shifts, shift patterns, shift assignments, swap requests
  - Integration points with HRMS employees and leave

- [ ] **10.2** Models, migrations, factories
- [ ] **10.3** API controllers and resources
- [ ] **10.4** Web pages (roster view, shift assignment, swap requests)
- [ ] **10.5** Calendar view component
- [ ] **10.6** Leave integration (auto-block shifts on approved leave)
- [ ] **10.7** Coverage reports and compliance checks
- [ ] **10.8** Scheduling tests

---

### Phase 11: Deployment & Operations (Track 12)
> **Goal:** Production-ready deployment pipeline and operational tooling.
> **Priority:** MEDIUM (after feature completeness) | **Effort:** 2 weeks

- [ ] **11.1** Production infrastructure setup
  - Laravel Forge or AWS deployment scripts
  - PostgreSQL production configuration
  - Redis for cache and queues
  - S3 for tenant file storage

- [ ] **11.2** CI/CD pipeline
  - GitHub Actions: lint, test, build on PR
  - Auto-deploy to staging on merge to `dev`
  - Manual deploy to production from `main`

- [ ] **11.3** Backup & recovery
  - Automated daily backups (database + files)
  - Tenant-level backup/restore capability
  - Disaster recovery runbook

- [ ] **11.4** Monitoring & alerting
  - Application health checks (Spatie Health)
  - Error tracking (Sentry or similar)
  - Uptime monitoring
  - Queue worker monitoring
  - Alert channels (email, Slack)

- [ ] **11.5** Update mechanism
  - On-premise: `git pull` + `composer install` + `migrate` + `optimize`
  - SaaS: zero-downtime deployment with queue drain
  - Tenant migration orchestration for schema changes

- [ ] **11.6** Operational documentation
  - Runbook: common operations
  - Incident response procedure
  - Scaling guide

---

### Phase 12: Advanced Features & Polish
> **Goal:** Enterprise features that differentiate the platform.
> **Priority:** LOW (future) | **Effort:** 4-6 weeks

- [ ] **12.1** CRM Module (new)
  - Contacts and organizations directory
  - Interaction timeline
  - Pipeline management
  - Campaign builder

- [ ] **12.2** Dynamic Forms Module
  - Form builder with conditional logic
  - Form-to-case routing
  - HR self-service forms

- [ ] **12.3** Workflow Builder
  - Visual if-this-then-that builder
  - Shared approval engine across modules
  - Custom automation rules per tenant

- [ ] **12.4** Advanced Reporting
  - Scheduled report delivery (email)
  - Custom report builder
  - Data quality checks and anomaly alerts

- [ ] **12.5** SSO / SAML Integration
  - Single sign-on for enterprise tenants
  - OAuth2 provider support

- [ ] **12.6** Mobile-responsive polish
  - All module pages fully responsive
  - Mobile-optimized table patterns
  - Touch-friendly interactions

---

## Metronic UI Pattern Reference

| Pattern Need | Demo1 Reference File | Use For |
|---|---|---|
| Data table (standard) | `network/user-table/team-crew.html` | Employee, incident, project, document lists |
| User cards grid | `network/user-cards/team-crew.html` | Employee directory, team members |
| Profile page | `account/home/user-profile.html` | Employee show, user profile |
| Company profile | `account/home/company-profile.html` | Organization settings |
| Settings sidebar | `account/home/settings-sidebar.html` | Module settings, tenant settings |
| Form layouts | `account/home/settings-plain.html` | Create/edit forms |
| Auth pages | `authentication/classic/sign-in.html` | Login, register, 2FA |
| Dashboard | `index.html` | Tenant and superadmin dashboards |
| Team members | `account/members/team-members.html` | Role management, team assignment |
| Permission matrix | `account/members/permissions-check.html` | Permission management |
| Billing plans | `account/billing/plans.html` | Subscription management |
| Security log | `account/security/security-log.html` | Audit trail |
| Activity timeline | `public-profile/activity.html` | Module activity feeds |
| Projects grid | `public-profile/projects/3-columns.html` | Project cards |
| CRM layout | `public-profile/profiles/crm.html` | Contact management |
| Search results | `store-client/search-results-list.html` | Global search, document list |
| Order/invoice | `store-client/order-receipt.html` | Invoice display |
| Empty states | `public-profile/empty.html` | No-data states |

---

## Execution Priority Order

```
Phase 1  ─── Shell & Navigation (CRITICAL - do first)
    │
Phase 2  ─── Tier 1 Modules: HRMS, CMS, Incidents (HIGH)
    │
Phase 6  ─── Shared UI Components (run in parallel with Phase 2)
    │
Phase 3  ─── Tier 2 Modules: Docs, Memos, Forums, Projects, QM (HIGH)
    │
Phase 4  ─── Dashboards & Analytics (MEDIUM)
    │
Phase 5  ─── Notifications & Workflows (MEDIUM)
    │
Phase 8  ─── Testing Hardening (ongoing, peaks here)
    │
Phase 7  ─── Export, Reports, Search (MEDIUM)
    │
Phase 9  ─── Superadmin Platform Management (MEDIUM)
    │
Phase 11 ─── Deployment & Operations (after feature freeze)
    │
Phase 10 ─── Scheduling Module (deferred)
    │
Phase 12 ─── Advanced Features (future roadmap)
```

---

## Quality Gates (Applied to Every Phase)

Before marking any phase complete:

1. **Tests pass:** `php artisan test --compact` - zero failures
2. **Code formatted:** `vendor/bin/pint --dirty` - no changes needed
3. **Demo data works:** `CompletedModulesDemoSeeder` runs and all pages navigable
4. **UI consistent:** Pages follow Metronic demo1 patterns, no Bootstrap remnants
5. **Permissions enforced:** Unauthorized access returns 403
6. **Tenant isolation:** No cross-tenant data leaks
7. **Mobile responsive:** Pages render properly on mobile viewport

---

## Key Files Reference

| Purpose | Path |
|---|---|
| Master docs | `docs/EXECUTION_MASTER_PLAN.md` |
| Architecture | `docs/architecture/MODULAR_ARCHITECTURE.md` |
| Handover notes | `handing_over_notes.md` |
| Starter kit docs | `STARTERKIT.md` |
| **This plan** | `EXECUTION_PLAN.md` |
| Module manager | `app/Services/ModuleManager.php` |
| Navigation | `app/Services/Navigation/WorkspaceNavigation.php` |
| App layout | `resources/views/layouts/metronic/app.blade.php` |
| Auth layout | `resources/views/layouts/metronic/auth.blade.php` |
| Global routes | `routes/web.php` |
| Tenant routes | `routes/subdomain.php` |
| Module routes | `app/Modules/*/Routes/web.php` |
| Demo seeder | `database/seeders/CompletedModulesDemoSeeder.php` |
| CRUD tests | `tests/Feature/Modules/Web/OperationalModuleCrudTest.php` |
| Metronic demos | `metronic-tailwind-html-demos/dist/html/demo1/` |

---

## Login Credentials (Development)

| Role | Email | Password |
|---|---|---|
| Superadmin | `hiselase@gmail.com` | `password` |
| Tenant User (purpledot) | `dev@wearepurpledot.com` | `password` |

---

## Commands Quick Reference

```bash
# Setup
composer install && npm install && npm run build
php artisan migrate --database=landlord
php artisan db:seed
php artisan db:seed --class=CompletedModulesDemoSeeder

# Module operations
php artisan module:list
php artisan module:enable <slug> --tenant=<uuid>
php artisan module:migrate <slug> --tenant=<uuid>

# Quality
vendor/bin/pint --dirty
php artisan test --compact
php artisan test --compact --filter=OperationalModuleCrudTest

# Development
npm run dev        # Frontend hot reload
composer run dev   # Full dev server
```
