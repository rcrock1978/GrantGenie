# Quickstart Validation Guide: GrantGenie Core

**Date**: 2026-07-03
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Data model**: [data-model.md](./data-model.md)
**Contracts**: [contracts/](./contracts/)

This document is a runnable validation guide that proves the system works end-to-end. It does not include implementation code, model/service bodies, or complete test suites — those live in `tasks.md` and the implementation phase. Use this guide to verify that the implemented system satisfies the spec's success criteria.

## Prerequisites

- Docker + Docker Compose v2
- `make` (or run the equivalent `docker compose` commands directly)
- Auth0 tenant (or compatible OIDC provider) with a configured application
- API keys for at least one AI model provider (OpenAI or Anthropic)
- SMTP credentials (or use MailHog locally)

## 1. Bring up the local stack

```bash
make up
```

Expected: services `postgres`, `redis`, `minio`, `mailhog`, `backend`, `frontend`, `ai-service` all healthy within 60 s.

Verify:
```bash
curl -sf http://localhost:8000/api/v1/healthz
curl -sf http://localhost:8001/internal/v1/healthz
curl -sf http://localhost:4200
```

## 2. Seed demo data

```bash
make seed-demo
```

Creates:
- 3 tenants (Acme Literacy, Bay Area Food Bank, Coastal Arts Collective)
- 50 grants across the 3 ingestion sources
- 5 boilerplate documents per tenant
- 2 proposal templates
- 1 admin + 1 writer + 1 reviewer per tenant

## 3. Validate the multi-tenant isolation invariant (SC-005)

Run the architecture test suite:
```bash
make test-isolation
```

Expected: passes 0 cross-tenant leak tests. This is the automated proof of `SC-005`.

## 4. Validate performance budgets (SC-001, SC-002, constitution Principle III)

```bash
make load-test-smoke
```

Runs k6 scripts in `tests/load/`:
- `discovery.js`: 100 concurrent users over 60 s, p95 < 5 s (SC-001)
- `draft.js`: 10 concurrent drafting requests, p95 < 6 s (SC-002)

Expected: both budgets pass.

## 5. Walk through the P1 user stories

Each scenario has a matching Playwright E2E spec in `frontend/e2e/`. Run them in order:

```bash
make e2e-p1
```

Scenarios:

### 5.1 User Story 1 — Grant Discovery & Eligibility Match
- Sign in as `writer@acme-literacy.test`
- Complete the org profile (Acme Literacy, mission, programs, NTEE codes, service area)
- Navigate to **Discovery**
- Search grants filtered by category `education`, amount `$50k-$200k`, deadline `within 90 days`
- **Expect**: list of grants appears within 5 s, each marked **eligible** with rule citations
- Empty-search variant: filter to a category with no matches
- **Expect**: empty-state message with suggestions to broaden criteria

### 5.2 User Story 2 — Org Profile & Boilerplate Library
- As `writer@acme-literacy.test`
- Upload a PDF boilerplate document ("Annual Report 2025.pdf")
- **Expect**: status transitions `uploaded → processing → ready` within 30 s
- Search the document library for "evaluation metrics"
- **Expect**: top-3 relevant chunks with document title, page number, and content snippet

### 5.3 User Story 3 — Proposal Drafting Grounded in Org Docs
- From a discovered grant, click **Draft proposal**
- **Expect**: streaming progress (server-sent events) for each section
- **Expect**: complete draft with citations in each section within 6 s p95
- Open the **Eval panel**
- **Expect**: `relevance ≥ 0.85` and `faithfulness ≥ 0.90` (SC-003)
- If eval fails: **Expect** a 422 with the failure detail and an option to retry with a different model tier

### 5.4 User Story 7 — Reviewer Workflow (P3, but a critical collaboration primitive)
- As `admin@acme-literacy.test`, invite `reviewer@acme-literacy.test`
- As `writer@acme-literacy.test`, transition the proposal to `ready_for_review`
- As `reviewer@acme-literacy.test`, open the proposal
- **Expect**: read-only view (edit controls disabled)
- Add an inline comment on the "Need Statement" section
- As `writer@acme-literacy.test`, refresh
- **Expect**: comment appears, can be resolved
- Try to open a second browser as a different user and edit a section
- **Expect**: `423 Locked` for the non-holder

## 6. Validate P2 / P3 features

```bash
make e2e-p2-p3
```

### 6.1 User Story 4 — Budget Narrative Helper
- Open a drafted proposal
- Add a budget item: `Personnel — Program Director 0.5 FTE — $65,000`
- Click **Generate narrative**
- **Expect**: narrative text appears, aligned with proposal activities and funder category

### 6.2 User Story 5 — Deadline & Submission Tracker
- From a discovered grant, click **Track**
- Verify the in-app notification appears at 14, 7, and 1 days before the deadline (use the demo time-machine helper: `make advance-time 14d`)
- **Expect**: in-app notification row + email in MailHog (http://localhost:8025) at each threshold
- Mark the proposal as submitted
- **Expect**: `Submission` row created, status `submitted`

### 6.3 User Story 6 — Funder-Specific Tailoring
- From the same proposal, select **Tailor for different funder**
- Choose another grant from the corpus
- **Expect**: regenerated draft with distinct language, structure, and emphasis; side-by-side diff available

## 7. Validate non-functional requirements

```bash
make validate-nfrs
```

Runs in sequence:

| Check | Command | Expected |
|---|---|---|
| Logs are structured JSON with correlation IDs (SC-006) | `jq -e '.correlation_id' backend/storage/logs/$(date +%F).json \| head` | 100% have `correlation_id` |
| API availability (SC-004) | Run 1 h k6 soak test, `tests/load/soak.js` | error rate < 0.1% |
| Eval thresholds (SC-003) | `make eval-gates` | relevance ≥ 0.85 AND faithfulness ≥ 0.90 on frozen dataset |
| AI service unavailability recovery | `docker compose stop ai-service; trigger a draft; docker compose start ai-service` | request queued, completes within 60 s of restart |
| Cross-tenant leak (SC-005) | `make test-isolation` | 0 leaks |
| Eval-gate cost ceiling | `make finops-report` | per-tenant cost visible, under configured cap |
| Security scan | `make security-scan` | no HIGH/CRITICAL findings in SAST, SCA, or secret scan |

## 8. Tear down

```bash
make down
```

Volumes (`postgres_data`, `redis_data`, `minio_data`) are preserved for re-runs. To wipe:

```bash
make down && make clean
```

## 9. What "done" means for MVP

All of the following must be true:
- [ ] `make e2e-p1` passes
- [ ] `make e2e-p2-p3` passes
- [ ] `make validate-nfrs` passes
- [ ] `make load-test-smoke` p95 within budget
- [ ] SC-001 through SC-007 all measured and meeting target
- [ ] No HIGH/CRITICAL security findings
- [ ] All audit-log writes verified by integration test (FR-011)
- [ ] RLS isolation verified by integration test (FR-012)
- [ ] Prompt-injection + PII redaction verified by red-team test (FR-013)
- [ ] Idempotency keys verified by integration test (FR-016)
- [ ] RFC 7807 problem-details verified by integration test (FR-017)

When all boxes are checked, the feature is ready for production deployment via `make deploy-prod`.
