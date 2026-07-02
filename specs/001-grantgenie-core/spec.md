# Feature Specification: GrantGenie

**Feature Branch**: `001-grantgenie-core`

**Created**: 2026-07-01

**Status**: Draft

**Input**: User description: "Build GrantGenie — a grant-finder plus AI writer agent for small nonprofits, as described in PRD-18"

## Clarifications

### Session 2026-07-02

- Q: Do we need separate role-based permissions (admin, writer, reviewer, viewer) or is every user a single "org member" role? → A: Distinct roles - Admin, Writer, Reviewer, Viewer with RBAC.

### Session 2026-07-03

- Q: Grant corpus sources and refresh cadence? → A: Daily scheduled crawler (Grants.gov + 2-3 foundation directories), staleness tolerated up to 24h.
- Q: Eligibility scoring algorithm? → A: Boolean match — grant is either "eligible" or "not eligible" based on org profile fields vs. grant criteria.
- Q: AI model selection and fallback strategy? → A: Multi-model with cost-aware routing — cheapest adequate model first per task, with automatic fallback to higher-tier on failure.
- Q: Concurrent edit and reviewer conflict resolution? → A: Last-write-wins with edit-locking — author holds an exclusive edit lock on a proposal; reviewers can only add inline comments, not edit content.
- Q: Notification and reminder delivery channels and cadence? → A: In-app notification plus email at 14 days, 7 days, and 1 day before deadline (no SMS or push).

## User Scenarios & Testing *(mandatory)*

### Actors

- **Admin**: Manages org profile, invites users, configures tenant settings
- **Writer**: Creates and edits proposals, manages boilerplate library, runs discovery
- **Reviewer**: Views proposals assigned for review, adds inline comments (read-only on editing)
- **Viewer**: Reads proposals and submission status (read-only)

### User Story 1 - Grant Discovery & Eligibility Match (Priority: P1)

A nonprofit user (Writer) discovers relevant grant opportunities and checks eligibility without manually browsing multiple funder sites.

**Why this priority**: Discovery is the entry point — without knowing what grants exist, the rest of the system has no purpose. This is the highest-value slice.

**Independent Test**: A nonprofit user can search for grants by criteria (category, amount, deadline) and see a list of matched opportunities with eligibility indicators.

**Acceptance Scenarios**:

1. **Given** a registered nonprofit tenant with a completed org profile, **When** the user submits a DiscoverGrants request with search criteria, **Then** the system returns a paginated list of grants that have passed the boolean eligibility check, each marked "eligible" or "not eligible" with the rule(s) that determined the result.
2. **Given** a grant discovery request with no matching results, **When** processed, **Then** the system returns an empty results list with suggestions to broaden criteria.
3. **Given** a user from tenant A submits a grant discovery request, **When** results are returned, **Then** they include only grants visible to tenant A (no cross-tenant data leak).

---

### User Story 2 - Org Profile & Boilerplate Library (Priority: P1)

A nonprofit builds and maintains a profile with mission, boilerplate text, past proposals, and supporting documents to ground all future proposal drafts.

**Why this priority**: The org knowledge base is essential grounding material for AI-generated proposals. Without it, drafts would be generic and unfundable.

**Independent Test**: A user can create an org profile, upload boilerplate documents, and retrieve them in a query.

**Acceptance Scenarios**:

1. **Given** a registered nonprofit tenant, **When** the user creates an org profile with mission, history, and key programs, **Then** the profile is persisted and retrievable.
2. **Given** an existing org profile, **When** the user uploads a PDF or document to the boilerplate library, **Then** it is chunked, embedded, and stored for semantic retrieval.
3. **Given** an org profile with multiple documents, **When** a query is made for a specific topic, **Then** the RAG layer returns the most relevant chunks with source citations.

---

### User Story 3 - Proposal Drafting Grounded in Org Docs (Priority: P1)

A nonprofit drafts a fundable proposal tailored to a specific grant opportunity, grounded in the org's own approved materials.

**Why this priority**: Drafting is the core value proposition. This is the feature that saves the most time and directly drives funding.

**Independent Test**: A user can select a matched grant, trigger a proposal draft, and receive a complete proposal document with citations to source materials.

**Acceptance Scenarios**:

1. **Given** a matched grant and an org profile with boilerplate, **When** the user triggers DraftProposal, **Then** a complete proposal draft is generated with sections (summary, need statement, activities, budget narrative, impact).
2. **Given** a drafted proposal, **When** inspected, **Then** each claim or data point includes a citation to the source org document.
3. **Given** an AI-generated proposal draft, **When** evaluated, **Then** it passes the eval thresholds (relevance >= 0.85, faithfulness >= 0.90) before being surfaced.

---

### User Story 4 - Budget Narrative Helper (Priority: P2)

A nonprofit generates a budget narrative that aligns with the proposal activities and funder guidelines.

**Why this priority**: Budget narratives are time-consuming and often a barrier to submission, but secondary to the main draft.

**Independent Test**: A user can input budget line items and receive a narrative explanation for each.

**Acceptance Scenarios**:

1. **Given** a proposal draft with identified activities, **When** the user submits budget items, **Then** the system generates a narrative explaining each cost in the context of the proposal.
2. **Given** a budget narrative, **When** reviewed, **Then** it reflects the funder's budget categories and restrictions.

---

### User Story 5 - Deadline & Submission Tracker (Priority: P2)

A nonprofit tracks upcoming grant deadlines and submission status across multiple opportunities.

**Why this priority**: Tracking is necessary for production use but does not require AI — standard CRUD with notifications.

**Independent Test**: A user can add a grant deadline, receive reminders, and mark submissions as submitted.

**Acceptance Scenarios**:

1. **Given** a tracked grant opportunity with a deadline, **When** the deadline approaches (14 days, 7 days, and 1 day before), **Then** the system sends an in-app notification and an email reminder.
2. **Given** a submitted proposal, **When** the user marks it as submitted, **Then** the submission status updates and the award outcome can be logged.

---

### User Story 6 - Funder-Specific Tailoring (Priority: P3)

A proposal is automatically tailored to match the language, priorities, and format requirements of a specific funder.

**Why this priority**: Tailoring increases win rate but can be deferred until core drafting is solid.

**Independent Test**: The same org profile and grant can produce two proposals for different funders with distinct language and emphasis.

**Acceptance Scenarios**:

1. **Given** a proposal draft for funder A, **When** the same content is drafted for funder B, **Then** the language, emphasis, and structure differ to match each funder's guidelines.
2. **Given** funder-specific requirements (page limits, section order, formatting), **When** a proposal is generated, **Then** it conforms to those requirements.

---

### User Story 7 - Reviewer Workflow (Priority: P3)

A nonprofit can invite reviewers to comment on a proposal draft before final submission.

**Why this priority**: Collaboration improves quality but is a team feature, not a solo-user feature.

**Independent Test**: A user can share a proposal draft with a reviewer, the reviewer can add comments, and the author can see and respond to them.

**Acceptance Scenarios**:

1. **Given** a proposal draft in review status, **When** the author invites a reviewer, **Then** the reviewer receives access and can view the draft.
2. **Given** a reviewer with access to a draft, **When** they add inline comments, **Then** the author sees the comments and can resolve them.

### Edge Cases

- What happens when a user searches for grants with no org profile completed? — Prompt to complete profile first, offer guided setup.
- How does the system handle a grant corpus refresh mid-session? — Graceful staleness: show last-known results with a refresh banner.
- What happens when the AI service is unavailable during proposal drafting? — Cost-aware router retries on next-cheapest adequate model, then queues the request, notifies the user, and completes asynchronously when a model is available.
- How does the system handle uploading an unsupported document format? — Reject with supported format list (PDF, DOCX, TXT, MD).
- How does the system handle a user with expired authentication session during a long drafting session? — Save draft progress, prompt re-auth, resume on return.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow nonprofit tenants to register and manage their account with multi-tenant isolation.
- **FR-002**: System MUST allow users to build and maintain an org profile including mission, history, programs, and boilerplate documents.
- **FR-003**: System MUST ingest grant opportunities from a daily scheduled crawler covering Grants.gov and 2-3 foundation directories into a searchable corpus, with staleness tolerated up to 24h and a visible "last refreshed" indicator on results.
- **FR-004**: System MUST match grants to org profiles using a boolean eligibility check — each grant is either "eligible" or "not eligible" based on org profile fields (mission, programs, service area, organization type) compared against the grant's eligibility rules, and only eligible grants are returned in results.
- **FR-005**: System MUST generate proposal drafts grounded in org documents with citations, via AI.
- **FR-006**: System MUST allow users to review, edit, and approve AI-generated proposal drafts before finalization.
- **FR-007**: System MUST support a budget narrative helper that explains line items in the context of proposal activities.
- **FR-008**: System MUST allow users to track grant deadlines, send in-app and email reminders at 14, 7, and 1 days before each deadline, and log submission status.
- **FR-009**: System MUST allow funder-specific tailoring of proposal language and format.
- **FR-010**: System MUST support a reviewer workflow with inline commenting and resolution, where reviewers have read-only access to proposal content and authors hold an exclusive edit lock to prevent concurrent conflicting edits.
- **FR-011**: System MUST log every state-changing command with before/after diff in an append-only audit log.
- **FR-012**: System MUST enforce tenant-level row security on all data access.
- **FR-013**: System MUST apply prompt-injection defense and PII redaction to all AI inputs and outputs.
- **FR-014**: System MUST cache idempotent query results to meet performance budgets.
- **FR-015**: System MUST emit structured JSON logs with correlation IDs, rotated daily.
- **FR-016**: System MUST support idempotency keys on all command endpoints.
- **FR-017**: System MUST return RFC 7807 problem-details on API errors.
- **FR-018**: System MUST enforce role-based access control with four roles — Admin, Writer, Reviewer, Viewer — scoped per tenant.

### Key Entities

- **Grant**: A funding opportunity with criteria, deadline, amount, funder, and status.
- **OrgProfile**: The nonprofit tenant's identity — mission, history, programs, service area.
- **BoilerplateDocument**: An uploaded document chunked and embedded for RAG retrieval.
- **Proposal**: An AI-generated draft with sections, citations, status, and version history.
- **Submission**: A proposal submitted to a funder with deadline, status, and outcome.
- **Account**: The nonprofit tenant — owns all data, enforces isolation.
- **ReviewComment**: An inline comment on a proposal section from a reviewer.
- **OutboxMessage**: Reliable event publication via transactional outbox.
- **AuditLog**: Append-only record of security-relevant and state-changing actions.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A nonprofit can complete grant discovery and receive matched results in under 5 seconds (p95).
- **SC-002**: A proposal draft with citations is generated in under 6 seconds (p95) from trigger to complete.
- **SC-003**: AI-generated proposals pass automated eval thresholds (relevance >= 0.85, faithfulness >= 0.90) before surfacing.
- **SC-004**: The system maintains 99.9% API availability for read operations.
- **SC-005**: No cross-tenant data leak occurs under automated isolation testing.
- **SC-006**: Structured JSON logs are written per service, per day, with 100% of requests carrying correlation IDs.
- **SC-007**: A new tenant can complete setup (register + build org profile) in under 10 minutes.

## Assumptions

- Users have stable internet connectivity (cloud SaaS).
- Mobile support is out of scope for MVP — responsive web/PWA only.
- Grant corpus is sourced from public grant databases and scheduled crawlers (Grants.gov, foundation directories).
- AI model providers (OpenAI, Anthropic, open models) are available with acceptable latency and cost; a cost-aware router selects the cheapest adequate model per task and falls back to a higher-tier model on failure.
- Each nonprofit tenant has at least one administrator who manages the org profile.
- Users are comfortable with AI-generated drafts requiring review and editing before submission.
- Authentication uses OIDC/OAuth2 (social login or email/password via a provider like Auth0).
- Document uploads are limited to PDF, DOCX, TXT, and Markdown formats.
- Performance budgets assume a cloud deployment with adequate resources.
