# Domain Events

Versioned events published via the transactional outbox (`outbox_messages` table) to a Redis Stream (`grantgenie.events`). Each event has a stable `event_type` string, a `payload_version`, and is JSON-serialized.

## Conventions

- **Event envelope**:
  ```json
  {
    "event_id": "uuid",
    "event_type": "string",
    "event_version": 1,
    "occurred_at": "2026-07-03T12:00:00Z",
    "account_id": "uuid",
    "correlation_id": "string",
    "payload": { ... event-specific ... }
  }
  ```
- **Tenant scope**: every event includes `account_id` so downstream consumers (notifications, analytics, audit) can re-apply RLS-like filtering.
- **Schema evolution**: changes to payload shape bump `event_version`; consumers must ignore unknown fields.

---

## Identity & Tenancy

### `tenant.created` (v1)
```json
{
  "tenant_id": "uuid",
  "name": "string",
  "plan": "free|team|pro"
}
```

### `user.invited` (v1)
```json
{
  "user_id": "uuid",
  "tenant_id": "uuid",
  "email": "string",
  "role": "admin|writer|reviewer|viewer",
  "invited_by": "uuid"
}
```

### `user.activated` (v1)
```json
{
  "user_id": "uuid",
  "tenant_id": "uuid",
  "first_login_at": "2026-07-03T12:00:00Z"
}
```

---

## Org Profile & Boilerplate

### `org_profile.completed` (v1)
```json
{
  "tenant_id": "uuid",
  "completed_at": "2026-07-03T12:00:00Z",
  "documents_count": 5
}
```
Consumed by: FinOps dashboard, re-evaluate all cached eligibility decisions.

### `boilerplate.document.uploaded` (v1)
```json
{
  "tenant_id": "uuid",
  "document_id": "uuid",
  "uploaded_by": "uuid",
  "format": "pdf|docx|txt|md",
  "size_bytes": 245678
}
```

### `boilerplate.document.processed` (v1)
```json
{
  "tenant_id": "uuid",
  "document_id": "uuid",
  "chunk_count": 42,
  "embedding_model": "text-embedding-3-small",
  "duration_ms": 14250
}
```

### `boilerplate.document.failed` (v1)
```json
{
  "tenant_id": "uuid",
  "document_id": "uuid",
  "error": "string"
}
```

---

## Grant Discovery

### `grant.ingestion.completed` (v1)
```json
{
  "ingestion_run_id": "uuid",
  "source_id": "uuid",
  "source_name": "grants_gov",
  "status": "success|partial|failed",
  "grants_fetched": 1280,
  "grants_upserted": 1142,
  "duration_ms": 184000
}
```

### `grant.eligibility.re_evaluated` (v1)
```json
{
  "tenant_id": "uuid",
  "grants_evaluated": 245,
  "eligible_count": 23,
  "duration_ms": 4200
}
```

---

## Proposal Drafting

### `proposal.created` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "grant_id": "uuid",
  "created_by": "uuid"
}
```

### `proposal.draft.requested` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "requested_by": "uuid",
  "job_id": "uuid|null"
}
```

### `proposal.draft.completed` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "model_used": "claude-sonnet-4-5",
  "input_tokens": 42150,
  "output_tokens": 18432,
  "cost_cents": 178,
  "eval_relevance": 0.89,
  "eval_faithfulness": 0.93,
  "eval_passed": true
}
```

### `proposal.draft.failed` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "error_code": "eval_gate_failed|model_exhausted|safety_blocked",
  "detail": "string"
}
```

### `proposal.status.changed` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "from_status": "drafting",
  "to_status": "ready_for_review",
  "actor_user_id": "uuid"
}
```

### `proposal.section.updated` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "section_id": "uuid",
  "version_id": "uuid",
  "author_id": "uuid",
  "is_ai_generated": false
}
```

### `proposal.lock.acquired` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "lock_id": "uuid",
  "holder_user_id": "uuid",
  "expires_at": "2026-07-03T12:30:00Z"
}
```

### `proposal.lock.released` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "released_by": "uuid|system",
  "released_reason": "explicit|expired|timeout"
}
```

---

## Review

### `review.comment.added` (v1)
```json
{
  "tenant_id": "uuid",
  "proposal_id": "uuid",
  "section_id": "uuid",
  "comment_id": "uuid",
  "author_id": "uuid"
}
```

### `review.comment.resolved` (v1)
```json
{
  "tenant_id": "uuid",
  "comment_id": "uuid",
  "resolved_by": "uuid"
}
```

---

## Tracking

### `submission.created` (v1)
```json
{
  "tenant_id": "uuid",
  "submission_id": "uuid",
  "proposal_id": "uuid",
  "funder_name": "string",
  "submitted_at": "2026-07-03T12:00:00Z"
}
```

### `submission.status.changed` (v1)
```json
{
  "tenant_id": "uuid",
  "submission_id": "uuid",
  "from_status": "submitted",
  "to_status": "under_review",
  "awarded_amount_cents": null
}
```

### `notification.dispatched` (v1)
```json
{
  "tenant_id": "uuid",
  "notification_id": "uuid",
  "user_id": "uuid",
  "kind": "deadline_reminder|review_request|comment_reply|system",
  "channels": ["in_app", "email"]
}
```

### `deadline.reminder.scheduled` (v1)
```json
{
  "tenant_id": "uuid",
  "grant_id": "uuid",
  "user_id": "uuid",
  "days_before": 7,
  "scheduled_for": "2026-07-10T09:00:00Z"
}
```

---

## Outbox publishing guarantees

- Events are written to `outbox_messages` in the same DB transaction as the state change.
- An outbox poller (Laravel command running in the k8s deployment, scaled independently) reads pending rows, publishes to Redis Streams, and marks `published` on success.
- On publish failure, the row is retried with exponential backoff (`attempts`, `next_attempt_at`).
- At-least-once delivery: consumers MUST be idempotent. The `event_id` is the idempotency key.
