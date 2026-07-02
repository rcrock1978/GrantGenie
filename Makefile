# GrantGenie monorepo Makefile
# See specs/001-grantgenie-core/quickstart.md for the validation flow.

SHELL := /bin/bash
.DEFAULT_GOAL := help

ENV_FILE ?= .env
COMPOSE ?= docker compose

# -------- Help --------
.PHONY: help
help: ## Show this help.
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# -------- Stack lifecycle --------
.PHONY: up down restart logs ps
up: ## Start the full local stack (postgres, redis, minio, mailhog, backend, frontend, ai-service)
	$(COMPOSE) up -d --build
	@echo "Stack up. Try: curl -sf http://localhost:8000/api/v1/healthz"

down: ## Stop and remove containers (volumes preserved)
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

logs: ## Tail logs from all services
	$(COMPOSE) logs -f --tail=200

ps: ## Show service status
	$(COMPOSE) ps

.PHONY: up-observability up-full down-clean
up-observability: ## Also start Prometheus + Grafana
	$(COMPOSE) --profile observability up -d

up-full: up up-observability ## Start everything (default services + observability)

down-clean: down ## Stop stack AND remove volumes (destructive)
	$(COMPOSE) down -v

# -------- Per-service commands --------
.PHONY: backend-shell frontend-shell ai-shell db-shell redis-cli
backend-shell: ## Open a shell in the backend container
	$(COMPOSE) exec backend bash

frontend-shell: ## Open a shell in the frontend container
	$(COMPOSE) exec frontend sh

ai-shell: ## Open a shell in the ai-service container
	$(COMPOSE) exec ai-service bash

db-shell: ## Open psql against the local postgres
	$(COMPOSE) exec postgres psql -U grantgenie -d grantgenie

redis-cli: ## Open redis-cli against the local redis
	$(COMPOSE) exec redis redis-cli

# -------- Database --------
.PHONY: migrate migrate-fresh seed-demo db-reset
migrate: ## Run database migrations
	$(COMPOSE) exec backend php artisan migrate --force

migrate-fresh: ## Drop all tables and re-migrate (destructive)
	$(COMPOSE) exec backend php artisan migrate:fresh --force

seed-demo: ## Seed 3 demo tenants, 50 grants, 5 boilerplate docs/tenant
	$(COMPOSE) exec backend php artisan db:seed --class=Database\\Seeders\\DemoSeeder

db-reset: down-clean up migrate seed-demo ## Full DB reset: down-clean, up, migrate, seed

# -------- Tests --------
.PHONY: test test-backend test-frontend test-ai test-isolation test-unit test-contract
test: test-backend test-ai ## Run all backend + ai tests

test-backend: ## Run Pest tests in backend
	cd backend && vendor/bin/pest --parallel

test-unit: ## Run backend unit tests only
	cd backend && vendor/bin/pest tests/Unit

test-isolation: ## Run the SC-005 multi-tenant RLS isolation suite
	cd backend && vendor/bin/pest tests/Integration/Isolation

test-frontend: ## Run Angular unit tests (Karma+Jasmine)
	cd frontend && npm test -- --watch=false --browsers=ChromeHeadless

test-ai: ## Run pytest in ai-service
	cd ai-service && uv run --frozen pytest

test-contract: ## Run API contract tests (schemathesis against running backend)
	cd backend && vendor/bin/pest tests/Contract

# -------- Lint / static analysis --------
.PHONY: lint lint-backend lint-frontend lint-ai format check
lint: lint-backend lint-frontend lint-ai ## Run all linters

lint-backend: ## PHPStan + Pint --test
	cd backend && vendor/bin/pint --test && vendor/bin/phpstan analyse --no-progress --memory-limit=2G

lint-frontend: ## ESLint + Prettier --check
	cd frontend && npx prettier --check . && npx eslint . --max-warnings 0

lint-ai: ## Ruff + mypy
	cd ai-service && uv run --frozen ruff check . && uv run --frozen mypy src

format: ## Auto-format all sources
	cd backend && vendor/bin/pint
	cd frontend && npx prettier --write .
	cd ai-service && uv run --frozen ruff check --fix . && uv run --frozen ruff format .

check: lint test ## Lint + test (pre-merge gate)

# -------- E2E / performance / NFRs --------
.PHONY: e2e-p1 e2e-p2-p3 load-test-smoke eval-gates validate-nfrs
e2e-p1: ## Run Playwright E2E tests for P1 user stories
	cd frontend && npx playwright test --grep @us1|@us2|@us3

e2e-p2-p3: ## Run Playwright E2E tests for P2/P3 user stories
	cd frontend && npx playwright test --grep @us4|@us5|@us6|@us7

load-test-smoke: ## Run k6 load tests for SC-001/002 budgets
	$(COMPOSE) run --rm -T --entrypoint sh backend -c "\
	  k6 run tests/load/discovery.js && k6 run tests/load/draft.js" || true

eval-gates: ## Run the SC-003 eval-gate threshold test
	cd ai-service && uv run --frozen pytest -m eval

validate-nfrs: check load-test-smoke eval-gates security-scan ## Run all NFR validations

# -------- Security / FinOps --------
.PHONY: security-scan finops-report
security-scan: ## Run SAST (semgrep), SCA (trivy), secret scan (gitleaks)
	@echo "(stub) Wire semgrep/trivy/gitleaks in .github/workflows/security.yml — see T035"

finops-report: ## Print per-tenant AI cost summary
	$(COMPOSE) exec backend php artisan finops:report

# -------- Time machine (for tests) --------
.PHONY: advance-time
advance-time: ## Advance the in-app clock by N days (e.g. make advance-time DAYS=7)
	$(COMPOSE) exec backend php artisan time:advance --days=${DAYS:-7}

# -------- Deploy --------
.PHONY: deploy-staging deploy-prod
deploy-staging: ## Deploy to staging (GitHub Actions: deploy.yml)
	@echo "Triggered via GitHub Actions on push to main."

deploy-prod: ## Deploy to production (manual approval required)
	@echo "Triggered via GitHub Actions workflow_dispatch on production branch."

# -------- Misc --------
.PHONY: clean
clean: ## Remove build artifacts, cache, generated files
	rm -rf backend/storage/framework/cache/data/* backend/storage/framework/sessions/* backend/storage/framework/views/*
	rm -rf backend/bootstrap/cache/*.php
	rm -rf frontend/dist frontend/.angular frontend/coverage
	rm -rf ai-service/.pytest_cache ai-service/.mypy_cache ai-service/.ruff_cache ai-service/htmlcov
	rm -rf tests/load/results
