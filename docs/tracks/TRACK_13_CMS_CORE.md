# Track 13: CMS Core Schema

**Duration:** 3 weeks
**Status:** ✅ Complete
**Dependencies:** Track 03
**Team Size:** 2-3 developers

---

## Overview

Build the tenant-scoped CMS core for UDW with post types, posts, taxonomies, media metadata, revisions, menus, and settings. This track focuses on schema, models, API layer, and tests. It does not rebuild Spatie Permission or auth.

---

## Goals

- Tenant-scoped CMS schema (PostgreSQL)
- Post types, posts, categories, tags
- Media metadata and variants
- Revisions and meta fields
- Menus and settings
- Eloquent relationships and query scopes
- API resources and tests

---

## Constraints

- Use PostgreSQL-friendly data types (jsonb, no enum columns)
- CMS tables are tenant-scoped by default
- Spatie Permission already exists; do not recreate auth
- Slugs stored as lowercase; enforce unique constraints

---

## Tasks

### Task 13.1: Tenant Context Verification
**Estimated Time:** 4 hours
**Priority:** Critical

Confirm tenant migration execution and the user model used for author/editor/uploaded_by. Document the expected foreign key targets.

**Acceptance Criteria:**
- Migrations run in tenant context
- Author/editor/uploaded_by foreign keys are defined consistently

---

### Task 13.2: Core Tables
**Estimated Time:** 10 hours
**Priority:** Critical

Create `post_types` and `posts` with required constraints and indexes.

**Acceptance Criteria:**
- `post_types.slug` unique
- `posts` unique (`post_type_id`, `slug`)
- Indexes for `(post_type_id, status, published_at)`

---

### Task 13.3: Taxonomies
**Estimated Time:** 8 hours
**Priority:** High

Create `categories`, `tags`, and pivots `category_post`, `post_tag`.

**Acceptance Criteria:**
- Unique slugs in categories/tags
- Unique pivot pairs to prevent duplicates

---

### Task 13.4: Media Metadata
**Estimated Time:** 12 hours
**Priority:** High

Create `media`, optional `media_variants`, and optional `media_post` pivot.

**Acceptance Criteria:**
- Metadata supports size, mime, checksum, and accessibility fields
- Variant uniqueness (`media_id`, `variant`)

---

### Task 13.5: CMS Essentials
**Estimated Time:** 12 hours
**Priority:** High

Add `post_revisions`, `post_meta`, `menus`, `menu_items`, `settings`.

**Acceptance Criteria:**
- Revisions capture author and content snapshot
- `post_meta` unique per (`post_id`, `key`)
- Menus support nesting via `parent_id`

---

### Task 13.6: Eloquent Contract
**Estimated Time:** 10 hours
**Priority:** High

Implement model relationships and query scopes:
- `published()`, `scheduled()`, `forType($slug)`

**Acceptance Criteria:**
- Relationships match schema
- Scopes produce correct listings

---

### Task 13.7: API Layer + Tests
**Estimated Time:** 12 hours
**Priority:** High

Create API resources and integration tests for post types, posts, and taxonomies.

**Acceptance Criteria:**
- API resources map to schema correctly
- Tests cover CRUD baselines and relationships

---

## PostgreSQL Indexing Checklist

- Unique (`post_type_id`, `slug`) on posts
- Index (`post_type_id`, `status`, `published_at`)
- Index `mime_type`, `size_bytes`, `checksum_sha256` on media
- Use jsonb for metadata and meta values

---

## Done Definition

This track is complete when:
1) CMS migrations run cleanly in tenant context
2) Constraints and indexes are in place
3) Eloquent relationships and scopes are implemented
4) Tenant can create post types, posts, categories, tags, media, meta, revisions, menus, settings
5) Tests pass for CMS core flows

---

## Next Track

[Track 05: Scheduling Module](TRACK_05_SCHEDULING.md)
