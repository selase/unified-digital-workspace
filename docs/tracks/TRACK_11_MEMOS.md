**Duration:** 3 weeks
**Status:** 🔲 To Build
**Dependencies:** Track 03, Track 07

---

## Overview

Tenant-scoped memo drafting, routing, approvals, e-signatures, recipient tracking, versioning, and audit trail.

---

## Goals

- Memo drafting with rich content and attachments
- Routing/approval workflows with statuses
- E-signature capture (stub/integration-ready)
- Recipient tracking (to/cc/bcc) with read receipts
- Version history and audit log

---

## Constraints

- PostgreSQL types (jsonb; no enum columns)
- Tenant scoped; module middleware enforced
- Attachments via `Helper::processUploadedFile`; store disk/path/mime/size
- Activity logging for draft/submit/approve/reject/sign/read

---

## Schema (Tenant)

- `memos`: id, uuid, tenant_id, title, body, status, author_id, current_version_id, metadata (jsonb), submitted_at, approved_at, rejected_at, timestamps.
- `memo_versions`: id, memo_id, version_number, body, attachments (jsonb), created_by_id, created_at. Unique (memo_id, version_number).
- `memo_recipients`: id, memo_id, user_id, type (to/cc/bcc), read_at, acknowledged_at, timestamps. Unique (memo_id, user_id, type).
- `memo_signatures`: id, memo_id, user_id, signed_at, signature_data (jsonb), timestamps.
- `memo_routes`: id, memo_id, step_order, approver_id, status, acted_at, comment, timestamps.
- `memo_audits`: id, memo_id, user_id, event, metadata (jsonb), created_at.

Indexes: memos (tenant_id, status, submitted_at), recipients (memo_id, user_id), signatures (memo_id, user_id), routes (memo_id, step_order), audits (memo_id, event, created_at).

---

## API Surface (v1)

- `GET /api/memos/v1/memos` (filters: status, author, date range)
- `POST /memos`, `GET/PUT/DELETE /memos/{uuid}`
- `POST /memos/{uuid}/submit`, `/approve`, `/reject`
- `POST /memos/{uuid}/routes` (define/modify route)
- `POST /memos/{uuid}/sign`
- `POST /memos/{uuid}/recipients`
- `GET /memos/{uuid}/audit`

---

## Permissions

- `memos.view`, `memos.create`, `memos.update`, `memos.delete`
- `memos.route.manage`, `memos.sign`, `memos.audit.view`

---

## Tests

- Memo CRUD and slug/uuid uniqueness per tenant
- Routing workflow (submit → approve/reject), status transitions
- Recipient tracking and read receipts
- Signature creation (stub) and audit log
- Permissions enforcement on route/approve/sign

---

## Done Definition

1) Tenant migrations run cleanly
2) Routing, approvals, signatures, recipients functional
3) Audit logs recorded for key actions
4) Permissions enforced across flows
5) Feature tests pass
