# Research: GrantGenie Core

**Date**: 2026-07-03
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Purpose

Resolve the technical decisions required before Phase 1 design. Each section identifies a research question, the decision taken, the rationale, and the alternatives evaluated. All "NEEDS CLARIFICATION" items from the plan are resolved here.

---

## R1. Laravel 11 project structure for Clean Architecture + DDD

**Decision**: Adopt a `Domain → Application → Infrastructure → Presentation` layer structure under `backend/app/`, with `Domain` and `Application` strictly framework-free (no `Illuminate\*` imports). Enforce via PHPStan level max + dedicated Laravel architecture test that fails on any `App\Domain\*` → `App\Infrastructure\*` or `App\Domain\*` → `Illuminate\*` import.

**Rationale**:
- Constitution Principle II mandates Clean Architecture with dependencies pointing inward, violations caught by automated tests.
- Laravel 11's "streamlined application structure" still ships `app/Http`, `app/Models`, etc.; the `app/Domain + app/Application + app/Infrastructure` overlay is the established Laravel-12-compatible DDD pattern (Steve McDougall, Spatie's laravel-ddd, Brent Roose's blog).
- Testability: domain entities are plain PHP and unit-testable without booting Laravel.

**Alternatives considered**:
- *Hexagonal package by subcontext only (`app/Identity/...`)*: simpler, but couples HTTP routing to domain namespaces and makes inward-dependency harder to enforce with one rule. Rejected.
- *Modular monolith via nWidart/laravel-modules*: heavier ops surface (per-module migrations, service providers, composer) and not required for MVP scale. Deferred — re-evaluate if a second team needs strict module ownership.

**Sources**:
- Laravel 11 docs — "Directory Structure" (https://laravel.com/docs/11.x/structure)
- Spatie's laravel-ddd skeleton (architecture reference, not dependency)

---

## R2. Multi-tenant isolation in PostgreSQL with Laravel

**Decision**: Use a single `accounts` (tenants) table and a `tenant_id` foreign key on every domain table. Enforce isolation with Postgres Row-Level Security (RLS) policies keyed on a session variable `app.current_tenant_id`, set by a Laravel middleware that reads the JWT claim. Eloquent models cannot bypass RLS because the database role used by the app is a non-superuser with RLS enforced. Supplement with Pest integration tests that prove cross-tenant queries return zero rows.

**Rationale**:
- Constitution Principle IV: "Every table MUST carry a tenant key; row-level security MUST prevent cross-tenant data access by construction."
- RLS provides defense in depth — even a bug in middleware cannot leak data, because the DB rejects it.
- Single-database multi-tenancy is operationally simpler than schema-per-tenant at MVP scale (1k tenants) and supports cross-tenant aggregate queries needed for the FinOps dashboard.

**Alternatives considered**:
- *Schema-per-tenant*: stronger isolation, weaker analytics, more migrations. Overkill for MVP.
- *Database-per-tenant*: strongest isolation, no realistic at MVP cost. Rejected.
- *Application-layer only* (global scope on Eloquent): one missed `withoutGlobalScopes` leaks data. Constitution requires defense in depth.

**Sources**:
- PostgreSQL 16 RLS docs (https://www.postgresql.org/docs/16/ddl-rowsecurity.html)
- Postgres RLS + Laravel guide (Supabase blog)

---

## R3. RAG embedding & retrieval strategy

**Decision**: Use `text-embedding-3-small` (OpenAI, 1536 dims) as primary embedding model with a local `all-MiniLM-L6-v2` (384 dims) fallback for development and cost-sensitive tenants. Store embeddings in `pgvector` with HNSW index (`m=16, ef_construction=64`). Chunking: RecursiveCharacterTextSplitter, 800 tokens/chunk, 100 token overlap. Top-k=5 retrieval with cosine distance, then a reranker (`bge-reranker-base`) to top-3. Citations: every chunk carries `(document_id, chunk_index, page_number, char_offset_start, char_offset_end)`.

**Rationale**:
- pgvector is the constitution-mandated vector store.
- 1536 dims is the de-facto default that balances retrieval quality and storage; smaller models save cost but lose precision on long grant prose.
- HNSW outperforms IVFFlat for <1M vectors and supports incremental inserts without rebuild.
- Reranker is a proven 5-15% recall@5 lift; cost is acceptable for the proposal-draft latency budget.

**Alternatives considered**:
- *pgvector with IVFFlat*: faster build, slower recall, requires retraining on insert. Rejected for production.
- *Pinecone / Weaviate / Qdrant*: external dependency, extra cost, not mandated. Rejected.
- *Chunking by sentence only*: produces too many small chunks, hurts citation precision. Rejected.

---

## R4. Multi-model AI routing & fallback

**Decision**: Build a `ModelRouter` in the Python AI service that maintains an ordered capability table per task (proposal-draft, budget-narrative, funder-tailoring, citation-extraction, eval-judging). Default tier mapping: cheap → `gpt-4o-mini` or `claude-haiku-4-5`; mid → `gpt-4o` or `claude-sonnet-4-5`; premium → `o1-preview` or `claude-opus-4`. For each request: try cheapest model with adequate capability; on 5xx/timeout/rate-limit, retry with next tier; after exhausting tiers, enqueue the request and return a `202 Accepted` with a job token. Cost logged per token per model per tenant.

**Rationale**:
- Spec clarification (2026-07-03): "Multi-model with cost-aware routing — cheapest adequate model first per task, with automatic fallback to higher-tier on failure."
- Constitution Principle V: "model routing (cheapest adequate model first)" is mandated.
- Graceful degradation is required by spec Edge Case: "AI service is unavailable → queue, notify, complete asynchronously."

**Alternatives considered**:
- *Single primary + one static fallback*: simpler, but does not optimize cost — most requests can run on a cheap model.
- *Pure cost-optimization (always cheapest)*: violates the eval-gate quality requirement when cheap model fails faithfulness.
- *Per-tenant BYOK*: defers cost to tenant, conflicts with constitution's centralized FinOps dashboard.

---

## R5. Eligibility matching algorithm (boolean)

**Decision**: A grant carries an `EligibilityRule` set (list of `(field, operator, value)` triples over org profile fields: `mission_keywords`, `ntee_codes`, `service_area_states`, `organization_type`, `annual_budget_range`, `years_operating`). The matcher evaluates rules left-to-right; an OR of rule groups produces "eligible" if any group fully matches, "not eligible" otherwise. Each decision records the matching rule IDs for the audit log. Rules are authored by the corpus ingestion pipeline from the funder's published guidelines (parsed via LLM into structured rules) and reviewed by the Admin on first ingestion per funder.

**Rationale**:
- Spec clarification (2026-07-03): "Boolean match — eligible or not eligible based on org profile fields vs. grant criteria."
- Boolean is fast (no LLM in the hot path), explainable (which rule fired), and auditable (constitution FR-011).
- The rule list is bounded and stable per funder, so admin review is one-time.

**Alternatives considered**:
- *LLM judge per grant at query time*: too slow for the 5 s p95 budget; too expensive at scale.
- *Hardcoded SQL with no rule abstraction*: unmaintainable as funder count grows.
- *Vector similarity between org profile and grant description*: ambiguous, non-boolean, harder to test.

---

## R6. Grant corpus ingestion: Grants.gov + 2 foundation directories

**Decision**: Daily scheduled crawler (Laravel scheduler → Kubernetes CronJob) at 03:00 UTC, fetching from:
1. **Grants.gov** — REST API (https://api.grants.gov/v1/api/search2) with full opportunity dump; XML response parsed to a normalized `Grant` record.
2. **Candid Foundation Directory** (formerly GuideStar/Candid) — partner API; OAuth2 client-credentials flow.
3. **Instrumentl** — public feed of indexed RFPs.

Each source has an `IngestionAdapter` interface; the crawler fans out in parallel, deduplicates by `(source, source_grant_id)`, upserts, and updates an `IngestionRun` record with timestamps and counts. Staleness = 24 h ceiling; a visible "last refreshed" badge on the discovery UI shows the timestamp.

**Rationale**:
- Spec clarification (2026-07-03): "Daily scheduled crawler (Grants.gov + 2-3 foundation directories), staleness tolerated up to 24h."
- Three sources is a balance: one federal + two private gives coverage breadth without per-source integration cost.
- Daily cadence is sufficient because nonprofit RFP cycles are days-to-weeks, not minutes.

**Alternatives considered**:
- *Real-time webhook (Grants.gov)*: not offered by Grants.gov; would require polling any way.
- *Weekly*: misses the 14-day reminder window edge case.
- *Hourly*: wasteful for slow-moving funder data.

---

## R7. Edit-locking for proposal concurrent-edit prevention

**Decision**: When a Writer (author) opens a proposal for edit, the backend acquires a Redis key `proposal-lock:{proposal_id}` via `SET NX EX 1800` (30 min TTL, refreshed by a heartbeat every 5 min). The lock holder is the only role allowed to `PATCH` proposal content. Reviewers attempting `PATCH` get `423 Locked`. On explicit "release" or TTL expiry the lock clears. UI shows a banner to the author with the TTL countdown. The 30-min TTL balances long drafting sessions against lock-out after abandonment.

**Rationale**:
- Spec clarification (2026-07-03): "Last-write-wins with edit-locking — author holds an exclusive edit lock on a proposal; reviewers can only add inline comments, not edit content."
- Redis is already in the stack and provides atomic `SET NX EX` with TTL.
- TTL prevents permanent lockout if a session is lost (Edge Case: "expired authentication session → save draft, prompt re-auth, resume").

**Alternatives considered**:
- *Postgres advisory lock*: works, but couples draft state to DB connection lifetime; harder to release explicitly.
- *Optimistic concurrency (version column)*: last-write-wins violates "no concurrent conflicting edits" from the spec.
- *Operational transform / CRDT*: overkill for a single-author model.

---

## R8. Notification & reminder delivery

**Decision**: Reminder scheduler (Laravel scheduler + k8s CronJob) runs daily at 09:00 UTC per tenant timezone. For each tracked grant, compute days-to-deadline; if `days ∈ {14, 7, 1}` and the same (grant, days) reminder has not been sent before, dispatch a `DeadlineReminder` event to a Redis Stream `notifications`. A `NotificationWorker` Python service consumes the stream and delivers: (1) an in-app notification (row in `notifications` table + WebSocket push if user is connected), (2) an email via the configured SMTP (SendGrid or SES). Idempotency key = `(grant_id, days)`. No SMS, no push.

**Rationale**:
- Spec clarification (2026-07-03): "In-app notification plus email at 14 days, 7 days, and 1 day before deadline."
- Redis Streams gives reliable delivery with replay on consumer crash; aligns with the outbox pattern already required (FR-011 / `OutboxMessage` entity).
- SMTP via transactional provider is constitution-compatible and avoids SMS/push vendor lock-in for MVP.

**Alternatives considered**:
- *In-DB polling (cron queries)*: doesn't scale past ~10k tracked grants; CloudEvents is the cleaner answer.
- *AWS SES SNS for SMS*: spec explicitly excludes SMS for MVP.

---

## R9. API contract: REST + OpenAPI 3.1

**Decision**: The Laravel backend exposes a versioned REST API at `/api/v1/...` documented in `contracts/api-openapi.yaml` (OpenAPI 3.1). All write endpoints require `Idempotency-Key` header (UUID) keyed in Redis with 24 h TTL. Errors follow RFC 7807 `application/problem+json`. Auth: OAuth2 Bearer JWT (RS256) issued by Auth0; Laravel validates via `lcobucci/jwt`. The Angular SPA uses a typed client generated from the OpenAPI spec via `ng-openapi-gen`. The AI service is consumed by the backend over a thin internal HTTP/JSON contract (not exposed to the public API).

**Rationale**:
- Constitution mandates RFC 7807 (FR-017) and idempotency keys (FR-016).
- OpenAPI as source of truth prevents client/server drift.
- Internal AI service over HTTP (not gRPC) keeps the Python service framework-agnostic and simpler to deploy; gRPC deferred until a second internal client appears.

**Alternatives considered**:
- *GraphQL*: more flexible, but inconsistent with "RBAC enforced in application layer" (every GraphQL field needs explicit AuthZ) and the 200/400 ms latency budgets.
- *tRPC-like typed RPC*: only useful if backend and frontend share TypeScript; we have a PHP backend.

---

## R10. Authentication & RBAC

**Decision**: Auth0 (or compatible OIDC provider) issues OIDC tokens; Laravel validates via middleware. Roles (`admin`, `writer`, `reviewer`, `viewer`) are claims in the JWT and mirrored in the `users` table for audit. Authorization uses `spatie/laravel-permission` for in-app checks and middleware aliases (`role:writer,reviewer`) for route-level guards. Tenant scope is enforced by middleware that injects `account_id` into the request and asserts the user's `account_id` matches; cross-tenant access returns `403`.

**Rationale**:
- Constitution Principle IV: "AuthN uses OIDC/OAuth2; AuthZ uses RBAC/ABAC enforced in the application layer."
- Spec FR-018: "four roles — Admin, Writer, Reviewer, Viewer — scoped per tenant."
- Auth0 abstracts the IdP (Google, Microsoft, email+password, etc.) without us re-implementing flows.

**Alternatives considered**:
- *Laravel Passport as the IdP*: we'd run and protect the IdP ourselves; out of scope for MVP.
- *Keycloak self-hosted*: more control, much higher ops cost. Rejected for MVP.

---

## R11. Observability stack

**Decision**: OpenTelemetry SDK in each service; traces shipped to an OTLP collector and persisted in Jaeger (dev) or Azure Monitor (prod). Logs: structured JSON via `monolog` (PHP) and `structlog` (Python), rotated daily to `storage/logs/<service>/YYYY-MM-DD.json` (constitution Principle V). Metrics: Prometheus scrape on each service's `/metrics`, aggregated in Grafana. Correlation ID: `X-Correlation-Id` header generated at the edge (Angular) or at the load balancer, propagated through every hop, included in every log line and trace span.

**Rationale**:
- Constitution Principle V mandates structured JSON logs with correlation IDs, rotated daily.
- OTel is vendor-neutral and aligns with the Azure-native posture for production.

**Alternatives considered**:
- *Azure Application Insights SDK directly*: vendor lock-in, harder to test in CI.
- *Laravel Telescope in prod*: dev-only tool; not for production tracing.

---

## R12. CI/CD pipeline

**Decision**: GitHub Actions workflows on push and PR:
- `ci-backend.yml`: composer install → PHPStan max → Pest (unit + feature + architecture) → Dusk (E2E smoke)
- `ci-frontend.yml`: npm ci → ng lint → ng test → ng build → Playwright e2e
- `ci-ai-service.yml`: uv pip install → ruff → mypy → pytest (unit + integration via Testcontainers) → eval-gate threshold check
- `eval-gates.yml`: nightly, runs the full RAGAS + deepeval evaluation against a frozen eval dataset; fails the build if any threshold drops below spec SC-003
- `deploy.yml`: on main, builds images, pushes to ACR, `terraform apply` for the target env, `kubectl rollout` with smoke tests and a canary step

**Rationale**:
- Constitution mandates CI for unit, integration, AI eval, SAST/SCA/secret scan, performance budgets.
- Nightly eval gates catch silent quality regressions from model-provider updates.

**Alternatives considered**:
- *Azure DevOps Pipelines*: viable, but the team standardizes on GitHub.
- *Single mega-workflow*: harder to triage failures; matrix jobs in parallel are faster.

---

## R13. Local dev environment

**Decision**: `docker-compose.yml` brings up the full stack:
- `postgres` (with `pgvector` image `pgvector/pgvector:pg16`)
- `redis`
- `minio` (S3-compatible)
- `mailhog` (SMTP for local email testing)
- `backend` (Laravel artisan serve + horizon)
- `frontend` (Angular dev server with hot reload)
- `ai-service` (uvicorn with hot reload)
- `jaeger` (trace UI)
- `prometheus` + `grafana` (optional, profile `observability`)

A `Makefile` exposes `make up`, `make down`, `make test`, `make eval`, `make seed-demo`. Seed data: 3 demo tenants, 50 grants, 5 boilerplate docs, 2 proposal templates.

**Rationale**:
- Single-command onboarding is essential for a 3-service system.
- Testcontainers is used by tests, not by the dev stack, to avoid resource contention.

---

## R14. Performance budgets enforcement

**Decision**: k6 scripts in `tests/load/` exercise the SC-001/002 budgets against a deployed staging environment. The `ci-eval.yml` and `deploy.yml` workflows include a `k6 run` stage that aborts the pipeline if p95 exceeds the budget. PHP-FPM tuning and Postgres connection pool sizing are documented in `infra/README.md`.

**Rationale**:
- Constitution Principle III: "performance budgets MUST be enforced in CI."
- k6 is lightweight and produces JUnit-compatible output for GitHub annotations.

---

## R15. Document upload & parsing

**Decision**: Browser uploads via multipart to backend → backend streams to S3-compatible storage → a `DocumentProcessing` job (Laravel queue, Redis driver) downloads, parses, chunks, embeds, and upserts. Accepted formats: PDF, DOCX, TXT, MD. Parsers: `smalot/pdfparser` for PDF, `phpoffice/phpword` for DOCX, native for TXT/MD. Files >10 MB rejected; max 50 documents per tenant per month (soft cap, Admin-configurable).

**Rationale**:
- Spec Edge Case: "unsupported document format → reject with supported format list (PDF, DOCX, TXT, MD)."
- Async processing prevents request timeouts on large documents.

**Alternatives considered**:
- *Client-side parsing (PDF.js)*: leaks document content to the browser; out of scope.

---

## R16. Proposal versioning

**Decision**: Every successful `PATCH /proposals/{id}/sections/{sid}` creates a row in `proposal_section_versions` (immutable) with `(section_id, content, content_hash, author_id, created_at, edit_lock_id)`. Authors can view and restore prior versions from a "Version history" drawer. Versions are append-only, never deleted (audit trail).

**Rationale**:
- Spec Key Entities: "Proposal — AI-generated draft with sections, citations, status, and version history."
- Append-only version rows double as the audit log for proposal content changes.

---

## Summary of resolved decisions

All 16 research questions resolved. No "NEEDS CLARIFICATION" items remain. Proceed to Phase 1 design.
