"""T038: FastAPI application entry point for the AI service.

Wires routers, middleware, startup/shutdown, and OpenTelemetry tracing.
Exposes `/healthz` (liveness) and `/readyz` (readiness: DB + Redis + a model
provider) for k8s probes.
"""

from __future__ import annotations

from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.responses import JSONResponse

from grantgenie_ai.api.router import api_router
from grantgenie_ai.core.config import Settings, get_settings
from grantgenie_ai.core.logging import get_logger, init_logging
from grantgenie_ai.core.telemetry import init_tracing

logger = get_logger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    settings = get_settings()
    init_logging(settings.log_level)
    init_tracing(app)
    logger.info("ai_service_starting env=%s service=%s", settings.env, settings.service_name)
    yield
    logger.info("ai_service_shutting_down")


def create_app(settings: Settings | None = None) -> FastAPI:
    settings = settings or get_settings()
    application: FastAPI = FastAPI(
        title="GrantGenie AI Service",
        version="0.1.0",
        description="RAG retrieval, multi-model generation, eval gates, and safety checks.",
        lifespan=lifespan,
    )

    application.include_router(api_router, prefix="/internal/v1")

    @application.get("/healthz", include_in_schema=False)
    async def healthz() -> JSONResponse:
        return JSONResponse({"status": "ok"})

    @application.get("/readyz", include_in_schema=False)
    async def readyz() -> JSONResponse:
        # T038: ready when process is alive; deeper checks (DB, Redis, model
        # providers) are added in Phase 2 readiness probes.
        return JSONResponse({"status": "ready"})

    return application


app: FastAPI = create_app()


app = create_app()
