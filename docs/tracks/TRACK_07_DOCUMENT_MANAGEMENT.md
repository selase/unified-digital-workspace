**Duration:** 4 weeks
**Status:** 🔲 To Build
**Dependencies:** Track 03

---

## Overview

Tenant-scoped document management with versioning, access control, quizzes/analytics, previews, and audit logging.

---

## Goals

- Document CRUD with version history and download/preview
- Polymorphic visibility (per-tenant users/teams/departments/directorates/tenant-wide)
- Personal/private documents (visible only to uploader) and shared views (“shared with me”, “shared by me”)
- Document quizzes with attempts and analytics
- Audit trail for views/downloads/updates
- Search/filter by tags/category/status/date/owner with visibility enforcement

---

## Constraints

- PostgreSQL-friendly types (jsonb; no enum columns)
- Tenant scoped; module middleware enforced
- Use `Helper::processUploadedFile` for uploads; store disk/path/mime/size
- Slugs lowercased and unique per tenant
- Activity logging for create/update/version/publish/download
- Supported file types include pdf, docx, video, and audio (validated via mime)

---

- `documents`: id, uuid, tenant_id, title, slug, description, status, visibility (jsonb), current_version_id, owner_id, category, tags (jsonb), metadata (jsonb), published_at, soft deletes, timestamps. Unique (tenant_id, slug). Visibility supports user/team/department/directorate/tenant-wide and personal (owner-only).
- `document_versions`: id, document_id, version_number, checksum_sha256, disk, path, mime_type, size_bytes, uploaded_by_id, notes, created_at. Unique (document_id, version_number).
- `document_quizzes`: id, document_id, title, description, settings (jsonb), timestamps.
- `document_quiz_questions`: id, quiz_id, body, options (jsonb), correct_option, points, sort_order.
- `document_quiz_attempts`: id, quiz_id, user_id, score, responses (jsonb), started_at, completed_at, timestamps.
- `document_audits`: id, document_id, user_id, event (view|download|update|quiz_attempt), metadata (jsonb), created_at.

Indexes: documents (tenant_id, status, published_at), versions (document_id, version_number), audits (document_id, event, created_at), attempts (quiz_id, user_id).

---

- `GET /api/document-management/v1/documents` (filters: status, category, tag, owner, shared_with_me, shared_by_me, published_from/to)
- `POST /documents`, `GET/PUT/DELETE /documents/{uuid}`
- `POST /documents/{uuid}/versions` (upload new version)
- `GET /documents/{uuid}/versions`
- `POST /documents/{uuid}/publish`
- `POST /documents/{uuid}/quizzes`, `GET /documents/{uuid}/quizzes/{id}`
- `POST /quizzes/{id}/attempts`
- `GET /documents/{uuid}/audits`
- `GET /documents/{uuid}/download/{version?}`

---

## Permissions

- `documents.view`, `documents.create`, `documents.update`, `documents.delete`
- `documents.publish`, `documents.manage_quizzes`, `documents.manage_versions`
- `documents.audit.view`

---

## Tests

- Document CRUD + slug uniqueness per tenant
- Version upload/selection and checksum recorded
- Visibility enforcement (role/user/team) and module middleware
- Quiz create/attempt scoring
- Audit records for view/download

---

## Done Definition

1) Tenant migrations run cleanly
2) Versioning, visibility, and quizzes functional
3) Audit trail recorded for key events
4) Filters and downloads work
5) Feature tests pass
