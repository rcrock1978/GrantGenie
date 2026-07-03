"""T026: OpenTelemetry tracing bootstrap for the AI service.

Configures FastAPI/httpx/asyncpg auto-instrumentation with an OTLP gRPC
exporter. The OTLP endpoint is read from `OTEL_EXPORTER_OTLP_ENDPOINT`
(defaults to http://jaeger:4317 in docker-compose). If the collector is
unreachable, the SDK is configured to log and continue — never crash the
service on a missing observability sink (per SRE best practice).
"""

from __future__ import annotations

import logging
import os

from opentelemetry import trace
from opentelemetry.exporter.otlp.proto.grpc.trace_exporter import OTLPSpanExporter
from opentelemetry.instrumentation.asyncpg import AsyncPGInstrumentor
from opentelemetry.instrumentation.fastapi import FastAPIInstrumentor
from opentelemetry.instrumentation.httpx import HTTPXClientInstrumentor
from opentelemetry.sdk.resources import SERVICE_NAME, Resource
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor

logger = logging.getLogger(__name__)

_initialised = False


def init_tracing(app: object | None = None) -> None:
    """Initialise OpenTelemetry. Safe to call multiple times."""
    global _initialised
    if _initialised:
        return

    resource = Resource.create({SERVICE_NAME: os.getenv("OTEL_SERVICE_NAME", "grantgenie-ai")})
    provider = TracerProvider(resource=resource)

    endpoint = os.getenv("OTEL_EXPORTER_OTLP_ENDPOINT", "http://localhost:4317")
    try:
        exporter = OTLPSpanExporter(endpoint=endpoint, insecure=True)
        provider.add_span_processor(BatchSpanProcessor(exporter))
    except Exception as exc:  # noqa: BLE001
        logger.warning("otel_exporter_init_failed endpoint=%s err=%s", endpoint, exc)

    trace.set_tracer_provider(provider)

    # Auto-instrument the standard libraries we use.
    try:
        HTTPXClientInstrumentor().instrument()
    except Exception as exc:  # noqa: BLE001
        logger.debug("httpx_instrumentation_skipped err=%s", exc)
    try:
        AsyncPGInstrumentor().instrument()
    except Exception as exc:  # noqa: BLE001
        logger.debug("asyncpg_instrumentation_skipped err=%s", exc)

    if app is not None:
        try:
            FastAPIInstrumentor.instrument_app(app)
        except Exception as exc:  # noqa: BLE001
            logger.debug("fastapi_instrumentation_skipped err=%s", exc)

    _initialised = True
    logger.info(
        "tracing_initialised service=%s endpoint=%s",
        resource.attributes.get(SERVICE_NAME),
        endpoint,
    )


def get_tracer(name: str) -> trace.Tracer:
    return trace.get_tracer(name)
