"""T038: API router aggregator.

Each domain (RAG, generation, eval, safety) registers its own sub-router
in a follow-up phase. For now we expose only the v1 surface and a stub for
each endpoint so imports succeed and OpenAPI is generated.
"""

from __future__ import annotations

from fastapi import APIRouter

api_router = APIRouter()


@api_router.get("/rag/retrieve", tags=["rag"], summary="RAG retrieve (stub)")
async def rag_retrieve_stub() -> dict[str, str]:
    return {"stub": "true", "endpoint": "rag/retrieve", "status": "implemented in T082"}


@api_router.post("/generate/proposal", tags=["generation"], summary="Generate proposal (stub)")
async def generate_proposal_stub() -> dict[str, str]:
    return {"stub": "true", "endpoint": "generate/proposal", "status": "implemented in T103"}


@api_router.post(
    "/generate/budget-narrative",
    tags=["generation"],
    summary="Budget narrative (stub)",
)
async def generate_budget_narrative_stub() -> dict[str, str]:
    return {
        "stub": "true",
        "endpoint": "generate/budget-narrative",
        "status": "implemented in T119",
    }


@api_router.post("/eval/score", tags=["eval"], summary="Eval-gate score (stub)")
async def eval_score_stub() -> dict[str, str]:
    return {"stub": "true", "endpoint": "eval/score", "status": "implemented in T105"}


@api_router.post("/safety/check", tags=["safety"], summary="Safety check (stub)")
async def safety_check_stub() -> dict[str, str]:
    return {"stub": "true", "endpoint": "safety/check", "status": "implemented in T106"}
