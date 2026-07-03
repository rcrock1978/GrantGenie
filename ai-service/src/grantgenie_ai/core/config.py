"""T038: Pydantic-Settings configuration for the AI service.

Values are read from environment variables (12-factor) with sensible local
defaults matching `docker-compose.yml` and `.env.example` in the repo root.
"""

from __future__ import annotations

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    env: str = "local"
    service_name: str = "grantgenie-ai"
    log_level: str = "INFO"

    # Postgres + pgvector
    database_url: str = "postgresql+asyncpg://grantgenie:grantgenie@localhost:5432/grantgenie"
    database_pool_min: int = 2
    database_pool_max: int = 10

    # Redis
    redis_url: str = "redis://localhost:6379/0"

    # Model providers
    openai_api_key: str = ""
    anthropic_api_key: str = ""
    primary_model: str = "gpt-4o-mini"
    fallback_models: list[str] = Field(default_factory=lambda: ["gpt-4o", "claude-haiku-4-5"])
    premium_model: str = "claude-sonnet-4-5"

    # Embedding
    embedding_model: str = "text-embedding-3-small"
    embedding_dimensions: int = 1536

    # Reranker
    rerank_model: str = "bge-reranker-base"

    # Eval thresholds (SC-003)
    eval_relevance_threshold: float = 0.85
    eval_faithfulness_threshold: float = 0.90

    # Internal auth (mTLS in prod, shared secret in dev)
    internal_auth_token: str = "dev-internal-token"

    # Observability
    otel_exporter_otlp_endpoint: str = "http://localhost:4317"
    otel_service_name: str = "grantgenie-ai"


def get_settings() -> Settings:
    return Settings()
