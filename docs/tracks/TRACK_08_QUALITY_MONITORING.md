**Duration:** 5 weeks
**Status:** 🔲 To Build
**Dependencies:** Track 03, Track 04

---

## Overview

Tenant-scoped quality monitoring and evaluation with workplans, objectives, activities, KPIs, progress updates, reviews, dashboards, and alerts.

---

## Goals

- Workplan lifecycle with objectives and activities
- KPI definitions, targets, actuals, and scoring
- Progress updates workflow with evidence attachments
- Performance reviews and approvals
- Dashboards and reporting (status, trends, exceptions)
- Alerts/notifications for overdue/underperforming items

---

## Constraints

- PostgreSQL-friendly types (jsonb; no enum columns)
- Tenant scoped; module middleware enforced
- Activity logging for submissions/reviews/score changes
- Attachments via `Helper::processUploadedFile` (disk/path/mime/size)

---

## Schema (Tenant)

- `qm_workplans`: id, uuid, tenant_id, title, period_start, period_end, status, owner_id, metadata (jsonb), timestamps, soft deletes.
- `qm_objectives`: id, workplan_id, title, description, weight, status, sort_order, timestamps.
- `qm_activities`: id, objective_id, title, description, responsible_id, start_date, due_date, status, weight, sort_order, timestamps.
- `qm_kpis`: id, activity_id, name, unit, target_value, baseline_value, direction (string), calculation (jsonb), timestamps.
- `qm_kpi_updates`: id, kpi_id, value, captured_at, note, captured_by_id, evidence_path/mime/size, timestamps.
- `qm_reviews`: id, workplan_id, reviewer_id, status, comments, scores (jsonb), submitted_at, approved_at, timestamps.
- `qm_alerts`: id, workplan_id, kpi_id, type (overdue/underperform), status, metadata (jsonb), sent_at, timestamps.

Indexes: workplans (tenant_id, status, period_start/period_end), objectives/activities (workplan_id/status), kpis (activity_id), updates (kpi_id, captured_at), alerts (status, type).

---

## API Surface (v1)

- `GET /api/quality/v1/workplans` (filters: status, period, owner)
- `POST/PUT/DELETE /workplans/{uuid}`
- `POST /workplans/{uuid}/objectives`, `/objectives/{id}/activities`
- `POST /activities/{id}/kpis`, `POST /kpis/{id}/updates`
- `POST /workplans/{uuid}/reviews`
- `GET /workplans/{uuid}/dashboard` (status breakdown, KPI trends, alerts)
- `GET /alerts` and `POST /alerts/{id}/ack`

---

## Permissions

- `qm.workplans.view`, `qm.workplans.manage`
- `qm.kpis.manage`, `qm.reviews.manage`, `qm.alerts.manage`

---

## Tests

- Workplan/objective/activity CRUD and status transitions
- KPI target vs. actual calculations; direction handling
- Progress updates with attachments and audit log
- Alerts generation for overdue/underperforming KPIs
- Dashboard aggregates accuracy

---

## Done Definition

1) Tenant migrations run cleanly
2) Workplans, KPIs, updates, reviews, alerts functional
3) Dashboards/reporting endpoints return correct aggregates
4) Activity logging present for key changes
5) Feature tests pass
