# Track 06: Incident Management Module

**Duration:** 5 weeks
**Status:** ✅ Complete
**Dependencies:** Track 03, Track 02
**Team Size:** 3-4 developers

---

## Overview

Build a tenant-scoped Incident Management module to capture, triage, assign, escalate, track, and report incidents. This module integrates with RBAC (Spatie teams), tenant isolation, and the module system. It provides internal workflows and a public submission entry point.

---

## Goals

- Incident CRUD with classification and lifecycle states
- Assignment, delegation, CC, and multi-assignee support
- Escalation workflows (manual + automatic)
- Progress reporting, tasks, and SLA tracking
- Deadline extensions and reminders
- Public submission form (tenant-aware)
- API resources, permissions, and tests

---

## Constraints

- Use PostgreSQL-friendly types (jsonb; no enums)
- All incident data is tenant-scoped
- Use Spatie Permission teams for authorization
- Use module enablement middleware on all routes

---

## Domain Model

### Core Entities
- **Incident**: central record
- **IncidentCategory**: classification (type/department)
- **IncidentPriority**: priority definition (SLA thresholds)
- **IncidentStatus**: workflow status catalog (open, triaged, in-progress, resolved, closed)
- **IncidentAssignment**: assignment history and current owner
- **IncidentTask**: sub-tasks for resolution
- **IncidentComment**: internal notes and activity log
- **IncidentAttachment**: files linked to incidents/comments
- **IncidentEscalation**: escalation events
- **IncidentReminder**: scheduled notifications
- **IncidentSla**: SLA clocks per incident
- **IncidentReporter**: external submissions

### Relationships
- Incident belongsTo Category, Priority, Status, Reporter
- Incident hasMany Assignments, Tasks, Comments, Attachments, Escalations, Reminders
- Incident hasOne active SLA record
- IncidentAssignment belongsTo Incident and User (assignee/delegator)
- IncidentTask belongsTo Incident and optionally User

---

## Schema (Tenant Tables)

### `incident_categories`
- id, uuid, tenant_id
- name, slug (unique per tenant)
- description, is_active
- timestamps

### `incident_priorities`
- id, uuid, tenant_id
- name, slug (unique per tenant)
- level (int), response_time_minutes, resolution_time_minutes
- is_active
- timestamps

### `incident_statuses`
- id, uuid, tenant_id
- name, slug (unique per tenant)
- sort_order, is_terminal, is_default
- timestamps

### `incidents`
- id, uuid, tenant_id
- title, description
- category_id, priority_id, status_id
- reported_by_id (user id or reporter id), reported_via (internal|public)
- assigned_to_id (nullable)
- due_at, resolved_at, closed_at
- source (email|web|api), reference_code
- metadata (jsonb), impact (text)
- soft deletes, timestamps

Indexes:
- (tenant_id, status_id, priority_id)
- (tenant_id, assigned_to_id)
- (tenant_id, due_at)

### `incident_assignments`
- id, incident_id
- assigned_to_id, assigned_by_id
- delegated_from_id (nullable)
- assigned_at, unassigned_at, is_active
- note (nullable)

### `incident_tasks`
- id, incident_id, assigned_to_id (nullable)
- title, description, status, due_at, completed_at
- sort_order
- timestamps

### `incident_comments`
- id, incident_id, user_id
- body (text)
- is_internal (bool)
- timestamps

### `incident_attachments`
- id, incident_id, comment_id (nullable)
- disk, path, filename, mime_type, size_bytes
- uploaded_by_id
- timestamps

### `incident_escalations`
- id, incident_id
- from_priority_id, to_priority_id
- escalated_by_id (nullable)
- reason
- escalated_at

### `incident_reminders`
- id, incident_id
- reminder_type (string), scheduled_for, sent_at
- channel (email|sms|slack)
- metadata (jsonb)

### `incident_slas`
- id, incident_id
- response_due_at, resolution_due_at
- first_response_at, resolution_at
- is_breached, breached_at
- timestamps

### `incident_reporters`
- id, uuid, tenant_id
- name, email, phone
- organization (nullable)
- timestamps

---

## API Surface (v1)

### Core
- `GET /api/incidents/v1/incidents`
- `POST /api/incidents/v1/incidents`
- `GET /api/incidents/v1/incidents/{id}`
- `PUT /api/incidents/v1/incidents/{id}`
- `DELETE /api/incidents/v1/incidents/{id}`

### Classification
- `GET/POST/PUT` categories, priorities, statuses

### Workflow
- `POST /incidents/{id}/assign`
- `POST /incidents/{id}/delegate`
- `POST /incidents/{id}/escalate`
- `POST /incidents/{id}/resolve`
- `POST /incidents/{id}/close`

### Tasks & Comments
- `POST /incidents/{id}/tasks`
- `PUT /incidents/{id}/tasks/{taskId}`
- `POST /incidents/{id}/comments`

### Public Intake
- `POST /api/incidents/v1/public/submit`

---

## Permissions

- `incidents.view`, `incidents.create`, `incidents.update`, `incidents.delete`
- `incidents.assign`, `incidents.delegate`, `incidents.escalate`
- `incidents.tasks.manage`, `incidents.comments.manage`
- `incidents.priorities.manage`, `incidents.statuses.manage`, `incidents.categories.manage`
- `incidents.public.submit`

---

## Events & Notifications

- IncidentCreated, IncidentAssigned, IncidentEscalated
- SLA warnings (near breach), SLA breached
- Reminder dispatch (scheduled)

---

## Tests

- Feature: incident CRUD, assignment, escalation
- SLA tracking behavior
- Public submission flow
- RBAC authorization
- Tenant isolation

---

## Done Definition

Track 06 is complete when:
1) All tenant tables migrate cleanly
2) API routes and controllers cover workflows
3) SLA/assignment/escalation logic is tested
4) Public intake is functional and isolated per tenant
5) PHPStan + Pest pass for module scope
