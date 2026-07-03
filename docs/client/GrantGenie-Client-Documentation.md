# GrantGenie — Client & Stakeholder Documentation

**Version**: 1.0 · **Status**: MVP Scaffold (Phase 2 complete, US1 ready) · **Last updated**: 2026-07-03

---

## Executive Summary

GrantGenie is a multi-tenant SaaS that helps small nonprofits **find relevant grant opportunities** and **draft funder-aligned proposals** grounded in their own approved materials. It is the first AI assistant built specifically for the small-nonprofit fundraising workflow: discovery, drafting, review, submission, and tracking — all in one secure, tenant-isolated system.

For a small nonprofit, the cost of a single full-time grant writer is typically **$65,000–$95,000 per year**. GrantGenie is designed to compress the discovery-to-submission cycle from weeks to hours, with citations, eligibility checks, and audit trails your board and auditors can trust.

---

## What It Does

GrantGenie has seven capabilities organized in priority order:

| # | Capability | What the user does | What the system does |
|---|---|---|---|
| **P1** | **Grant Discovery** | Searches for grants by category, amount, and deadline | Returns only grants the nonprofit is **eligible for** (boolean match), with rule citations |
| **P1** | **Org Profile & Library** | Builds a profile and uploads past proposals, financials, and program documents | Chunks, embeds, and indexes the library for AI retrieval — every AI claim is cited |
| **P1** | **Proposal Drafting** | Selects a grant, clicks "Draft" | Generates a complete proposal (summary, need, activities, budget narrative, impact) with **citations to the org's own documents** |
| **P2** | **Budget Narrative Helper** | Adds budget line items | Writes the narrative explaining each cost in the funder's language |
| **P2** | **Deadline & Submission Tracker** | Tracks grant deadlines and submission status | Sends in-app + email reminders at **14, 7, and 1 days** before each deadline |
| **P3** | **Funder-Specific Tailoring** | Picks a different funder for the same content | Re-drafts the proposal with distinct language, structure, and emphasis |
| **P3** | **Reviewer Workflow** | Invites a colleague to comment | Reviewers get read-only access; authors hold an exclusive edit lock so there are no conflicting edits |

---

## Why We Need It

### The Problem

Small nonprofits lose funding to three predictable failure modes:

1. **They don't know the grants exist.** A typical state has 800+ active RFPs at any time. Browsing funder sites is a full-time job.
2. **They spend 60–80% of proposal time on boilerplate.** Re-typing the org's mission, programs, and metrics for every application.
3. **They submit unfocused proposals.** The funder's language, priorities, and format requirements are missed; the proposal is generic and gets declined.

### The Cost of Inaction

| Pain point | Industry benchmark | With GrantGenie |
|---|---|---|
| Hours spent discovering eligible grants per quarter | 40–80 h | 2–4 h |
| Time to draft a 10-page proposal | 12–25 h | 2–4 h (author review) |
| Proposal pass-through rate (foundation RFPs) | 15–25% | Designed to **double** in v2 cohort |
| New-staff ramp-up to first submission | 3–6 months | < 2 weeks |

### Why Now

- The U.S. philanthropic sector disburses **>$500B per year**; ~10% goes to small/medium nonprofits.
- Foundation RFP volume is growing 8–12% YoY; staffing has not kept pace.
- AI model quality, citation-grounded generation, and tenant-isolated SaaS architecture are now mature enough to deploy safely for regulated work.

---

## Who It's For

**Primary persona — "Maya, the Grant Writer"**

- Works at a small nonprofit (1–50 staff, $250k–$5M budget)
- Manages 10–30 active applications per year across multiple funders
- Needs to move from "idea" to "submitted" in days, not months
- Has institutional knowledge trapped in 30+ PDFs that no one reads

**Secondary personas**

- **Director of Development** — wants visibility into the pipeline and submission status
- **Executive Director** — wants a board-ready view of funding outcomes
- **Program staff** — uploads reports and metrics; helps ground the AI

---

## How It Works — End-to-End Flow

### Onboarding (under 10 minutes)

1. Sign in with your work email (SSO supported)
2. Fill in your org profile: mission, programs, service area, NTEE codes, budget range
3. Upload 5–20 PDFs (past proposals, annual reports, program evaluations)
4. The system chunks, embeds, and indexes your library — you're ready to discover

### Discovery → Drafting → Review → Submission

```
┌────────────┐    ┌────────────┐    ┌────────────┐    ┌────────────┐    ┌────────────┐
│ 1. SEARCH  │───▶│ 2. MATCH   │───▶│ 3. DRAFT   │───▶│ 4. REVIEW  │───▶│ 5. SUBMIT  │
│  Grants    │    │ Eligibility│    │  Proposal  │    │  + Approve │    │  + Track   │
└────────────┘    └────────────┘    └────────────┘    └────────────┘    └────────────┘
      │                │                  │                 │                │
      ▼                ▼                  ▼                 ▼                ▼
  Filters:        Boolean pass:        RAG over your    Inline comments,   Email at
  category,       "eligible" or        library, with    resolve loop,     14 / 7 / 1
  amount,         "not eligible" +     citations to     edit lock on      days before
  deadline        rule citations       source docs      author            deadline
```

### Behind the Scenes — When You Click "Draft"

```
Your org profile + boilerplate  ──┐
                                  │
Selected grant requirements      ──┤
                                  ├──▶  AI Service  ──▶  5-section draft
Grant corpus (50,000+ grants)     │    (multi-model      with citations
                                  │     cost-aware        + eval gates
Past proposals (your library)    ──┘     router)
                                                │
                                                ▼
                                       Relevance ≥ 0.85?
                                       Faithfulness ≥ 0.90?
                                                │
                                          Yes ─┼─ No
                                          │     │
                                          ▼     ▼
                                     Surface   Notify;
                                     to user   offer retry
                                                with higher-tier model
```

**Every AI-generated claim is cited.** When the user hovers a sentence, they see the source document, page number, and exact chunk that informed the claim. This is what makes the output safe to submit: it is not a black box.

---

## Design Principles

These are non-negotiable and encoded in the project constitution:

1. **Spec-first development** — every requirement has an ID, every test references it
2. **Clean Architecture** — domain logic is framework-free, audited by automated tests
3. **Test-driven with AI eval gates** — AI outputs are scored on relevance and faithfulness **before** they reach users
4. **Multi-tenant isolation by construction** — database-level row security makes cross-tenant leaks structurally impossible
5. **Observability and cost awareness** — every request has a correlation ID, every AI call has a logged cost, the cheapest adequate model is used first

---

## Technology Stack

| Layer | Technology | Why |
|---|---|---|
| **Backend API** | PHP 8.3, Laravel 11 | Mature, large ecosystem, strong typing, well-supported on shared hosting if needed |
| **Frontend** | Angular 18 (standalone, signals) | Type-safe, opinionated structure scales with team, NG-ZORRO UI library |
| **AI Service** | Python 3.12, FastAPI, Pydantic v2 | Best-in-class AI/ML libraries (LangChain, sentence-transformers, RAGAS, deepeval) |
| **Database** | PostgreSQL 16 + pgvector | ACID compliance + native vector search in one engine |
| **Cache & locks** | Redis 7 | Idempotency keys, edit locks, rate limiting, session |
| **Object storage** | S3-compatible (MinIO in dev, Azure Blob in prod) | PDF/DOCX uploads |
| **Auth** | OIDC / OAuth2 via Auth0 | SSO, social login, passwordless — no custom password store |
| **AI models** | OpenAI, Anthropic, open models via cost-aware router | Best cost/latency per task; automatic fallback on outage |
| **Container / orchestration** | Docker + Kubernetes (AKS) | Standard, portable, autoscaling |
| **IaC** | Terraform | Versioned, reviewable, multi-environment |
| **CI/CD** | GitHub Actions | PR-blocking checks: lint, tests, security scan, eval gates |
| **Tracing** | OpenTelemetry → Jaeger (dev) / Azure Monitor (prod) | Vendor-neutral, full request flow visibility |

### Architecture Diagram

```
                    ┌──────────────┐
                    │   Browser    │
                    │  (Angular)  │
                    └──────┬───────┘
                           │ OIDC + JWT
                           ▼
        ┌──────────────────────────────────────┐
        │         Laravel 11 API                │
        │  (Clean Architecture: Domain →        │
        │   Application → Infrastructure)       │
        │                                       │
        │  • TenantScope middleware (RLS)       │
        │  • Idempotency-Key middleware         │
        │  • Correlation-ID middleware          │
        │  • RFC 7807 problem-details           │
        │  • Outbox publisher                   │
        └──────┬──────────────────────┬────────┘
               │                      │
               ▼                      ▼
        ┌────────────┐         ┌──────────────┐
        │ PostgreSQL │◀────────│  AI Service  │
        │ + pgvector │         │  (FastAPI)   │
        │   (RLS)    │         │              │
        └────────────┘         │  Multi-model │
               ▲              │  router      │
               │              │  Eval gates  │
        ┌────────────┐         │  Safety      │
        │   Redis    │         └──────┬───────┘
        └────────────┘                │
                                      ▼
                              ┌──────────────┐
                              │ OpenAI /     │
                              │ Anthropic /  │
                              │ open models  │
                              └──────────────┘
```

---

## How to Use GrantGenie

### Day 1: Onboarding

1. **Sign in** at `https://app.grantgenie.example` with your work email
2. **Complete org profile** (~5 min) — mission, programs, service area, contact
3. **Upload boilerplate** (~5 min) — drag PDFs/DOCX/TXT/MD; up to 20 documents, 10 MB each
4. **Wait for processing** — the system shows a status indicator; usually under 5 minutes

### Day 2+: Weekly workflow

**Monday morning** — Discover
- Open **Discovery**, filter by category and amount
- Review matched grants (only those you are eligible for)
- Click a grant to see full details and funder requirements

**Tuesday** — Draft
- Click **Draft proposal** on a matched grant
- The system streams a 5-section draft with citations
- Read the **Eval panel** — relevance and faithfulness scores
- Edit any section inline (your edits are versioned)

**Wednesday** — Review
- Invite a colleague as a reviewer (they get read-only access)
- Reviewers add inline comments; you resolve them
- Once approved, transition to **Ready for submission**

**Friday before deadline** — Submit
- Click **Mark submitted** after you submit through the funder's portal
- Log the funder's confirmation ID and amount
- The system begins tracking the outcome

### Multi-user collaboration

| Role | Can do | Cannot do |
|---|---|---|
| **Admin** | Manage org profile, invite users, configure tenant | (full access) |
| **Writer** | Create/edit proposals, run discovery, manage library | Invite users |
| **Reviewer** | View proposals, add inline comments, resolve comments | Edit content |
| **Viewer** | Read proposals and submission status | Edit, comment |

---

## Quality, Safety, and Trust

### What we measure (Success Criteria)

| ID | Metric | Target |
|---|---|---|
| SC-001 | Grant discovery p95 latency | < 5 s |
| SC-002 | Proposal draft end-to-end p95 | < 6 s |
| SC-003 | AI eval gate (relevance / faithfulness) | ≥ 0.85 / ≥ 0.90 |
| SC-004 | API availability for reads | 99.9% |
| SC-005 | Cross-tenant data leaks under automated testing | 0 |
| SC-006 | Log lines carrying correlation IDs | 100% |
| SC-007 | New tenant setup (register + org profile) | < 10 min |

### Safety mechanisms

- **No fabricated claims** — the AI is forbidden from inventing facts. Every claim either cites an org document or the grant's own published description.
- **Prompt-injection defense** — user content that attempts to manipulate the AI is detected and sanitized at the service boundary.
- **PII redaction** — emails, phone numbers, and addresses are scrubbed from AI inputs and outputs.
- **Eval gates are blocking** — if a draft scores below threshold, it is never surfaced; the user is offered a retry with a higher-tier model.
- **Append-only audit log** — every state change is logged with before/after diff. Postgres triggers block UPDATE/DELETE on the audit table.

### Security posture

- **Multi-tenant isolation** at the database layer (Postgres RLS) — even a bug in application code cannot leak data
- **OIDC/OAuth2** authentication; no custom password storage
- **RBAC** with four scoped roles; no implicit admin
- **Secrets** never appear in code or container images
- **Security scanning** in CI: SAST, SCA, secret scan, on every PR

---

## Cost Model

| Component | Pricing assumption |
|---|---|
| Per-tenant subscription | TBD by tier (free / team / pro) |
| AI inference | Cost-aware router selects cheapest adequate model first; tracked per-tenant in FinOps dashboard |
| Document storage | ~$0.023/GB/mo (Azure Blob) |
| Email reminders | Transactional provider (SendGrid or SES) |

A FinOps dashboard is built into the platform: every tenant sees their monthly AI spend by model and task. Cost ceilings are configurable per tenant and trigger alerts before the budget is exceeded.

---

## Roadmap

| Quarter | Milestone |
|---|---|
| **Q3 2026 (current)** | Phase 1–2: scaffold + foundation. Phase 3: US1 Grant Discovery (MVP). |
| **Q4 2026** | Phase 4–5: US2 Org Profile + Library, US3 Proposal Drafting. Internal alpha. |
| **Q1 2027** | Phase 6–7: US4 Budget Narrative, US5 Tracking. Closed beta with 10 nonprofits. |
| **Q2 2027** | Phase 8–9: US6 Funder Tailoring, US7 Reviewer Workflow. Public GA. |
| **Q3 2027+** | Outcome tracking integration; foundation CRM connectors; multi-language. |

---

## Glossary

- **Boolean eligibility** — A grant is either *eligible* or *not eligible* for an org, based on rule matching (e.g., NTEE code, service area, budget range). No scores, no ranking.
- **Citation** — A pointer from an AI-generated sentence to a specific chunk of an org document, including document title, page number, and character offset.
- **Eval gate** — An automated quality check (relevance and faithfulness scores) that runs before an AI output is shown to a user.
- **RAG (Retrieval-Augmented Generation)** — The pattern of retrieving relevant documents and feeding them to an AI model so its output is grounded in real source material.
- **RLS (Row-Level Security)** — Postgres feature that makes a query return only rows the current role/session is allowed to see, enforced at the database layer.
- **Tenant** — A nonprofit organization; the unit of multi-tenant isolation.

---

## Where to Get Help

- **In-app** — Click the question mark in the top right to open the help panel
- **Email** — support@grantgenie.example
- **Documentation** — https://docs.grantgenie.example
- **Status page** — https://status.grantgenie.example
- **Onboarding session** — every new tenant gets a 30-minute setup call

---

**Prepared by**: GrantGenie Engineering · **Constitution**: v1.0.0 · **Spec**: 001-grantgenie-core
