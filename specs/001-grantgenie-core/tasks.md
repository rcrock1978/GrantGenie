---
description: "Task list for GrantGenie core implementation"
---

# Tasks: GrantGenie Core

**Input**: Design documents from `/specs/001-grantgenie-core/`
- `plan.md` (tech stack, structure)
- `spec.md` (user stories P1–P3, 18 FRs, 7 SCs)
- `data-model.md` (5 bounded contexts, ~20 entities)
- `contracts/api-openapi.yaml`, `contracts/ai-service-http.yaml`, `contracts/events.md`
- `research.md` (16 resolved decisions)
- `quickstart.md` (validation scenarios)

**Tests**: Constitution Principle III requires TDD; the spec is silent on explicit test-only phases. Test tasks are interleaved per user story as the "Tests (if requested)" pattern. The eval-gate and isolation test tasks in Phase 2 are required, not optional, because the constitution mandates them in CI.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend (Laravel)**: `backend/`
- **Frontend (Angular)**: `frontend/`
- **AI service (Python)**: `ai-service/`
- **Infra (Terraform)**: `infra/`
- **CI**: `.github/workflows/`
- **Tests**: `backend/tests/`, `frontend/e2e/`, `ai-service/tests/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization, monorepo skeleton, and tooling baseline.

- [X] T001 Create monorepo skeleton (backend/, frontend/, ai-service/, infra/, .github/workflows/, docker-compose.yml, Makefile) at repo root
- [X] T002 Initialize Laravel 11 project in backend/ with composer (laravel/laravel ^11.0) and required composer packages (spatie/laravel-permission, lcobucci/jwt, ramsey/uuid, predis/predis, open-telemetry/opentelemetry-php, league/csv, smalot/pdfparser, phpoffice/phpword)
- [X] T003 [P] Initialize Angular 18 project in frontend/ with npm (standalone components, Angular Signals, NG-ZORRO UI library, ng-openapi-gen, openid-client-js, monaco-editor, jest, playwright, storybook)
- [X] T004 [P] Initialize Python 3.12+ FastAPI project in ai-service/ with uv (fastapi, pydantic v2, langchain, openai, anthropic, tiktoken, pgvector, sentence-transformers, bge-reranker, deepeval, ragas, structlog, opentelemetry-instrumentation-fastapi, pytest, pytest-asyncio, testcontainers, ruff, mypy)
- [X] T005 [P] Configure backend linting/formatting/arch-test: phpstan.neon (level: max), laravel-architecture-tester, pint, pest
- [X] T006 [P] Configure frontend linting/formatting: eslint, prettier, ng lint config, husky pre-commit hook for backend + frontend + ai-service
- [X] T007 [P] Configure AI service linting/formatting: ruff.toml, mypy.ini, pyproject.toml with strict type checking
- [X] T008 Create docker-compose.yml with services: postgres (pgvector/pgvector:pg16), redis, minio, mailhog, backend (artisan serve), frontend (ng serve), ai-service (uvicorn), jaeger, prometheus, grafana
- [X] T009 Create root Makefile with targets: up, down, test, test-backend, test-frontend, test-ai, test-isolation, e2e-p1, e2e-p2-p3, validate-nfrs, load-test-smoke, eval-gates, finops-report, security-scan, seed-demo, advance-time, deploy-prod
- [X] T010 [P] Create root README.md with quickstart, architecture diagram (Mermaid), link to .specify/memory/constitution.md and specs/001-grantgenie-core/

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T011 Create pgvector extension migration: backend/database/migrations/2026_07_03_000001_create_pgvector_extension.php
- [ ] T012 [P] Create accounts, users, roles, user_roles migrations with RLS policies in backend/database/migrations/
- [ ] T013 [P] Create org_profiles migration with RLS in backend/database/migrations/
- [ ] T014 [P] Create grants, eligibility_decisions, ingestion_sources, ingestion_runs migrations with RLS in backend/database/migrations/
- [ ] T015 [P] Create boilerplate_documents, document_chunks (with HNSW index) migrations with RLS in backend/database/migrations/
- [ ] T016 [P] Create proposals, proposal_sections, proposal_section_versions, citations, review_comments, edit_locks, budget_items migrations with RLS in backend/database/migrations/
- [ ] T017 [P] Create submissions, notifications, deadline_reminders, audit_logs (immutable, incl. Postgres trigger blocking UPDATE/DELETE on audit_logs), outbox_messages, idempotency_keys migrations with RLS in backend/database/migrations/
- [ ] T018 Implement TenantScope middleware in backend/app/Http/Middleware/TenantScope.php (sets `app.current_tenant_id` Postgres session var from JWT, asserts user account_id match)
- [ ] T019 [P] Implement IdempotencyKey middleware in backend/app/Http/Middleware/IdempotencyKey.php (24h Redis-backed key store; replays cached response)
- [ ] T020 [P] Implement CorrelationIdMiddleware in backend/app/Http/Middleware/CorrelationIdMiddleware.php (generates/propagates X-Correlation-Id; injects into logs and outbox events)
- [ ] T021 [P] Implement ProblemDetailsHandler in backend/app/Exceptions/Handler.php (RFC 7807 application/problem+json responses with correlation_id)
- [ ] T022 [P] Configure OIDC JWT auth in backend/config/auth.php + backend/app/Http/Middleware/VerifyOidcToken.php (RS256, Auth0-issued)
- [ ] T023 [P] Configure spatie/laravel-permission roles (admin/writer/reviewer/viewer) in backend/database/seeders/RoleSeeder.php
- [ ] T024 [P] Configure monolog JSON logging in backend/config/logging.php with daily rotation to backend/storage/logs/$(date +%F).json
- [ ] T025 [P] Configure OpenTelemetry SDK in backend/ (auto-instrumentation for Laravel + Eloquent + Redis + HTTP) with OTLP exporter
- [ ] T026 [P] Configure OpenTelemetry in ai-service/src/grantgenie_ai/core/telemetry.py (FastAPI + httpx + asyncpg auto-instrumentation)
- [ ] T027 [P] Configure structured logging in ai-service/src/grantgenie_ai/core/logging.py (structlog, JSON output, correlation_id)
- [ ] T028 [P] Configure OutboxPublisher Laravel command in backend/app/Console/Commands/PublishOutboxCommand.php (reads pending outbox_messages, publishes to Redis Stream grantgenie.events with retries and exponential backoff)
- [ ] T029 [P] Implement AIServiceClient HTTP adapter in backend/app/Infrastructure/External/AIServiceClient.php (calls ai-service/internal/v1/...; internal mTLS or shared secret auth; maps errors to ProblemDetails)
- [ ] T030 [P] Implement EventPublisher in backend/app/Infrastructure/Messaging/EventPublisher.php (writes events to outbox_messages in same DB transaction as state change)
- [ ] T031 [P] Set up Redis-backed rate limiter, query cache, and edit-lock manager in backend/app/Infrastructure/Cache/
- [ ] T032 [P] Set up object storage adapter in backend/app/Infrastructure/Storage/DocumentStorage.php (S3-compatible, MinIO in dev, Azure Blob in prod)
- [ ] T033 [P] Implement RBAC policy tests in backend/tests/Architecture/ (PHPStan + Laravel architecture tests enforcing: App\Domain\* has no Illuminate\*, no App\Infrastructure\*; App\Application\* depends only on App\Domain\*; controllers in App\Infrastructure\Http\ depend on Application ports not Domain concrete classes)
- [ ] T034 [P] Implement multi-tenant RLS isolation test suite in backend/tests/Integration/Isolation/ (creates 2 tenants, proves cross-tenant reads return 0 rows across all tables; runs in CI as the SC-005 gate)
- [ ] T035 [P] Configure GitHub Actions CI workflows at .github/workflows/ci-backend.yml, ci-frontend.yml, ci-ai-service.yml, eval-gates.yml, deploy.yml, security.yml (SAST: semgrep, SCA: snyk/trivy, secret scan: gitleaks; required by constitution Governance §4 step 5 before any PR merge)
- [ ] T036 [P] Generate Angular typed API client from contracts/api-openapi.yaml in frontend/src/app/core/api/ (using ng-openapi-gen)
- [ ] T037 [P] Implement core Angular infrastructure: AuthInterceptor, OidcAuthService, TenantContextService, ApiClient wrapper, AppShell layout, route guards (authGuard, roleGuard) in frontend/src/app/core/
- [ ] T038 [P] Implement ai-service FastAPI app skeleton: main.py, config (pydantic-settings), /healthz, /readyz, dependency injection wiring, ai-service/src/grantgenie_ai/api/router.py

**Checkpoint**: Foundation ready — user story implementation can now begin in parallel.

---

## Phase 3: User Story 1 — Grant Discovery & Eligibility Match (Priority: P1) 🎯 MVP

**Goal**: A nonprofit user (Writer) discovers relevant grant opportunities and checks eligibility via boolean matching against the org profile, with p95 < 5 s (SC-001).

**Independent Test**: A registered tenant with a completed org profile can call `POST /api/v1/discovery/search` and receive a paginated list of grants, each marked "eligible" with rule citations; multi-tenant isolation holds (no cross-tenant data leak, SC-005).

### Tests for User Story 1

- [ ] T039 [P] [US1] Contract test for POST /api/v1/discovery/search in backend/tests/Contract/DiscoverySearchTest.php (validates request/response against contracts/api-openapi.yaml)
- [ ] T040 [P] [US1] Integration test for discovery happy path in backend/tests/Feature/Discovery/DiscoverGrantsTest.php (seed tenant, profile, grants; assert eligible results within 5 s p95)
- [ ] T041 [P] [US1] Integration test for empty results in backend/tests/Feature/Discovery/EmptyResultsTest.php
- [ ] T042 [P] [US1] Integration test for cross-tenant isolation in backend/tests/Feature/Discovery/CrossTenantLeakTest.php (SC-005)
- [ ] T043 [P] [US1] Load test for SC-001 p95 < 5 s budget in tests/load/discovery.js (k6)
- [ ] T044 [P] [US1] E2E Playwright test for discovery search in frontend/e2e/us1-discovery.spec.ts

### Implementation for User Story 1

- [ ] T045 [P] [US1] Create Domain entity Grant + value objects EligibilityRule, EligibilityDecision in backend/app/Domain/Grant/
- [ ] T046 [P] [US1] Create Domain entity OrgProfile in backend/app/Domain/OrgProfile/
- [ ] T047 [P] [US1] Create Domain entity Account (tenant) in backend/app/Domain/Identity/
- [ ] T048 [US1] Implement Eloquent repositories GrantRepository, OrgProfileRepository in backend/app/Infrastructure/Persistence/
- [ ] T049 [US1] Implement application service EligibilityMatcher in backend/app/Application/Grant/EligibilityMatcher.php (boolean rule evaluation; records EligibilityDecision; caches by org_profile_hash)
- [ ] T050 [US1] Implement application service DiscoverGrants in backend/app/Application/Grant/DiscoverGrants.php (composes OrgProfile lookup + EligibilityMatcher + paginated Grant query)
- [ ] T051 [US1] Implement IngestionSource domain entity + IngestionRun in backend/app/Domain/Grant/
- [ ] T052 [P] [US1] Implement GrantsGovAdapter (REST XML parser) in backend/app/Infrastructure/External/Ingestion/GrantsGovAdapter.php
- [ ] T053 [P] [US1] Implement CandidAdapter (OAuth2 client-credentials) in backend/app/Infrastructure/External/Ingestion/CandidAdapter.php
- [ ] T054 [P] [US1] Implement InstrumentlAdapter in backend/app/Infrastructure/External/Ingestion/InstrumentlAdapter.php
- [ ] T055 [US1] Implement IngestionService in backend/app/Application/Grant/IngestionService.php (fans out adapters in parallel, dedupes, upserts, records IngestionRun; emits grant.ingestion.completed)
- [ ] T056 [US1] Schedule daily ingestion k8s CronJob in infra/modules/cron/ingestion-cronjob.yaml (03:00 UTC) and backend/routes/console.php schedule entry
- [ ] T057 [US1] Implement FormRequest + Controller DiscoverGrantsController in backend/app/Infrastructure/Http/Controllers/Discovery/ (validates GrantSearchInput; returns GrantPage with corpus_last_refreshed_at)
- [ ] T058 [US1] Implement GET /api/v1/discovery/grants/{id} controller in backend/app/Infrastructure/Http/Controllers/Discovery/GetGrantController.php
- [ ] T059 [US1] Wire routes in backend/routes/api.php (v1 group with auth + tenant + idempotency middleware)
- [ ] T060 [P] [US1] Implement Angular Discovery feature module in frontend/src/app/features/discovery/ (SearchForm component, ResultsTable component, GrantDetail component, corpus-refreshed badge, staleness banner per spec Edge Case when corpus_last_refreshed_at is older than 24h)
- [ ] T061 [P] [US1] Implement EmptyState component for discovery in frontend/src/app/features/discovery/components/empty-state.component.ts
- [ ] T062 [US1] Implement guided org-profile prompt when discovery is invoked with incomplete profile (in DiscoverGrantsController; returns 412 + Problem type tag)
- [ ] T063 [US1] Emit domain events grant.ingestion.completed and grant.eligibility.re_evaluated via EventPublisher

**Checkpoint**: User Story 1 fully functional and independently testable. MVP is deliverable from this point.

---

## Phase 4: User Story 2 — Org Profile & Boilerplate Library (Priority: P1)

**Goal**: A nonprofit builds and maintains a profile with mission, boilerplate text, past proposals, and supporting documents that ground all future proposal drafts.

**Independent Test**: A user can create an org profile, upload PDF/DOCX/TXT/MD documents, and the RAG layer returns relevant chunks with source citations on query.

### Tests for User Story 2

- [ ] T064 [P] [US2] Contract test for GET/PUT /api/v1/org-profile in backend/tests/Contract/OrgProfileTest.php
- [ ] T065 [P] [US2] Contract test for POST /api/v1/boilerplate/documents in backend/tests/Contract/BoilerplateUploadTest.php
- [ ] T066 [P] [US2] Integration test for document upload + processing in backend/tests/Feature/Boilerplate/UploadAndProcessTest.php
- [ ] T067 [P] [US2] Integration test for unsupported format rejection in backend/tests/Feature/Boilerplate/UnsupportedFormatTest.php
- [ ] T068 [P] [US2] Integration test for 10 MB size cap in backend/tests/Feature/Boilerplate/SizeCapTest.php
- [ ] T069 [P] [US2] Integration test for SC-007 setup time < 10 min in backend/tests/Feature/Onboarding/SetupTimeTest.php
- [ ] T070 [P] [US2] E2E Playwright test for org profile + boilerplate in frontend/e2e/us2-org-profile.spec.ts

### Implementation for User Story 2

- [ ] T071 [P] [US2] Create BoilerplateDocument + DocumentChunk domain entities in backend/app/Domain/OrgProfile/
- [ ] T072 [US2] Implement BoilerplateDocumentRepository + DocumentChunkRepository in backend/app/Infrastructure/Persistence/
- [ ] T073 [US2] Implement OrgProfileService (CRUD) in backend/app/Application/OrgProfile/OrgProfileService.php
- [ ] T074 [US2] Implement FormRequest + Controller OrgProfileController in backend/app/Infrastructure/Http/Controllers/OrgProfile/
- [ ] T075 [US2] Implement DocumentStorage.put() and DocumentStorage.get() (S3-compatible) in backend/app/Infrastructure/Storage/
- [ ] T076 [US2] Implement DocumentUploadController (multipart, validates format + size) in backend/app/Infrastructure/Http/Controllers/Boilerplate/
- [ ] T077 [US2] Implement DocumentProcessingJob in backend/app/Jobs/DocumentProcessingJob.php (downloads from storage, parses, chunks, embeds, upserts; status transitions uploaded→processing→ready/failed; depends on T076)
- [ ] T078 [P] [US2] Implement PDF parser in backend/app/Infrastructure/Parsing/PdfParser.php (smalot/pdfparser)
- [ ] T079 [P] [US2] Implement DOCX parser in backend/app/Infrastructure/Parsing/DocxParser.php (phpoffice/phpword)
- [ ] T080 [P] [US2] Implement TXT/MD parser in backend/app/Infrastructure/Parsing/TextParser.php
- [ ] T081 [US2] Implement AIServiceClient.embed() (calls ai-service/internal/v1/rag/retrieve for similarity check) in backend/app/Infrastructure/External/AIServiceClient.php
- [ ] T082 [US2] Implement ai-service /rag/retrieve endpoint: pgvector HNSW search + bge-reranker, in ai-service/src/grantgenie_ai/retrieval/
- [ ] T083 [US2] Implement BoilerplateDocumentController list/delete endpoints in backend/app/Infrastructure/Http/Controllers/Boilerplate/
- [ ] T084 [P] [US2] Implement Angular OrgProfile feature in frontend/src/app/features/org-profile/ (ProfileForm, completeness indicator, validation)
- [ ] T085 [P] [US2] Implement Angular Boilerplate feature in frontend/src/app/features/boilerplate/ (DocumentList, UploadDialog with drag-drop + progress, status badges)
- [ ] T086 [US2] Emit domain events org_profile.completed, boilerplate.document.uploaded, boilerplate.document.processed, boilerplate.document.failed
- [ ] T087 [US2] Trigger EligibilityDecision re-evaluation on org_profile update (in OrgProfileService.update(); emits grant.eligibility.re_evaluated)

**Checkpoint**: User Stories 1 AND 2 both work independently. RAG retrieval layer is ready to support User Story 3.

---

## Phase 5: User Story 3 — Proposal Drafting Grounded in Org Docs (Priority: P1)

**Goal**: A nonprofit drafts a fundable proposal tailored to a specific grant, grounded in the org's own approved materials, with citations and eval-gated quality (relevance ≥ 0.85, faithfulness ≥ 0.90, SC-003) within 6 s p95 (SC-002).

**Independent Test**: A user can select a matched grant, trigger `POST /proposals/{id}/draft`, and receive a complete proposal with citations; eval-gate scores are visible; if eval fails, the proposal cannot transition to `ready_for_review`.

### Tests for User Story 3

- [ ] T088 [P] [US3] Contract test for POST /api/v1/proposals/{id}/draft in backend/tests/Contract/ProposalDraftTest.php
- [ ] T089 [P] [US3] Contract test for POST /api/v1/proposals/{id}/status in backend/tests/Contract/ProposalStatusTransitionTest.php
- [ ] T090 [P] [US3] Integration test for proposal draft happy path with citations in backend/tests/Feature/Proposal/DraftProposalTest.php
- [ ] T091 [P] [US3] Integration test for eval-gate blocking transition in backend/tests/Feature/Proposal/EvalGateBlocksTest.php
- [ ] T092 [P] [US3] Integration test for AI service unavailable (queue + retry) in backend/tests/Feature/Proposal/AIServiceUnavailableTest.php
- [ ] T093 [P] [US3] Load test for SC-002 p95 < 6 s budget in tests/load/draft.js (k6)
- [ ] T094 [P] [US3] Eval-gate threshold test in ai-service/tests/eval/test_thresholds.py (RAGAS + deepeval on frozen dataset; SC-003)
- [ ] T095 [P] [US3] E2E Playwright test for proposal drafting in frontend/e2e/us3-proposal-draft.spec.ts

### Implementation for User Story 3

- [ ] T096 [P] [US3] Create Domain entities Proposal, ProposalSection, ProposalSectionVersion, Citation in backend/app/Domain/Proposal/
- [ ] T097 [US3] Implement ProposalRepository (with append-only ProposalSectionVersion writes) in backend/app/Infrastructure/Persistence/
- [ ] T098 [US3] Implement ProposalService (CRUD, status transitions with eval-gate guard) in backend/app/Application/Proposal/ProposalService.php
- [ ] T099 [US3] Implement ProposalController (create, get, archive) in backend/app/Infrastructure/Http/Controllers/Proposal/
- [ ] T100 [US3] Implement DraftProposalController in backend/app/Infrastructure/Http/Controllers/Proposal/ (calls AIServiceClient.generate_proposal; persists sections + citations; records tokens + cost; emits proposal.draft.requested + proposal.draft.completed/failed)
- [ ] T101 [US3] Implement ProposalStatusController in backend/app/Infrastructure/Http/Controllers/Proposal/ (validates state machine; blocks drafting→ready_for_review unless eval_passed=true)
- [ ] T102 [P] [US3] Implement ai-service ModelRouter in ai-service/src/grantgenie_ai/generation/model_router.py (cost-aware, cheapest-adequate-first, fallback to higher tier; queues to Redis on exhaustion)
- [ ] T103 [P] [US3] Implement ai-service /generate/proposal endpoint in ai-service/src/grantgenie_ai/api/generation.py (calls RAG, builds prompt with citations, streams tokens, persists citations, returns ProposalDraftResponse)
- [ ] T104 [P] [US3] Implement ai-service prompt templates in ai-service/src/grantgenie_ai/generation/prompts/ (per section kind: summary, need_statement, activities, budget_narrative, impact; funder-tuning modifiers)
- [ ] T105 [P] [US3] Implement ai-service /eval/score endpoint in ai-service/src/grantgenie_ai/eval/scorer.py (RAGAS faithfulness + relevance; returns passed + failures)
- [ ] T106 [P] [US3] Implement ai-service /safety/check endpoint in ai-service/src/grantgenie_ai/safety/ (prompt-injection defense + PII redaction; input sanitization, output filtering)
- [ ] T107 [US3] Implement ProposalSection PATCH controller with edit-lock check in backend/app/Infrastructure/Http/Controllers/Proposal/SectionController.php
- [ ] T108 [US3] Implement Citation domain entity + repository + render in backend/app/Domain/Proposal/Citation.php and backend/app/Infrastructure/Persistence/CitationRepository.php
- [ ] T109 [P] [US3] Implement Angular Proposals feature in frontend/src/app/features/proposals/ (ProposalList, ProposalDetail with sections, DraftButton with streaming progress, CitationsPanel, EvalPanel, SectionEditor with Monaco)
- [ ] T110 [P] [US3] Implement Angular proposal status transition UI in frontend/src/app/features/proposals/components/status-transitions.component.ts
- [ ] T111 [US3] Emit domain events proposal.created, proposal.draft.requested, proposal.draft.completed, proposal.draft.failed, proposal.status.changed, proposal.section.updated
- [ ] T111a [US3] Implement draft autosave + session-resume flow per spec Edge Case (expired auth → save draft, prompt re-auth, resume): autosave ProposalSection content every 10 s to Redis draft buffer; on token refresh, rehydrate editor from last autosave; covered by E2E test frontend/e2e/us3-session-resume.spec.ts

**Checkpoint**: All P1 user stories functional; MVP demo ready end-to-end.

---

## Phase 6: User Story 4 — Budget Narrative Helper (Priority: P2)

**Goal**: A nonprofit generates a budget narrative that aligns with the proposal activities and funder guidelines.

**Independent Test**: A user can input budget line items and receive a narrative explanation for each, mapped to funder budget categories.

### Tests for User Story 4

- [ ] T112 [P] [US4] Contract test for POST ai-service /generate/budget-narrative in ai-service/tests/contract/test_budget_narrative.py
- [ ] T113 [P] [US4] Integration test for budget narrative generation in backend/tests/Feature/Proposal/BudgetNarrativeTest.php
- [ ] T114 [P] [US4] E2E Playwright test for budget narrative in frontend/e2e/us4-budget.spec.ts

### Implementation for User Story 4

- [ ] T115 [P] [US4] Create BudgetItem domain entity in backend/app/Domain/Proposal/BudgetItem.php
- [ ] T116 [US4] Implement BudgetItemRepository in backend/app/Infrastructure/Persistence/BudgetItemRepository.php
- [ ] T117 [US4] Implement BudgetNarrativeService in backend/app/Application/Proposal/BudgetNarrativeService.php (groups items by funder_category; calls AIServiceClient.generate_budget_narrative)
- [ ] T118 [US4] Implement BudgetItemController in backend/app/Infrastructure/Http/Controllers/Proposal/BudgetItemController.php
- [ ] T119 [P] [US4] Implement ai-service /generate/budget-narrative endpoint in ai-service/src/grantgenie_ai/api/budget_narrative.py (uses cheap tier; aligns with funder_categories; returns per-item narrative)
- [ ] T120 [P] [US4] Implement Angular budget editor in frontend/src/app/features/proposals/components/budget-editor.component.ts (line items table + Generate Narrative button + narrative preview)

**Checkpoint**: User Story 4 functional; proposal flow now includes budget support.

---

## Phase 7: User Story 5 — Deadline & Submission Tracker (Priority: P2)

**Goal**: A nonprofit tracks upcoming grant deadlines and submission status across multiple opportunities; receives in-app + email reminders at 14/7/1 days (spec clarification 2026-07-03).

**Independent Test**: A user can track a grant, advance time, and verify in-app + email reminders fire at each threshold; submission status updates correctly.

### Tests for User Story 5

- [ ] T121 [P] [US5] Contract test for deadline reminder delivery in backend/tests/Feature/Tracking/DeadlineReminderTest.php
- [ ] T122 [P] [US5] Integration test for reminder idempotency in backend/tests/Feature/Tracking/ReminderIdempotencyTest.php
- [ ] T123 [P] [US5] Integration test for submission lifecycle in backend/tests/Feature/Tracking/SubmissionLifecycleTest.php
- [ ] T124 [P] [US5] E2E Playwright test for tracking in frontend/e2e/us5-tracking.spec.ts (uses time-machine helper)

### Implementation for User Story 5

- [ ] T125 [P] [US5] Create Submission domain entity in backend/app/Domain/Tracking/Submission.php
- [ ] T126 [P] [US5] Create Notification + DeadlineReminder domain entities in backend/app/Domain/Tracking/
- [ ] T127 [US5] Implement SubmissionRepository + NotificationRepository + DeadlineReminderRepository in backend/app/Infrastructure/Persistence/
- [ ] T128 [US5] Implement TrackingService in backend/app/Application/Tracking/TrackingService.php (track/untrack grant; submission CRUD)
- [ ] T129 [US5] Implement DeadlineReminderScheduler k8s CronJob in infra/modules/cron/reminder-cronjob.yaml (daily 09:00 UTC) + backend/routes/console.php schedule
- [ ] T130 [US5] Implement ReminderDispatchService in backend/app/Application/Tracking/ReminderDispatchService.php (computes days_before for each tracked grant; idempotency check; creates Notification; dispatches via NotificationDispatcher; emits notification.dispatched)
- [ ] T131 [P] [US5] Implement NotificationDispatcher (in-app via WS push + email via SMTP) in backend/app/Infrastructure/Messaging/NotificationDispatcher.php
- [ ] T132 [US5] Implement TrackingController (list/create submissions, list notifications) in backend/app/Infrastructure/Http/Controllers/Tracking/
- [ ] T133 [P] [US5] Implement Angular tracking feature in frontend/src/app/features/tracking/ (SubmissionList, ReminderBadge, NotificationCenter, time-machine helper for tests)
- [ ] T134 [US5] Emit domain events submission.created, submission.status.changed, notification.dispatched, deadline.reminder.scheduled

**Checkpoint**: All P1 + P2 (except tailoring/review) functional.

---

## Phase 8: User Story 6 — Funder-Specific Tailoring (Priority: P3)

**Goal**: A proposal is automatically tailored to match the language, priorities, and format requirements of a specific funder.

**Independent Test**: The same org profile and content can produce two proposals for different funders with distinct language, structure, and emphasis.

### Tests for User Story 6

- [ ] T135 [P] [US6] Contract test for re-draft with different funder in backend/tests/Feature/Proposal/TailorProposalTest.php
- [ ] T136 [P] [US6] E2E Playwright test for funder tailoring in frontend/e2e/us6-tailoring.spec.ts

### Implementation for User Story 6

- [ ] T137 [US6] Implement FunderProfile extraction in ProposalService in backend/app/Application/Proposal/ProposalService.php (parses funder requirements; page_limit, section_order, formatting)
- [ ] T138 [US6] Implement FunderTailoringService in backend/app/Application/Proposal/FunderTailoringService.php (selects prompt modifiers per funder_profile; re-invokes /generate/proposal)
- [ ] T139 [US6] Add TailorForFunder endpoint in backend/app/Infrastructure/Http/Controllers/Proposal/DraftProposalController.php
- [ ] T140 [P] [US6] Implement Angular side-by-side diff viewer in frontend/src/app/features/proposals/components/diff-viewer.component.ts (left = original, right = tailored)
- [ ] T141 [P] [US6] Add funder-profile modifiers to ai-service prompt templates in ai-service/src/grantgenie_ai/generation/prompts/

**Checkpoint**: Tailoring functional; same proposal can be re-drafted for multiple funders.

---

## Phase 9: User Story 7 — Reviewer Workflow (Priority: P3)

**Goal**: A nonprofit can invite reviewers to comment on a proposal draft with author-held edit locks (spec clarification 2026-07-03); reviewers have read-only access.

**Independent Test**: A user can share a draft with a reviewer; reviewer adds comments; author sees them and can resolve; concurrent edits by a second author return 423 Locked.

### Tests for User Story 7

- [ ] T142 [P] [US7] Contract test for edit lock endpoints in backend/tests/Contract/EditLockTest.php
- [ ] T143 [P] [US7] Integration test for review comment lifecycle in backend/tests/Feature/Review/ReviewCommentTest.php
- [ ] T144 [P] [US7] Integration test for edit lock acquisition/concurrency in backend/tests/Feature/Review/EditLockConcurrencyTest.php
- [ ] T145 [P] [US7] E2E Playwright test for reviewer workflow in frontend/e2e/us7-review.spec.ts

### Implementation for User Story 7

- [ ] T146 [P] [US7] Create ReviewComment + EditLock domain entities in backend/app/Domain/Proposal/
- [ ] T147 [US7] Implement ReviewCommentRepository + EditLockRepository in backend/app/Infrastructure/Persistence/
- [ ] T148 [US7] Implement EditLockService in backend/app/Application/Proposal/EditLockService.php (Redis SET NX EX 1800; heartbeat refresh; release; concurrency-safe)
- [ ] T149 [US7] Implement ReviewService in backend/app/Application/Proposal/ReviewService.php (add comment, resolve comment, list comments)
- [ ] T150 [US7] Implement EditLockController (acquire, release) in backend/app/Infrastructure/Http/Controllers/Proposal/EditLockController.php
- [ ] T151 [US7] Implement ReviewCommentController (list, add, resolve) in backend/app/Infrastructure/Http/Controllers/Proposal/ReviewCommentController.php
- [ ] T152 [US7] Wire edit-lock check into SectionController PATCH (returns 423 when lock held by other user; see T107)
- [ ] T153 [P] [US7] Implement Angular review feature in frontend/src/app/features/review/ (ReviewerView read-only, CommentThread, ResolveAction, LockBanner with TTL countdown)
- [ ] T154 [P] [US7] Implement Angular admin invite-reviewer flow in frontend/src/app/features/admin/ (invite user with reviewer role; send magic link)
- [ ] T155 [US7] Emit domain events review.comment.added, review.comment.resolved, proposal.lock.acquired, proposal.lock.released

**Checkpoint**: All 7 user stories functional.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories; production readiness.

- [ ] T156 [P] Wire FinOps dashboard in infra/grafana/finops-dashboard.json (per-tenant cost; cost ceiling alerts; model-tier usage breakdown)
- [ ] T158 [P] Configure OIDC client in frontend (Auth0 integration; PKCE flow; silent refresh; role-claim mapping)
- [ ] T159 [P] Implement Idempotency-Key integration test sweep in backend/tests/Feature/Idempotency/ (proves all command endpoints are idempotent within 24h)
- [ ] T160 [P] Implement RFC 7807 problem-details integration test sweep in backend/tests/Feature/Errors/ (validates 400, 401, 403, 404, 409, 412, 413, 415, 422, 423, 429, 503 return correct problem+json shape)
- [ ] T161 [P] Add audit log write coverage tests in backend/tests/Feature/Audit/ (verifies every state-changing command writes before/after diff to audit_logs)
- [ ] T162 [P] Add prompt-injection red-team tests in ai-service/tests/safety/test_prompt_injection.py (corpus of injection attempts; asserts defense blocks them)
- [ ] T163 [P] Add PII redaction tests in ai-service/tests/safety/test_pii_redaction.py (emails, phone numbers, SSN, addresses; asserts redaction in safe_output)
- [ ] T164 [P] Performance tuning: PHP-FPM worker count, Postgres connection pool, Redis maxmemory, AI service uvicorn workers in infra/README.md
- [ ] T165 [P] Add OpenAPI spec validation in CI (schemathesis or swagger-cli) at .github/workflows/api-contract.yml
- [ ] T166 [P] Add architectural drift detection to nightly CI (PHPStan max, import-linter for AI service, eslint boundaries for Angular) at .github/workflows/architecture.yml
- [ ] T167 [P] Document on-call runbooks in infra/runbooks/ (AI service unavailability, RLS leak response, model provider outage, cost-spike response)
- [ ] T168 [P] Configure k8s HPA + GPU scale-to-zero for ai-service in infra/modules/aks/ai-deployment.yaml (KEDA scaler on Redis Stream lag)
- [ ] T169 [P] Add SLO dashboards in infra/grafana/slo-dashboard.json (SC-001, SC-002, SC-004 with error budgets)
- [ ] T170 Run quickstart.md end-to-end validation: make e2e-p1, make e2e-p2-p3, make validate-nfrs, make load-test-smoke; confirm all checkboxes pass

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately.
- **Foundational (Phase 2)**: Depends on Setup completion — **BLOCKS** all user stories.
- **User Stories (Phase 3–9)**: All depend on Foundational phase completion. Within priorities, US2 depends on US1 (uses OrgProfile + Tenant); US3 depends on US1 + US2 (uses Grants + RAG); US4–US7 depend on US3 (operate on Proposals).
- **Polish (Phase 10)**: Depends on all desired user stories being complete.

### User Story Dependencies

- **US1 (P1)**: Can start after Foundational. No dependencies on other stories.
- **US2 (P1)**: Can start after Foundational. Reuses Account/User from Foundational; OrgProfile entity independent.
- **US3 (P1)**: Can start after US1 + US2 (needs Grants + Boilerplate chunks for RAG).
- **US4 (P2)**: Can start after US3 (proposes belong to Proposals).
- **US5 (P2)**: Can start after US1 (Tracks Grants; reminders independent of Proposals).
- **US6 (P3)**: Can start after US3 (re-drafts Proposals).
- **US7 (P3)**: Can start after US3 (comments target Proposal sections).

### Within Each User Story

- Tests MUST be written and FAIL before implementation (constitution Principle III — TDD).
- Domain entities before repositories before services before controllers.
- Backend endpoint before frontend feature.
- Story complete before moving to next priority.

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel.
- All Foundational tasks marked [P] can run in parallel within Phase 2.
- Once Foundational phase completes:
  - US1 can start immediately.
  - US2 can start in parallel with US1 (different bounded contexts).
  - US3 starts after US1 + US2 are merged.
  - US4, US5, US6, US7 can start as soon as their US3 dependency lands.
- All tests for a user story marked [P] can run in parallel.
- Domain entities within a story marked [P] can run in parallel.
- Frontend and backend implementation within a story can run in parallel (with API contract as the boundary).

---

## Parallel Example: User Story 1

```bash
# Launch all tests for US1 together (TDD):
Task: "Contract test for POST /api/v1/discovery/search in backend/tests/Contract/DiscoverySearchTest.php"
Task: "Integration test for discovery happy path in backend/tests/Feature/Discovery/DiscoverGrantsTest.php"
Task: "Integration test for empty results in backend/tests/Feature/Discovery/EmptyResultsTest.php"
Task: "Integration test for cross-tenant isolation in backend/tests/Feature/Discovery/CrossTenantLeakTest.php"
Task: "Load test for SC-001 p95 < 5 s budget in tests/load/discovery.js"
Task: "E2E Playwright test for discovery search in frontend/e2e/us1-discovery.spec.ts"

# Launch all domain entities for US1 together:
Task: "Create Domain entity Grant + value objects in backend/app/Domain/Grant/"
Task: "Create Domain entity OrgProfile in backend/app/Domain/OrgProfile/"
Task: "Create Domain entity Account in backend/app/Domain/Identity/"

# Launch all ingestion adapters in parallel:
Task: "Implement GrantsGovAdapter in backend/app/Infrastructure/External/Ingestion/GrantsGovAdapter.php"
Task: "Implement CandidAdapter in backend/app/Infrastructure/External/Ingestion/CandidAdapter.php"
Task: "Implement InstrumentlAdapter in backend/app/Infrastructure/External/Ingestion/InstrumentlAdapter.php"

# Frontend and backend can be developed in parallel after API contract is stable.
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories).
3. Complete Phase 3: User Story 1.
4. **STOP and VALIDATE**: Test US1 independently (SC-001, SC-005).
5. Deploy/demo if ready.

### Incremental Delivery (P1 MVP)

1. Setup + Foundational.
2. US1 → test → deploy.
3. US2 → test → deploy (adds org profile + RAG ingest).
4. US3 → test → deploy (adds AI drafting with citations + eval gates).
5. **At this point: all P1 stories live; full P1 MVP deliverable end-to-end.**

### Incremental Delivery (P2/P3)

6. US4 (budget narrative) → test → deploy.
7. US5 (tracking + reminders) → test → deploy.
8. US6 (funder tailoring) → test → deploy.
9. US7 (reviewer workflow) → test → deploy.
10. Polish (Phase 10) → production ready.

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together.
2. After Foundational lands:
   - Developer A: US1
   - Developer B: US2 (parallel with A)
3. After US1 + US2:
   - Developer A: US3
   - Developer B: US4 (parallel; depends on US3 contracts but not implementation)
4. After US3 lands:
   - Developer A: US5
   - Developer B: US6
   - Developer C: US7

---

## Notes

- [P] tasks = different files, no dependencies.
- [Story] label maps task to specific user story for traceability.
- Each user story is independently completable and testable.
- Tests written first; verified failing before implementation (TDD).
- Commit after each task or logical group.
- Stop at any checkpoint to validate story independently.
- All 18 FRs and 7 SCs are covered; traceability verified in this file via Story labels and explicit SC/FR references in task descriptions.
