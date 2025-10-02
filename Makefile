SHARED := compose.shared.yaml

# Per-service compose snippets. Order matters for `make stack-up`: dependents
# are listed after their dependencies (orders depends on catalog HTTP at place
# time, etc), so a fresh `up -d` brings everything up cleanly.
SERVICES := identity logger catalog orders notifications tg-bot inventory production crm pos-web admin-web reporting

# `-f compose.shared.yaml -f services/<svc>/compose.yaml -f ...` chain.
COMPOSE_FILES := -f $(SHARED) $(foreach s,$(SERVICES),-f services/$(s)/compose.yaml)

.PHONY: dev-up dev-down dev-restart dev-logs dev-ps dev-verify \
        stack-up stack-down stack-ps stack-build test

# --- Shared infra only (Phase 0 / dev sentinel) ---

dev-up:
	docker compose -f $(SHARED) up -d

dev-down:
	docker compose -f $(SHARED) down

dev-restart:
	docker compose -f $(SHARED) restart $(svc)

dev-logs:
	docker compose -f $(SHARED) logs -f $(svc)

dev-ps:
	docker compose -f $(SHARED) ps

# End-to-end check: hit Caddy, then ask Loki and Tempo what they captured.
dev-verify:
	@echo "==> curl http://localhost:8080/echo/hello"
	@curl -fsS http://localhost:8080/echo/hello && echo
	@echo "==> sleeping 4s for promtail + tempo flush"
	@sleep 4
	@echo "==> Loki query: {service=\"caddy\"}"
	@curl -fsS -G 'http://localhost:3100/loki/api/v1/query_range' \
		--data-urlencode 'query={service="caddy"}' \
		--data-urlencode 'limit=3' | head -c 600 ; echo
	@echo "==> Tempo search: service.name=caddy"
	@curl -fsS -G 'http://localhost:3200/api/search' \
		--data-urlencode 'tags=service.name=caddy' \
		--data-urlencode 'limit=3' | head -c 600 ; echo
	@echo "==> Grafana UI: http://localhost:3000 (anon admin enabled)"

# --- Whole stack (shared + every service) ---

stack-up:
	docker compose $(COMPOSE_FILES) up -d

stack-down:
	docker compose $(COMPOSE_FILES) down

stack-ps:
	docker compose $(COMPOSE_FILES) ps

# Rebuild one service's image (usage: make stack-build svc=crm).
# Without svc=, builds everything that has a build: stanza.
stack-build:
	docker compose $(COMPOSE_FILES) build $(svc)

# --- Tests ---

# Run the PHPUnit suites in a throwaway PHP 8.3 container (no local PHP needed).
# All services by default; a subset with `make test svc="orders crm"`.
test:
	MSYS_NO_PATHCONV=1 docker run --rm -v "$(CURDIR)":/app -w /app php:8.3-cli-alpine sh scripts/run-tests.sh $(svc)
