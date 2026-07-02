# Data Model: GrantGenie Core

**Date**: 2026-07-03
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Research**: [research.md](./research.md)

This document defines the entities, attributes, relationships, validation rules, and state transitions derived from the spec and research. All entities carry `tenant_id` for multi-tenant isolation (constitution Principle IV); RLS policies are defined in the migration files referenced from each entity.

---

## Bounded Contexts

- **Identity & Tenancy**: `Account` (tenant), `User`, `Role`, `UserRole`
- **Grant Discovery**: `Grant`, `EligibilityRule`, `EligibilityDecision`, `IngestionRun`, `IngestionSource`
- **Org Profile & Boilerplate**: `OrgProfile`, `BoilerplateDocument`, `DocumentChunk`
- **Proposal Drafting**: `Proposal`, `ProposalSection`, `ProposalSectionVersion`, `Citation`, `ReviewComment`, `EditLock`, `BudgetItem`
- **Tracking & Notifications**: `Submission`, `Notification`, `DeadlineReminder`
- **Cross-cutting**: `AuditLog`, `OutboxMessage`, `IdempotencyKey`

---

## Identity & Tenancy

### Account (Tenant)

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `name` | string(255) | not null, unique within registry | Nonprofit organization name |
| `slug` | string(64) | not null, unique | URL-safe identifier |
| `plan` | enum | `free`, `team`, `pro` | Billing tier (P2; flat for MVP) |
| `created_at` | timestamptz | not null | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), unique(slug).

### User

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | Tenant binding |
| `email` | string(320) | not null, unique globally | |
| `display_name` | string(255) | not null | |
| `oidc_subject` | string(255) | not null, unique per provider | OIDC `sub` claim |
| `status` | enum | `active`, `invited`, `disabled` | |
| `last_login_at` | timestamptz | nullable | |
| `created_at` | timestamptz | not null | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), unique(email), unique(oidc_subject, account_id), index(account_id).

### Role

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | smallint | PK | 1=admin, 2=writer, 3=reviewer, 4=viewer |
| `name` | string(32) | unique | admin, writer, reviewer, viewer |

Static table; seeded by migration.

### UserRole (join)

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `user_id` | uuid | FK → users.id | |
| `role_id` | smallint | FK → roles.id | |
| `account_id` | uuid | FK → accounts.id | RLS scope |
| `granted_at` | timestamptz | not null | |
| `granted_by` | uuid | FK → users.id | |

PK(user_id, role_id, account_id). Index(account_id).

---

## Grant Discovery

### IngestionSource

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `name` | string(64) | not null, unique | grants_gov, candid, instrumentl |
| `base_url` | string(2048) | not null | |
| `auth_config` | jsonb | nullable | OAuth2 client_id/secret, etc. |
| `enabled` | bool | not null, default true | |
| `last_run_at` | timestamptz | nullable | |
| `last_run_status` | enum | `success`, `partial`, `failed`, nullable | |

### IngestionRun

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `source_id` | uuid | FK → ingestion_sources.id | |
| `started_at` | timestamptz | not null | |
| `finished_at` | timestamptz | nullable | |
| `status` | enum | `running`, `success`, `partial`, `failed` | |
| `grants_fetched` | int | default 0 | |
| `grants_upserted` | int | default 0 | |
| `error_summary` | text | nullable | |

Indexes: index(source_id, started_at DESC).

### Grant

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `source_id` | uuid | FK → ingestion_sources.id, not null | |
| `source_grant_id` | string(128) | not null | External id |
| `title` | string(512) | not null | |
| `funder_name` | string(255) | not null | |
| `funder_url` | string(2048) | nullable | |
| `description` | text | not null | Plain text or sanitized HTML |
| `amount_min` | numeric(14,2) | nullable | USD |
| `amount_max` | numeric(14,2) | nullable | USD |
| `deadline_at` | timestamptz | nullable | |
| `posted_at` | timestamptz | nullable | |
| `categories` | string[] | not null, default `{}` | e.g. `["health", "education"]` |
| `ntee_codes` | string[] | not null, default `{}` | National Taxonomy of Exempt Entities |
| `service_area_states` | string(2)[] | not null, default `{}` | US state codes |
| `organization_types` | string[] | not null, default `{}` | `["501c3", "fiscally-sponsored"]` |
| `status` | enum | `open`, `closed`, `forecasted` | |
| `eligibility_rules` | jsonb | not null, default `[]` | List of `EligibilityRule` objects (see below) |
| `ingested_at` | timestamptz | not null | |
| `last_seen_at` | timestamptz | not null | |

Indexes: PK(id), unique(source_id, source_grant_id), GIN(categories), GIN(ntee_codes), index(deadline_at), index(status, deadline_at).

**EligibilityRule (jsonb shape)**:
```json
{
  "id": "uuid",
  "field": "ntee_codes | service_area_states | organization_types | annual_budget_range | years_operating",
  "operator": "in | not_in | between | gte | lte | eq",
  "value": "any JSON",
  "description": "human-readable rule text for audit"
}
```

### EligibilityDecision

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `grant_id` | uuid | FK → grants.id, not null | |
| `result` | enum | `eligible`, `not_eligible` | |
| `matched_rule_ids` | uuid[] | not null | Which rules fired |
| `evaluated_at` | timestamptz | not null | |
| `org_profile_hash` | string(64) | not null | For re-evaluation detection |

Indexes: PK(id), index(account_id, grant_id), index(grant_id, evaluated_at DESC).

Cache TTL: 6 hours per (account_id, grant_id). Re-evaluated when org_profile_hash changes.

---

## Org Profile & Boilerplate

### OrgProfile

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS, unique | One profile per tenant |
| `mission` | text | not null | |
| `history` | text | nullable | |
| `programs` | jsonb | not null, default `[]` | `[{name, description, served_annually}]` |
| `service_area_states` | string(2)[] | not null, default `{}` | |
| `ntee_codes` | string[] | not null, default `{}` | |
| `organization_type` | string(64) | not null | `501c3`, `fiscally-sponsored`, etc. |
| `annual_budget_cents` | bigint | nullable | |
| `years_operating` | smallint | nullable | |
| `contact_email` | string(320) | not null | |
| `website_url` | string(2048) | nullable | |
| `completed` | bool | not null, default false | Drives SC-007 measurement |
| `created_at` | timestamptz | not null | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), unique(account_id).

### BoilerplateDocument

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `title` | string(255) | not null | |
| `original_filename` | string(512) | not null | |
| `format` | enum | `pdf`, `docx`, `txt`, `md` | |
| `size_bytes` | bigint | not null, ≤ 10485760 (10 MB) | |
| `storage_key` | string(1024) | not null | S3/MinIO object key |
| `uploaded_by` | uuid | FK → users.id, not null | |
| `status` | enum | `uploaded`, `processing`, `ready`, `failed` | |
| `chunk_count` | int | default 0 | |
| `processing_error` | text | nullable | |
| `uploaded_at` | timestamptz | not null | |
| `processed_at` | timestamptz | nullable | |

Indexes: PK(id), index(account_id, uploaded_at DESC), index(status).

### DocumentChunk

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `document_id` | uuid | FK → boilerplate_documents.id, not null | |
| `chunk_index` | int | not null | 0-based within document |
| `content` | text | not null | ≤ 800 tokens |
| `content_hash` | string(64) | not null | SHA-256 |
| `page_number` | int | nullable | For PDFs only |
| `char_offset_start` | int | not null | |
| `char_offset_end` | int | not null | |
| `embedding` | vector(1536) | not null | pgvector, OpenAI text-embedding-3-small |
| `embedding_model` | string(64) | not null | Track model for re-embed migrations |
| `created_at` | timestamptz | not null | |

Indexes: PK(id), HNSW index on `embedding` (m=16, ef_construction=64), index(document_id, chunk_index), index(account_id).

---

## Proposal Drafting

### Proposal

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `grant_id` | uuid | FK → grants.id, not null | |
| `title` | string(512) | not null | |
| `status` | enum | `drafting`, `ready_for_review`, `in_review`, `approved`, `submitted`, `archived` | |
| `funder_profile` | jsonb | nullable | Page limits, section order, formatting |
| `ai_model_used` | string(64) | nullable | e.g. `claude-sonnet-4-5` |
| `ai_total_input_tokens` | int | default 0 | |
| `ai_total_output_tokens` | int | default 0 | |
| `ai_total_cost_cents` | int | default 0 | |
| `eval_relevance` | numeric(4,3) | nullable | 0.000-1.000 |
| `eval_faithfulness` | numeric(4,3) | nullable | 0.000-1.000 |
| `eval_passed` | bool | nullable | NULL until evaluated |
| `created_by` | uuid | FK → users.id, not null | |
| `created_at` | timestamptz | not null | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), index(account_id, status), index(grant_id), index(eval_passed).

**State Transitions**:
```
drafting → ready_for_review → in_review → approved → submitted → archived
                  ↑                                    ↓
                  └──────── (author edits) ────────────┘
```
- `drafting` → `ready_for_review`: author sets status explicitly
- `ready_for_review` → `in_review`: any reviewer accesses
- `in_review` → `approved`: author approves after reviewing comments
- `approved` → `submitted`: linked Submission created
- Any non-terminal → `drafting`: author reopens

### ProposalSection

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `proposal_id` | uuid | FK → proposals.id, not null | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `kind` | enum | `summary`, `need_statement`, `activities`, `budget_narrative`, `impact` | |
| `order_index` | smallint | not null | 0..N |
| `content` | text | not null | Current content (latest version) |
| `current_version_id` | uuid | FK → proposal_section_versions.id, nullable | |
| `updated_by` | uuid | FK → users.id | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), unique(proposal_id, kind), index(account_id).

### ProposalSectionVersion (append-only)

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `section_id` | uuid | FK → proposal_sections.id, not null | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `content` | text | not null | |
| `content_hash` | string(64) | not null | |
| `author_id` | uuid | FK → users.id, not null | |
| `edit_lock_id` | uuid | FK → edit_locks.id, nullable | NULL for AI-generated versions |
| `created_at` | timestamptz | not null | |

Indexes: PK(id), index(section_id, created_at DESC).

### Citation

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `section_version_id` | uuid | FK → proposal_section_versions.id, not null | |
| `chunk_id` | uuid | FK → document_chunks.id, not null | |
| `quote_span_start` | int | not null | Char offset in section content |
| `quote_span_end` | int | not null | |
| `confidence` | numeric(4,3) | not null | 0.000-1.000 |

Indexes: PK(id), index(section_version_id), index(chunk_id).

### ReviewComment

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `section_id` | uuid | FK → proposal_sections.id, not null | |
| `author_id` | uuid | FK → users.id, not null | |
| `body` | text | not null | ≤ 4000 chars |
| `anchor_quote` | text | nullable | Pinned to a specific text span |
| `anchor_quote_start` | int | nullable | Char offset |
| `anchor_quote_end` | int | nullable | |
| `status` | enum | `open`, `resolved` | |
| `resolved_by` | uuid | FK → users.id, nullable | |
| `resolved_at` | timestamptz | nullable | |
| `created_at` | timestamptz | not null | |

Indexes: PK(id), index(account_id, section_id), index(status).

### EditLock

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `proposal_id` | uuid | FK → proposals.id, not null, unique when active | |
| `holder_user_id` | uuid | FK → users.id, not null | |
| `acquired_at` | timestamptz | not null | |
| `expires_at` | timestamptz | not null | acquired_at + 30 min, refreshed on heartbeat |
| `released_at` | timestamptz | nullable | |

Unique partial index: unique(proposal_id) WHERE released_at IS NULL.

Mirrored in Redis as `proposal-lock:{proposal_id}` for fast check; Postgres is source of truth for audit.

### BudgetItem

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `proposal_id` | uuid | FK → proposals.id, not null | |
| `category` | string(128) | not null | `Personnel`, `Supplies`, `Travel`, etc. |
| `description` | string(512) | not null | |
| `amount_cents` | bigint | not null, ≥ 0 | |
| `narrative` | text | nullable | AI-generated or author-written |
| `funder_category` | string(128) | nullable | Mapped to funder's budget taxonomy |
| `created_at` | timestamptz | not null | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), index(account_id, proposal_id).

---

## Tracking & Notifications

### Submission

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `proposal_id` | uuid | FK → proposals.id, not null, unique | One submission per proposal |
| `funder_name` | string(255) | not null | |
| `submitted_at` | timestamptz | not null | |
| `funder_confirmation_id` | string(128) | nullable | |
| `status` | enum | `submitted`, `under_review`, `awarded`, `declined`, `withdrawn` | |
| `awarded_amount_cents` | bigint | nullable | |
| `outcome_notes` | text | nullable | |
| `updated_at` | timestamptz | not null | |

Indexes: PK(id), unique(proposal_id), index(account_id, status).

### Notification

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `user_id` | uuid | FK → users.id, not null | |
| `kind` | enum | `deadline_reminder`, `review_request`, `comment_reply`, `system` | |
| `title` | string(255) | not null | |
| `body` | text | not null | |
| `link_url` | string(2048) | nullable | |
| `read_at` | timestamptz | nullable | |
| `created_at` | timestamptz | not null | |

Indexes: PK(id), index(user_id, read_at), index(account_id, created_at DESC).

### DeadlineReminder (idempotency record)

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `grant_id` | uuid | FK → grants.id, not null | |
| `user_id` | uuid | FK → users.id, not null | |
| `days_before` | smallint | not null, in {1, 7, 14} | |
| `sent_at` | timestamptz | not null | |
| `notification_id` | uuid | FK → notifications.id | |

Unique(account_id, grant_id, user_id, days_before) — prevents duplicate reminders.

---

## Cross-cutting

### AuditLog (append-only)

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `actor_user_id` | uuid | FK → users.id, nullable | NULL for system actions |
| `action` | string(64) | not null | `proposal.update`, `grant.eligibility.evaluate`, etc. |
| `resource_type` | string(64) | not null | `proposal`, `grant`, `user` |
| `resource_id` | uuid | not null | |
| `before` | jsonb | nullable | |
| `after` | jsonb | nullable | |
| `correlation_id` | string(64) | not null | |
| `created_at` | timestamptz | not null, default now() | |

Indexes: PK(id), index(account_id, created_at DESC), index(resource_type, resource_id), index(correlation_id).

No UPDATE/DELETE allowed at the DB level (trigger blocks).

### OutboxMessage

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `aggregate_type` | string(64) | not null | |
| `aggregate_id` | uuid | not null | |
| `event_type` | string(64) | not null | |
| `payload` | jsonb | not null | |
| `status` | enum | `pending`, `published`, `failed` | |
| `attempts` | int | default 0 | |
| `next_attempt_at` | timestamptz | not null | |
| `published_at` | timestamptz | nullable | |
| `created_at` | timestamptz | not null | |

Indexes: PK(id), index(status, next_attempt_at), index(aggregate_type, aggregate_id).

### IdempotencyKey

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `account_id` | uuid | FK → accounts.id, not null, RLS | |
| `key` | string(128) | not null | Client-supplied UUID |
| `endpoint` | string(255) | not null | Method + path |
| `request_hash` | string(64) | not null | SHA-256 of request body |
| `response_status` | int | nullable | NULL until executed |
| `response_body` | jsonb | nullable | Cached response |
| `created_at` | timestamptz | not null | |
| `expires_at` | timestamptz | not null | created_at + 24h |

Indexes: PK(id), unique(account_id, key), index(expires_at) for TTL purge.

---

## Validation rules (cross-entity)

- **Cross-tenant writes**: every write must include `account_id` matching the JWT claim; middleware rejects mismatch with `403`.
- **Edit-lock enforcement**: `PATCH /proposal_sections/{id}` requires `EditLock` row where `released_at IS NULL` and `holder_user_id = current_user_id`; otherwise `423 Locked`.
- **Eligibility re-evaluation**: any update to `org_profiles` schedules re-evaluation of cached `EligibilityDecision` rows whose `org_profile_hash` differs.
- **Eval-gate requirement**: `Proposal.eval_passed` must be `true` before status transition from `drafting` to `ready_for_review`; otherwise `422 Unprocessable Entity`.
- **Citation integrity**: every `Citation` must reference a `DocumentChunk` belonging to the same `account_id`; orphan citations blocked by FK.
- **Reminder idempotency**: `DeadlineReminder` unique index prevents duplicate sends for the same (grant, user, days_before).
- **Audit log immutability**: Postgres trigger on `audit_logs` raises exception on UPDATE or DELETE.

---

## Migrations

All migrations are versioned under `backend/database/migrations/` and run in order on `composer install` (or `php artisan migrate`). Naming follows Laravel convention: `YYYY_MM_DD_HHMMSS_create_<table>_table.php`.

`pgvector` extension creation: `2026_07_03_000001_create_pgvector_extension.php` (must run first).

RLS policies are created in the same migration that creates the table, using a `CREATE POLICY` statement that keys on `current_setting('app.current_tenant_id')`.
