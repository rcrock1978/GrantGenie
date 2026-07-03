"""T027: Structured JSON logging for the AI service.

Uses `structlog` to produce a single JSON line per log event. The configured
processors:
  - inject `correlation_id` and `account_id` from contextvars
  - format timestamps as ISO 8601 UTC
  - merge in any keyword args as the `payload` field
  - render to JSON for log shipping (Loki / Azure Monitor)

A `bind_context(correlation_id=..., account_id=...)` helper sets per-request
values; downstream loggers inherit them automatically.
"""

from __future__ import annotations

import logging
import sys
from collections.abc import MutableMapping
from contextvars import ContextVar
from typing import Any

import structlog

correlation_id_var: ContextVar[str | None] = ContextVar("correlation_id", default=None)
account_id_var: ContextVar[str | None] = ContextVar("account_id", default=None)


def _add_context(
    _: object,
    __: str,
    event_dict: MutableMapping[str, Any],
) -> MutableMapping[str, Any]:
    cid = correlation_id_var.get()
    if cid is not None:
        event_dict.setdefault("correlation_id", cid)
    aid = account_id_var.get()
    if aid is not None:
        event_dict.setdefault("account_id", aid)
    return event_dict


def init_logging(level: str = "INFO") -> None:
    """Configure structlog + stdlib logging. Idempotent."""
    logging.basicConfig(
        format="%(message)s",
        stream=sys.stdout,
        level=getattr(logging, level.upper(), logging.INFO),
    )

    structlog.configure(
        processors=[
            structlog.contextvars.merge_contextvars,
            _add_context,
            structlog.processors.add_log_level,
            structlog.processors.TimeStamper(fmt="iso", utc=True),
            structlog.processors.StackInfoRenderer(),
            structlog.processors.format_exc_info,
            structlog.processors.JSONRenderer(),
        ],
        wrapper_class=structlog.make_filtering_bound_logger(
            getattr(logging, level.upper(), logging.INFO)
        ),
        logger_factory=structlog.PrintLoggerFactory(),
        cache_logger_on_first_use=True,
    )


def get_logger(name: str | None = None) -> structlog.stdlib.BoundLogger:
    logger: structlog.stdlib.BoundLogger = structlog.get_logger(name)
    return logger


def bind_context(correlation_id: str | None = None, account_id: str | None = None) -> None:
    if correlation_id is not None:
        correlation_id_var.set(correlation_id)
    if account_id is not None:
        account_id_var.set(account_id)


def clear_context() -> None:
    correlation_id_var.set(None)
    account_id_var.set(None)
