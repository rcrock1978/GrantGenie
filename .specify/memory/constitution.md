<!--
  Sync Impact Report
  ───────────────────────────────────────────────────────
  Version change: (new) → v1.0.0
  Modified principles: N/A (initial)
  Added sections: Core Principles (I–V), Additional Constraints,
                  Development Workflow, Governance
  Removed sections: N/A
  Templates requiring updates:
    - .specify/templates/plan-template.md        ✅ generic reference — no change needed
    - .specify/templates/spec-template.md         ✅ generic reference — no change needed
    - .specify/templates/tasks-template.md        ✅ generic reference — no change needed
    - .specify/templates/constitution-template.md ✅ kept as source; constitution now concrete
  Follow-up TODOs: None
-->

# GrantGenie Constitution

## Core Principles

### I. Spec-First Development

Every feature MUST start with an executable specification in
`specs/<NNN>-feature-name/spec.md`. No ad-hoc coding without a spec.
Every requirement MUST carry a traceability ID (`REQ-MSP18-n`) referenced
by tasks, tests, code, and AI eval cases. The spec is the source of truth
— when ambiguity arises between code and spec, the spec wins until amended.

### II. Clean Architecture & Domain-Driven Design

The codebase MUST follow Clean Architecture with dependencies pointing
inward: Domain → Application → Infrastructure → Presentation. Violations
MUST be caught by automated architectural tests (PHPStan level max +
Laravel architectural tests). Each bounded context owns its data,
its language, and its domain logic. Anti-Corruption Layers MUST isolate
each bounded context from external models and third-party SDKs.

### III. Test-Driven & Evaluation-Gated Quality

Tests and evaluation thresholds MUST exist before implementation.
Red-Green-Refactor is required for every behavioral change. AI-generated
outputs MUST pass automated eval thresholds (relevance, faithfulness,
task success) before reaching users. Performance budgets (reads p95 <
200 ms, writes p95 < 400 ms, AI first-token < 1.5 s) MUST be enforced
in CI.

### IV. Security & Multi-Tenant Isolation

Multi-tenant isolation is non-negotiable. Every table MUST carry a tenant
key; row-level security MUST prevent cross-tenant data access by
construction. AuthN uses OIDC/OAuth2; AuthZ uses RBAC/ABAC enforced in
the application layer. All AI inputs/outputs MUST pass prompt-injection
defense and PII redaction. Secrets MUST never appear in code or images.

### V. Observability & Cost Awareness

Every service MUST emit structured JSON logs with correlation IDs and
consistent schema, rotated daily to `storage/logs/*/YYYY-MM-DD.json`.
Cost is a first-class architectural concern — model routing (cheapest
adequate model first), prompt/embedding caching, and GPU scale-to-zero
are required patterns. FinOps dashboards MUST track per-tenant cost
against usage-based pricing.

## Technology Stack Constraints

| Concern | Mandated choice |
|---|---|
| Backend language & framework | PHP 8.3+ / Laravel 11 |
| Frontend framework | Angular 18 (standalone components, TypeScript) |
| AI services | Python 3.12+ |
| Primary database | PostgreSQL + pgvector |
| Cache | Redis |
| Infrastructure | Docker + Kubernetes (AKS), Terraform |
| CI/CD | GitHub Actions |

All additions to the stack MUST be justified by a documented ADR
and reviewed against the principles above. The Python AI service is
the only approved exception to the PHP/Laravel backend mandate.

## Development Workflow

1. **Specify** — `/speckit.specify <description>` produces a spec under
   `specs/<NNN>-feature-name/` with traceability IDs and acceptance
   criteria. Review gate pauses before planning.
2. **Plan** — `/speckit.plan` generates plan.md, research.md, and
   data-model.md. Review gate pauses before tasking.
3. **Tasks** — `/speckit.tasks` produces a phased task breakdown with
   parallel markers and dependency ordering.
4. **Implement** — `/speckit.implement` executes tasks in order: setup,
   tests-first, core, integration, polish. Each phase validates before
   the next proceeds.
5. **Validate** — Every PR MUST pass: unit tests, integration tests,
   AI eval gates, SAST/SCA/secret scan, and performance budget checks
   before merging.

## Governance

This Constitution supersedes all other development practices in this
repository. Amendments require:

1. A documented proposal (PR or ADR) describing the change, rationale,
   and impact on existing principles.
2. Review and approval by the Solution Architect (portfolio owner).
3. A version bump per semantic versioning rules:
   - MAJOR: backward-incompatible principle removal or redefinition.
   - MINOR: new principle or materially expanded guidance.
   - PATCH: clarifications, wording, typo fixes.
4. Update of this file and propagation to any affected templates or
   agent instructions.

Compliance is verified during each `/speckit.plan` gate via the
Constitution Check section in `plan.md`. Violations MUST be documented
in the Complexity Tracking table with justification.

**Version**: 1.0.0 | **Ratified**: 2026-07-01 | **Last Amended**: 2026-07-01
