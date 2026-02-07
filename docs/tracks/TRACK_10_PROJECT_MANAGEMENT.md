# Track 10: Project Management Module

**Duration:** 4 weeks
**Status:** ✅ Complete
**Dependencies:** Track 03
**Team Size:** 3-4 developers

---

## Overview

Build a tenant-scoped Project Management module to manage projects, milestones, tasks, and resourcing. Provide structured workflows, time tracking, and reporting while integrating with module enablement and RBAC.

---

## Goals

- Project and milestone management
- Task workflows with dependencies
- Kanban and list views
- Timesheets and time logs
- Gantt chart data model
- Resource allocation and utilization
- Reporting and analytics
- Activity logging for task actions (comments, attachments, assignments)
    - Ensure dependency cycle prevention; normalize Gantt JSON (tasks with dates, dependencies, progress; no SVG)

---

## Constraints

- Use PostgreSQL-friendly types (jsonb; no enum columns)
- All project data is tenant-scoped
- Use Spatie Permission teams for authorization
- Use module enablement middleware on all routes
- Follow module manifest/enablement patterns (`module.php`, pricing/tiers) used in other modules
- Define status/priority as strings (no DB enums) for projects and tasks; document accepted values
- Lists should support pagination + filters (status, priority, owner, member, date ranges)
- Attachments must use `Helper::processUploadedFile`; store disk/path/mime/size and enforce ownership/tenant checks
- Time entries: require `entry_date`, non-negative minutes; consider per-user daily limits; validate task ownership
- Resource allocations: prevent overlapping ranges per (project, user); role optional
- Comments/attachments/assignments should emit activity logs for audit/export

---

## Domain Model

### Core Entities

- **Project**: top-level work container
- **ProjectMember**: membership and role
- **Milestone**: project milestones
- **Task**: project tasks
- **TaskDependency**: task dependency graph
- **TaskAssignment**: task assignees
- **TaskComment**: discussion on tasks
- **TaskAttachment**: files linked to tasks
- **TimeEntry**: timesheet lines
- **ResourceAllocation**: planned resource usage

### Relationships

- Project hasMany Milestones, Tasks, Members
- Task belongsTo Project and optionally Milestone
- Task hasMany Assignments, Comments, Attachments
- Task hasMany Dependencies (blocking/blocked)
- TimeEntry belongsTo Task and User
- ResourceAllocation belongsTo Project and User

---

## Schema (Tenant Tables)

### `projects`

- id, uuid, tenant_id
- name, slug (unique per tenant)
- description, status, priority
- start_date, end_date, completed_at
- budget_amount (nullable), currency (nullable)
- owner_id (user)
- metadata (jsonb)
- soft deletes, timestamps

Indexes:

- (tenant_id, status)
- (tenant_id, owner_id)

### `project_members`

- id, project_id
- user_id
- role (string)
- joined_at
- timestamps

### `milestones`

- id, project_id
- name, description
- due_date, completed_at
- sort_order
- timestamps

### `tasks`

- id, project_id, milestone_id (nullable)
- title, description
- status, priority
- start_date, due_date, completed_at
- estimated_minutes (nullable)
- sort_order
- parent_id (nullable, for sub-tasks)
- timestamps

Indexes:

- (project_id, status)
- (project_id, milestone_id)
- (project_id, due_date)

### `task_dependencies`

- id
- task_id (blocked)
- depends_on_task_id (blocker)
- timestamps

Constraints:

- unique(task_id, depends_on_task_id)

### `task_assignments`

- id, task_id
- user_id
- assigned_by_id
- assigned_at
- timestamps

### `task_comments`

- id, task_id, user_id
- body (text)
- timestamps

### `task_attachments`

- id, task_id
- disk, path, filename, mime_type, size_bytes
- uploaded_by_id
- timestamps

### `time_entries`

- id, task_id, user_id
- entry_date
- minutes
- note (nullable)
- timestamps

### `resource_allocations`

- id, project_id, user_id
- start_date, end_date
- allocation_percent
- role (nullable)
- timestamps

---

## API Surface (v1)

### Projects

- `GET /api/projects/v1/projects`
- `POST /api/projects/v1/projects`
- `GET /api/projects/v1/projects/{id}`
- `PUT /api/projects/v1/projects/{id}`
- `DELETE /api/projects/v1/projects/{id}`

### Milestones

- `POST /projects/{id}/milestones`
- `PUT /projects/{id}/milestones/{milestoneId}`
- `DELETE /projects/{id}/milestones/{milestoneId}`

### Tasks

- `POST /projects/{id}/tasks`
- `PUT /projects/{id}/tasks/{taskId}`
- `DELETE /projects/{id}/tasks/{taskId}`
- `POST /tasks/{taskId}/assign`
- `POST /tasks/{taskId}/comment`
- `POST /tasks/{taskId}/attach`
- `POST /tasks/{taskId}/time-entries`
- `GET /projects/{id}/tasks` with filters (status, priority, assignee/member, date range)

### Dependencies & Gantt

- `POST /tasks/{taskId}/dependencies`
- `DELETE /tasks/{taskId}/dependencies/{dependencyId}`
- `GET /projects/{id}/gantt`
    - Returns normalized JSON for tasks/dependencies/progress

### Resource Allocation

- `POST /projects/{id}/allocations`
- `PUT /projects/{id}/allocations/{allocationId}`
- `DELETE /projects/{id}/allocations/{allocationId}`

---

## Permissions

- `projects.view`, `projects.create`, `projects.update`, `projects.delete`
- `projects.tasks.manage`, `projects.milestones.manage`
- `projects.dependencies.manage`, `projects.time.manage`
- `projects.allocations.manage`
- `projects.members.manage`, `projects.attachments.manage`

---

## Reporting

- Project progress (percent complete)
- Task status breakdown
- Time tracking summaries
- Resource utilization by user/role
- Gantt feed (tasks + dependencies) and critical path flag

---

## Tests

- Feature: project CRUD, milestone CRUD, task CRUD
- Dependencies and blocking logic
- Time entry validation and totals
- Allocation overlap validation
- Tenant isolation and RBAC
- Activity logging assertions (comments/attachments/assignments)

---

## Done Definition

Track 10 is complete when:

1. All tenant tables migrate cleanly
2. CRUD endpoints exist for projects/milestones/tasks
3. Dependencies, time entries, and allocations are functional
4. Reporting endpoints produce correct aggregates
5. PHPStan + Pest pass for module scope
