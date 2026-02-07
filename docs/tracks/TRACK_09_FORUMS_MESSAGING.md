**Duration:** 3 weeks
**Status:** 🔲 To Build
**Dependencies:** Track 03

---

## Overview

Tenant forums and messaging for channels, threads, replies, reactions, moderation, and group messaging/intranet hub.

---

## Goals

- Discussion channels with threads and replies
- Best reply marking and reactions (likes/emojis)
- Moderation tools (pin/lock/delete/flag)
- Group messaging and intranet announcements
- Search/filter by channel/tag/user
- Notifications for mentions/replies

---

## Constraints

- PostgreSQL types (jsonb; no enum columns)
- Tenant scoped; module middleware enforced
- Activity logging for create/reply/react/moderate
- Slugs lowercased and unique per tenant/channel

---

## Schema (Tenant)

- `forum_channels`: id, uuid, tenant_id, name, slug, description, visibility (jsonb), is_locked, sort_order, timestamps.
- `forum_threads`: id, uuid, channel_id, title, slug, user_id, status, pinned_at, locked_at, tags (jsonb), metadata (jsonb), timestamps.
- `forum_posts`: id, thread_id, user_id, parent_id (nullable for replies), body, is_best_answer, edited_at, timestamps.
- `forum_reactions`: id, post_id, user_id, type (string), timestamps. Unique (post_id, user_id, type).
- `forum_moderation_logs`: id, thread_id, post_id, moderator_id, action, reason, metadata (jsonb), created_at.
- `forum_messages`: id, uuid, tenant_id, sender_id, subject, body, visibility (jsonb), metadata (jsonb), timestamps.
- `forum_message_recipients`: id, message_id, user_id, read_at, deleted_at, timestamps. Unique (message_id, user_id).

Indexes: channels (tenant_id, slug), threads (channel_id, status, pinned_at), posts (thread_id, parent_id), reactions (post_id, type), messages (tenant_id, sender_id), message_recipients (message_id, user_id).

---

## API Surface (v1)

- `GET /api/forums/v1/channels` (filters: visibility/tag)
- `POST/PUT/DELETE /channels/{uuid}`
- `POST /channels/{uuid}/threads`, `GET /threads/{uuid}`
- `POST /threads/{uuid}/posts`, `POST /posts/{id}/reply`
- `POST /posts/{id}/react`, `DELETE /posts/{id}/react`
- `POST /posts/{id}/mark-best`
- `POST /threads/{uuid}/moderate` (pin/lock/delete/flag)
- Messaging: `POST /messages`, `GET /messages`, `POST /messages/{uuid}/read`

---

## Permissions

- `forums.view`, `forums.post`, `forums.moderate`
- `forums.messages.send`, `forums.messages.manage`

---

## Tests

- Channel/thread/post CRUD and slug uniqueness
- Replies hierarchy and best-answer marking
- Reactions uniqueness per user/post
- Moderation actions recorded; permissions enforced
- Messaging delivery, read receipts, visibility

---

## Done Definition

1) Tenant migrations run cleanly
2) Threads/replies/reactions and moderation functional
3) Messaging send/read/visibility works
4) Activity/audit logs recorded for moderation
5) Feature tests pass
