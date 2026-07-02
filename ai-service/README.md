# GrantGenie AI Service

Python 3.12+ FastAPI service: RAG retrieval, multi-model generation, eval gates, and safety checks.

## Quick start

```bash
uv sync
uv run uvicorn grantgenie_ai.api.main:app --reload --port 8001
```

## Endpoints

- `GET  /internal/v1/healthz` — liveness
- `GET  /internal/v1/readyz`  — readiness (DB, Redis, models)
- `POST /internal/v1/rag/retrieve`
- `POST /internal/v1/generate/proposal`
- `POST /internal/v1/generate/budget-narrative`
- `POST /internal/v1/eval/score`
- `POST /internal/v1/safety/check`

See `../specs/001-grantgenie-core/contracts/ai-service-http.yaml`.

## Architecture

```
src/grantgenie_ai/
├── api/            FastAPI routers
├── core/           Config, logging, telemetry
├── domain/         Models, citations, eval scoring
├── retrieval/      RAG: chunking, embedding, search (pgvector)
├── generation/     Multi-model router, prompt templates
├── eval/           RAGAS/deepeval gates
└── safety/         Prompt-injection defense, PII redaction
```

Layered per Clean Architecture; framework-agnostic core, infrastructure (pgvector, Redis, HTTP) at the edges.

## Tests

```bash
uv run pytest                     # unit + integration (testcontainers)
uv run pytest -m eval             # eval-gate threshold tests (frozen dataset)
uv run pytest -m safety           # prompt-injection / PII red-team
uv run ruff check . && uv run mypy src
```
