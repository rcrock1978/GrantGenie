# GrantGenie — Client Presentation

**Total slides**: 15 · **Target duration**: 20–25 min talk + 10 min Q&A · **Style**: modern, minimal, generous whitespace, dark accent on light background

> **Design tokens**
> - Primary: deep indigo `#3D2EA0`
> - Accent: warm coral `#FF6B5C`
> - Success: green `#22C55E`
> - Background: warm white `#FAFAF7`
> - Text: near-black `#0F172A`
> - Muted: `#64748B`
> - Type: Inter (titles), Source Sans 3 (body), JetBrains Mono (code/IDs)
> - Slide size: 16:9 (1920×1080)

---

## Slide 1 — Title

**Headline**: *GrantGenie*
**Subhead**: AI-assisted grant discovery and drafting for small nonprofits
**Right column**:
- 1-line value prop
- "v1.0 · July 2026"
- Logo placeholder (top-left)
**Footer**: presenter name, role, date

> **Visual direction**: full-bleed indigo gradient; bold wordmark in white at left third; the right two-thirds is empty with a single thin coral line as a graphic accent.

---

## Slide 2 — The Problem

**Headline**: *Small nonprofits lose funding to three predictable failure modes.*

**Three numbered tiles** (1.5×1.5 grid):
1. **They don't know the grants exist.** A typical state has 800+ active RFPs at any time.
2. **They spend 80% of proposal time on boilerplate.** Re-typing the org's mission, programs, and metrics for every application.
3. **They submit unfocused proposals.** Missed funder language, missed format, missed priorities.

**Right column stat**:
- 60–80 hours spent per quarter on discovery alone
- 12–25 hours to draft a single 10-page proposal
- 15–25% proposal pass-through rate (industry benchmark)

---

## Slide 3 — Why Now

**Headline**: *The philanthropic sector is growing; staffing is not.*

**Three KPI tiles**:
- **$500B+** — U.S. philanthropic disbursement per year
- **+8–12% YoY** — RFP volume growth
- **~10%** — Share flowing to small/medium nonprofits

**Right column** (single sentence each):
- AI model quality, citation-grounded generation, and tenant-isolated SaaS are now mature enough for regulated work
- Cloud + OpenTelemetry + eval-gated LLMs are the new normal
- A new "AI for good" SaaS category is opening up

---

## Slide 4 — Meet Maya

**Headline**: *Maya is our primary user.*

**Two-column split**:
- **Left**: 1-paragraph persona card
  - Grant writer at a small nonprofit
  - 1–50 staff, $250k–$5M budget
  - 10–30 active applications per year
  - 30+ PDFs of institutional knowledge, no one reads them
- **Right**: Stylized "day in the life" timeline (M–F)
  - M: discovery
  - T: drafting
  - W: review with ED
  - Th: revisions
  - F: submit, log

> **Visual direction**: muted color illustration; warm tones; not stock-photo.

---

## Slide 5 — Solution

**Headline**: *One platform. Five steps. Cited at every step.*

**Single horizontal flow diagram** with 5 rounded nodes, each in the primary indigo with a coral dot:

```
Discover → Match → Draft → Review → Submit + Track
```

**Caption under diagram**:
> Search only eligible grants. Get a full draft in 6 seconds with citations. Collaborate with reviewers. Track every deadline. All in one place.

---

## Slide 6 — How It Works

**Headline**: *Behind the scenes — when you click "Draft".*

**Two-column layout**:
- **Left**: numbered bullets
  1. Your org profile + boilerplate + selected grant → AI Service
  2. Multi-model cost-aware router picks the cheapest adequate model
  3. RAG retrieves your top-5 relevant chunks; reranks to top-3
  4. LLM streams a 5-section draft with citations per claim
  5. Eval gates (relevance ≥ 0.85, faithfulness ≥ 0.90) block low-quality output
- **Right**: simple architecture diagram (3 boxes, 1 user icon, 1 DB icon)

> **Visual direction**: line-art diagram, no gradients, single accent color used sparingly.

---

## Slide 7 — Trust & Safety

**Headline**: *Built for the work that gets audited.*

**Three trust pillars** (3-column layout):

| Every claim cited | Eval-gated quality | Audit-ready logs |
|---|---|---|
| Hover any AI sentence → see source document, page, and chunk | Relevance ≥ 0.85 and faithfulness ≥ 0.90 required to surface | Append-only audit log with before/after diff; Postgres triggers block any change |

**Bottom strip**: a single line — "Multi-tenant isolation at the database layer makes cross-tenant data leaks structurally impossible."

---

## Slide 8 — User Experience

**Headline**: *A working session in 60 seconds.*

**Three annotated screenshots** (Discovery → Drafting → Tracking), each ⅓ width, with one-line caption below:

1. **Discovery**: "Only eligible grants shown. 14 results in 3.2s."
2. **Drafting**: "5-section draft, citations inline, eval scores visible."
3. **Tracking**: "Email + in-app reminders at 14, 7, 1 days."

---

## Slide 9 — Design Principles

**Headline**: *Our constitution.*

**5 numbered principles** (vertical list, large numerals):
1. **Spec-first development** — every requirement has an ID, every test references it
2. **Clean Architecture** — domain logic is framework-free, audited by automated tests
3. **Test-driven with AI eval gates** — AI outputs scored before they reach users
4. **Multi-tenant isolation by construction** — database-level RLS
5. **Observability and cost awareness** — every request has a correlation ID; cheapest adequate model first

---

## Slide 10 — Technology Stack

**Headline**: *Standard, modern, mature.*

**Layered table** (alternating row backgrounds):

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 11 (Clean Architecture) |
| Frontend | Angular 18 (standalone, signals), NG-ZORRO |
| AI Service | Python 3.12, FastAPI, Pydantic v2 |
| Database | PostgreSQL 16 + pgvector |
| Cache & locks | Redis 7 |
| Object storage | S3-compatible (MinIO dev / Azure Blob prod) |
| Auth | OIDC / OAuth2 (Auth0) |
| AI models | OpenAI, Anthropic, open models (cost-aware router) |
| Container / orch | Docker + Kubernetes (AKS) |
| IaC | Terraform |
| CI/CD | GitHub Actions (lint, tests, SAST, SCA, secrets, eval gates) |
| Tracing | OpenTelemetry → Jaeger / Azure Monitor |

---

## Slide 11 — Success Metrics

**Headline**: *What we measure.*

**4-up grid of success criteria** (large numerals + label + target):

| SC-001 | SC-002 | SC-003 | SC-004 |
|---|---|---|---|
| **< 5s** | **< 6s** | **0.85 / 0.90** | **99.9%** |
| Discovery p95 | Draft p95 | AI relevance / faithfulness | API availability (reads) |

**Two more KPIs in a smaller row**:
- **SC-005** — 0 cross-tenant data leaks (under automated testing)
- **SC-007** — < 10 min to set up a new tenant

---

## Slide 12 — Cost & Business Model

**Headline**: *AI costs are first-class.*

**Two-column layout**:
- **Left**: Pricing assumption tiers (3 rows)
  - **Free** — discovery + 5 boilerplate docs
  - **Team** — $99/mo + AI pass-through (most tenants)
  - **Pro** — $499/mo + dedicated model tier + custom funder rules
- **Right**: Cost-control guarantees
  - Cost-aware router: cheapest adequate model first
  - Per-tenant FinOps dashboard with cost ceilings and alerts
  - GPU scale-to-zero when AI service is idle
  - 24h on-demand model fallback (no vendor lock-in)

---

## Slide 13 — Roadmap

**Headline**: *Where we're going.*

**Horizontal timeline** (4 quarters, 2 years):

| Q3 2026 (now) | Q4 2026 | Q1 2027 | Q2 2027 |
|---|---|---|---|
| **Scaffold + Foundation** | **Org Profile + Library, Proposal Drafting** | **Budget Narrative, Tracking; closed beta with 10 nonprofits** | **Funder Tailoring, Reviewer Workflow; public GA** |

**Below the timeline**:
- Q3 2027+: outcome tracking, foundation CRM connectors, multi-language

---

## Slide 14 — The Ask

**Headline**: *What we need from you.*

**3 bulleted asks** (large, bold):
1. **10 design partners** for the closed beta (Q1 2027) — your team, your RFPs, your feedback
2. **Reference call** — introduce us to a foundation program officer or nonprofit ED
3. **Co-marketing** — case study + a quote for the public launch (Q2 2027)

**Right column**: small contact card (name, role, email, calendar link)

---

## Slide 15 — Closing

**Headline**: *Fund the missions that fund us all.*

**Single line below**:
> GrantGenie turns weeks of proposal work into hours — with citations your board can trust.

**Visual**: full-bleed indigo gradient; the headline in white; "GrantGenie" wordmark; small footer with URL + handle.

**Below the line** (small): Q&A invitation

---

## Speaker notes (per slide)

| Slide | Duration | Key talking points |
|---|---|---|
| 1 | 30 s | Warm welcome. "Today I'll walk you through why we built GrantGenie, how it works, and what we need from you." |
| 2 | 90 s | Tell the three failure modes as a story. "Maya loses 60 hours per quarter on discovery alone." |
| 3 | 60 s | Anchor on $500B. "This is not a small market. The bottleneck is capacity, not money." |
| 4 | 60 s | Make Maya real. "She's been at this org for 4 years. She knows every funder by first name." |
| 5 | 60 s | Walk the diagram slowly. "Every step has a citation. Every step has an audit trail." |
| 6 | 90 s | This is the technical credibility slide. "We chose Postgres RLS over schema-per-tenant because…" |
| 7 | 60 s | Trust. "The biggest reason nonprofits don't adopt AI is fear of fabrication. Citations solve that." |
| 8 | 60 s | The screenshots do the selling. Pause on each one. "This is what Maya sees on Tuesday." |
| 9 | 45 s | Don't read the slide. "We have five principles. We violate none of them. We have a constitution." |
| 10 | 60 s | "Standard, modern, mature. We are not inventing new technology; we are applying it carefully." |
| 11 | 45 s | "These are the four metrics our board will see every month." |
| 12 | 60 s | "AI costs are a first-class concern. We track every token, every model, every tenant." |
| 13 | 60 s | "We're not promising everything. We are promising these four milestones in the next 9 months." |
| 14 | 60 s | Make the ask specific. "If you can introduce us to one program officer, that's worth more than $10k." |
| 15 | 30 s | "Thank you. I'd love to take your questions." |

---

## Appendix slides (only if asked)

- A1 — Database schema diagram (5 bounded contexts)
- A2 — API contract highlights (OpenAPI 3.1)
- A3 — AI service architecture (multi-model router + eval gates)
- A4 — Competitive landscape (3×3 matrix: GrantGenie vs. Instrumentl vs. Candid)
- A5 — Detailed success criteria (SC-001 through SC-007)

---

## Handout (one-pager PDF for the audience)

Same content as Slide 1 (title) + Slide 5 (solution flow) + Slide 7 (trust) + Slide 11 (metrics) + Slide 14 (the ask). A5 portrait, single-sided.
