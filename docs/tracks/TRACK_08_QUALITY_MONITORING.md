**Duration:** 5 weeks
**Status:** 🔲 To Build
**Dependencies:** Track 03, Track 04

---

## Overview

Monitoring, Evaluation & Performance (MEP) Workplan module (Gemini Conductor style): staff plan → submit → approve → execute → update, with variance handling, approvals, scheduling, dashboards, and exports.

---

## Success Criteria
- Staff can create → submit → approve → execute → update a workplan.
- Approvers review/comment/approve/reject; can request changes.
- Due schedules generate reminders and overdue notifications/escalations.
- Variance reasons captured for missed timelines/targets (optional supervisor review).
- Heads can filter by directorate/department/unit/staff.
- Audit trail + versioning + PDFs/Excel exports (MVP) available.

---

## Epics & Deliverables

### Epic 1 — Org Structure & Scope Controls
Objective: Model org hierarchy and enforce visibility (Staff, HOU, HOD, Director, M&E Admin).
- Org entities: directorates, departments, units; staff linked to unit.
- Roles/scope rules: owner, approval chain, org-scope visibility.
- Filters by directorate/department/unit/staff; enforced at query level.

### Epic 2 — Workplan Lifecycle & Versioning
Objective: Draft → Submit → Under Review → Approved/Rejected → Archived; immutable submitted versions.
- Workplan container (owner, period/year, org scope, status).
- `workplan_versions` with version_no; copy-on-submit; immutable submitted versions.
- Required fields before submission; revision flow for changes after approval.

### Epic 3 — Workplan Builder (Table 1)
Objective: Spreadsheet-like entry for objectives, breakdown, activities, timeline, indicators, targets, resources, responsibility.
- Rows include: specific objective, objective breakdown, strategic activity, timeline (Q1–Q4), output indicator, KPI, data source, frequency, target, resources, responsible.
- Table-like UI: add/duplicate/reorder rows; inline validation; optional Excel import/carry-forward (MVP+).

### Epic 4 — Indicator & Data Source Registry
Objective: Reusable indicators/KPIs and data sources.
- Indicator entity (output/kpi), unit, definition, formula notes; baselines/targets (MVP+ thresholds optional).
- Data source registry with custodian/collection method.
- Items link to indicators/data sources.

### Epic 5 — Approval Engine (HOU/HOD/Directorate)
Objective: Configurable approval chains; approver inbox.
- Chain by scope (unit/department/directorate/staff_level), e.g., HOU → HOD → Director.
- Inbox with filters; review tools (comment, request changes, approve/reject), audit trail.

### Epic 6 — Scheduling, Deadlines & Notifications
Objective: Generate due dates from timeline/frequency; reminders/escalations.
- Due date generation from Q1–Q4 end dates and measurement frequency.
- Reminder cadence: T-14, T-7, T-2, T+1 overdue (default); in-app, email optional.
- Escalation to next supervisor after X days (configurable).

### Epic 7 — Progress Updates & Evidence
Objective: Progress per activity with evidence and history.
- Update entry: %/status, notes, evidence upload (files/images/docs).
- Evidence requirement per KPI (optional setting).
- History timeline per item (who/when, attachments).

### Epic 8 — Variance / Deviation
Objective: Accountability for missed plans.
- Triggers: overdue activity, overdue measurement, off-target KPI (optional threshold).
- Variance form: category, narrative, impact, corrective action, revised date, evidence.
- Supervisor review (accept/request clarification/flag).

### Epic 9 — Dashboards, Reporting & Exports
Objective: Staff/leadership dashboards; exports.
- Staff: my plan status, due soon/overdue, KPI measurements due, progress by quarter.
- Leadership: submission compliance, overdue heatmap by unit/department, variance distribution.
- Reports: quarterly, mid-year, annual; exports: Table 1 layout, KPI scorecards (PDF/Excel).

### Epic 10 — Administration, Configuration & Audit
Objective: Configurable settings and full audit.
- Settings: submission deadlines, reminder cadence, escalation intervals, controlled vocabularies (reasons, frequency).
- Approval chain templates by scope.
- Audit log of submissions/approvals/edits/variances.

---

## Constraints
- PostgreSQL-friendly types (jsonb; no enum columns).
- Tenant scoped; module middleware enforced.
- Activity logging for submissions/reviews/score changes/variance.
- Attachments via `Helper::processUploadedFile` (disk/path/mime/size).

---

## Schema (Tenant)
- `qm_workplans`: id, uuid, tenant_id, title, period_start, period_end, status, owner_id, org_scope (directorate/department/unit), metadata (jsonb), timestamps, soft deletes.
- `qm_workplan_versions`: id, workplan_id, version_no, status, payload (jsonb), submitted_at, approved_at, created_by, timestamps.
- `qm_objectives`: id, workplan_id, title, description, weight, status, sort_order, timestamps.
- `qm_activities`: id, objective_id, title, description, responsible_id, start_date, due_date, status, weight, sort_order, timestamps.
- `qm_kpis`: id, activity_id, indicator_id (nullable), name, unit, target_value, baseline_value, direction (string), calculation (jsonb), frequency, timestamps.
- `qm_kpi_updates`: id, kpi_id, value, captured_at, note, captured_by_id, evidence_path/mime/size, timestamps.
- `qm_indicators`: id, name, type (output/kpi), unit, definition, formula_notes, metadata (jsonb), timestamps.
- `qm_data_sources`: id, name, description, method, custodian, quality_notes, metadata (jsonb), timestamps.
- `qm_reviews`: id, workplan_id, reviewer_id, status, comments, scores (jsonb), submitted_at, approved_at, timestamps.
- `qm_alerts`: id, workplan_id, kpi_id, type (overdue/underperform), status, metadata (jsonb), sent_at, timestamps.
- `qm_variances`: id, workplan_id, activity_id, kpi_id, category, impact_level, narrative, corrective_action, revised_date, evidence_path/mime/size, status, reviewed_by_id, reviewed_at, timestamps.

Indexes: workplans (tenant_id, status, period_start/period_end, org_scope), versions (workplan_id, version_no), activities (objective_id, status, due_date), kpis (activity_id, direction/frequency), updates (kpi_id, captured_at), alerts (status, type), variances (status, category, workplan_id), indicators (type, name).

---

## API Surface (v1)
- `GET /api/quality/v1/workplans` (filters: status, period, org scope, owner)
- `POST/PUT/DELETE /workplans/{uuid}`
- `POST /workplans/{uuid}/submit`, `/approve`, `/reject`, `/revision`
- `POST /workplans/{uuid}/objectives`, `/objectives/{id}/activities`
- `POST /activities/{id}/kpis`, `POST /kpis/{id}/updates`
- `POST /workplans/{uuid}/reviews`
- `POST /activities/{id}/variance`
- `GET /workplans/{uuid}/dashboard` (status/KPI/alerts)
- `GET /alerts` and `POST /alerts/{id}/ack`
- `GET /indicators`, `GET /data-sources`

---

## Permissions
- `qm.workplans.view`, `qm.workplans.manage`
- `qm.approvals.manage`, `qm.kpis.manage`, `qm.reviews.manage`, `qm.alerts.manage`, `qm.variances.manage`

---

## Tests
- Workplan/objective/activity CRUD, lifecycle, and version immutability after submission
- Approval routing by org scope and chain configuration
- KPI updates with evidence; direction/target calculations
- Alerts generation for overdue/underperforming; escalations
- Variance triggers and review flow
- Dashboards/reporting aggregates and exports

---

## MVP Plan
- MVP 1: Epics 1,2,3,5,6 (in-app),7,9 (basic)
- MVP 2: Epics 4,8,9 (full),10
- MVP 3: Integrations/auto-calc KPIs; evidence governance enforcement
