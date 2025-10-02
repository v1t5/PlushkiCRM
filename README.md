# 🥐 PlushkiCRM

> A management platform for **bakery production + a light café POS**, built as a set of
> **PHP / Symfony 7** microservices — bounded contexts, hexagonal layering, an outbox/event bus, and
> end-to-end observability out of the box.

<p>
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="Symfony 7" src="https://img.shields.io/badge/Symfony-7-000000?logo=symfony&logoColor=white">
  <img alt="PostgreSQL 16" src="https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white">
  <img alt="RabbitMQ" src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white">
  <img alt="Docker Compose" src="https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white">
  <img alt="Architecture: Hexagonal" src="https://img.shields.io/badge/architecture-hexagonal-informational">
</p>

---

PlushkiCRM is a small but realistic back office for a bakery with a café front. Each bounded context
is its own Symfony service with its own PostgreSQL database; services stay decoupled and communicate
only over HTTP (through a gateway) and asynchronous AMQP events. See
[docs/architecture.md](docs/architecture.md) for the full design.

## Table of contents

- [What it does](#what-it-does)
- [Highlights](#highlights)
- [Architecture](#architecture)
  - [Services](#services)
  - [Tech stack](#tech-stack)
  - [Repository layout](#repository-layout)
- [Getting started](#getting-started)
- [Observability](#observability)
- [Development](#development)
- [Status](#status)
- [License](#license)

## What it does

- **Order intake** from multiple channels (Telegram bot, café POS, future web).
- **Catalog** of products, recipes (bill of materials), categories and units.
- **Inventory** — warehouses, stock levels, and stock movements driven by events.
- **Production** — daily plans and baker task boards derived from confirmed orders and stock.
- **Light café POS** — in-person sales without fiscal printers or card acquiring (v1).
- **CRM** — customers and loyalty.
- **Reporting** — denormalised read models and Grafana dashboards.
- **Notifications** — outbound message log + channel adapters.

Money is stored as integer **kopecks** and quantities as integer **base units** (mg/ml/pcs) —
never floats. Every row carries a `tenant_id` so multi-tenancy is a later config switch, not a rewrite.

## Highlights

- 🧩 **Hexagonal, one service per bounded context.** Each service is its own Symfony app with
  `Domain / App / Ports / Adapters / Platform` layers. No service imports another.
- 🗄️ **One PostgreSQL database per service.** Cross-service SQL is physically impossible — state
  propagates through events only.
- 📨 **Outbox + topic exchanges.** Reliable event publishing via the outbox pattern, durable
  RabbitMQ topic exchanges, idempotent consumers. Routing key `<context>.v1.<event>.<tenant>`.
- 🔭 **Observable from day one.** Structured JSON logs (Monolog → Loki), distributed traces
  (OpenTelemetry → Tempo), and metrics (Prometheus) — all visible in Grafana. Trace context
  survives the HTTP → publish → consume hop.
- 🔐 **Real auth.** RS256 access JWTs with a JWKS endpoint, refresh-token rotation, opaque service
  tokens, bcrypt passwords — issued by the `identity` service.
- 🚪 **Single gateway.** Caddy routes `/api/<svc>/*` → `<svc>:8080` and serves the SPAs.

## Architecture

```
                       ┌─────────────┐
                       │   Caddy     │  routing, CORS, SPA hosting
                       │  (gateway)  │
                       └──────┬──────┘
                              │ HTTP/JSON  (/api/<svc>/*)
            ┌─────────────────┼──────────────────┐
            ▼                 ▼                  ▼
      ┌─────────┐       ┌──────────┐      ┌──────────┐
      │ identity│       │  orders  │      │ catalog  │  ... etc
      └────┬────┘       └─────┬────┘      └────┬─────┘
           │ events           │                │
           └──────────┬───────┴────────────────┘
                      ▼
             ┌──────────────────┐
             │    RabbitMQ      │
             │ (topic exchanges)│
             └────────┬─────────┘
                      │
       ┌────────┬─────┴─────┬─────────┬──────────┐
       ▼        ▼           ▼         ▼          ▼
   inventory production notifications crm    reporting
```

Each service runs as an HTTP container (FrankenPHP) **plus** one console-worker container per
background loop — the outbox relay (`<svc>-relay`) and event consumers (`<svc>-consume-<src>`).
Migrations run as a one-shot console command on startup.

### Services

| Service | Owns | Emits | Listens to |
|---|---|---|---|
| **identity** | users, roles, sessions, JWT keys | `identity.v1.user_created` | — |
| **catalog** | products, recipes (BOM), categories, units | `catalog.v1.product_updated`, `catalog.v1.recipe_updated` | — |
| **orders** | orders, line items, status transitions | `orders.v1.created`, `…confirmed`, `…cancelled`, `…fulfilled` | — |
| **inventory** | warehouses, stock levels, stock movements | `inventory.v1.stock_changed`, `…low_stock` | `production.v1.task_completed`, `orders.v1.fulfilled` |
| **production** | daily plan, baker tasks | `production.v1.plan_published`, `…task_started`, `…task_completed` | `orders.v1.confirmed`, `inventory.v1.stock_changed` |
| **crm** | customers, loyalty | `crm.v1.customer_registered`, `…loyalty_updated` | `orders.v1.fulfilled` |
| **notifications** | outbound message log, channel adapters | — | `orders.v1.*`, `production.v1.plan_published`, `inventory.v1.low_stock` |
| **reporting** | denormalised read models | — | all `*.v1.*` |
| **logger** | — (event sentinel) | — | all `*.v1.*` (logs every event) |

Edge / channel adapters: **tg-bot** (Telegram), **pos-web** (café POS SPA), **admin-web** (operations SPA).
They only ever call the gateway, never services directly.

### Tech stack

| Concern | Choice |
|---|---|
| Language / framework | PHP 8.2+, **Symfony 7** per service (HttpKernel, Console, DI, Validator) |
| Web server / runtime | **FrankenPHP** — one image per service serves HTTP and runs the console workers |
| HTTP | Symfony HttpKernel — controllers + event subscribers |
| Database | **PostgreSQL 16**, one container per service |
| DB access | **Doctrine DBAL** with hand-written SQL — *no ORM, no entities* |
| Migrations | plain `.sql` files + a small `plushki:migrate` console command |
| Messaging | **php-amqplib** — durable topic exchanges, manual ack/nack |
| Background loops | Symfony **console-command workers**, one container each |
| Identifiers | UUID v7 (`symfony/uid`) |
| Auth | RS256 JWT + JWKS (`firebase/php-jwt`), bcrypt via native `password_hash` |
| Logging | **Monolog** JSON to stdout (`svc/env/trace_id/span_id`) → Loki |
| Tracing | **OpenTelemetry PHP** SDK, OTLP → Tempo |
| Metrics | `promphp/prometheus_client_php` at `/metrics` → Prometheus |
| Errors | RFC 7807 `application/problem+json` (`https://errors.plushki/<svc>/<code>`) |
| Cache | Redis 7 |
| Gateway | Caddy — routing, CORS, SPA hosting |
| Frontend | Vue 3 + Vite (`pos-web`, `admin-web`) |
| Deployment | Docker Compose |

> **Per-service `Platform/`.** Each service keeps its *own* copy of the cross-cutting utilities
> (logging, OTel, AMQP client, health checks, config). Drift is allowed; consolidation into a shared
> package is deliberately avoided — that is what keeps services independently deployable.

### Repository layout

```
compose.shared.yaml         # shared infra: RabbitMQ, Redis, Loki, Promtail, Tempo, Prometheus, Grafana, Caddy
infra/                      # Caddyfile + Grafana/Loki/Tempo/Prometheus/Promtail configs
docs/                       # architecture / roadmap / development
Makefile                    # docker compose orchestration (dev-up / stack-up / …)
services/
  _template/                # Symfony skeleton + the per-service Platform/ donor copy
  identity/  catalog/  orders/  inventory/  production/  crm/  reporting/
  notifications/  logger/   # one Symfony app per bounded context
  tg-bot/                   # Telegram bot
  pos-web/  admin-web/      # Vue 3 SPAs
```

Each service directory owns its `composer.json`, `Dockerfile`, `compose.yaml`, `migrations/`,
`config/`, and `src/`. See each service's own `README.md` for its routes and layer map
(e.g. [services/identity/README.md](services/identity/README.md)).

## Getting started

### Prerequisites

- **Docker** + **Docker Compose**.
- **GNU Make** (optional).
- ~4 GB free RAM for the full stack (each service brings its own Postgres container).

No local PHP or Composer is required — everything builds and runs inside containers.

### Quick start

```bash
# (optional) override default credentials — see Configuration below
cp .env.example .env

# 1. Shared infrastructure only (RabbitMQ, Redis, Grafana stack, Caddy)
make dev-up

# 2. …or bring up the whole stack (shared infra + every service, built from source)
make stack-up
```

The stack runs out of the box with built-in dev defaults — `.env` is only needed to change them.

Equivalent raw commands (if you don't have Make):

```bash
# shared infra only
docker compose -f compose.shared.yaml up -d

# full stack
docker compose -f compose.shared.yaml \
  -f services/identity/compose.yaml \
  -f services/logger/compose.yaml \
  -f services/catalog/compose.yaml \
  -f services/orders/compose.yaml \
  -f services/notifications/compose.yaml \
  -f services/tg-bot/compose.yaml \
  -f services/inventory/compose.yaml \
  -f services/production/compose.yaml \
  -f services/crm/compose.yaml \
  -f services/pos-web/compose.yaml \
  -f services/admin-web/compose.yaml \
  -f services/reporting/compose.yaml up -d
```

Migrations and an admin bootstrap run automatically on first boot. In dev, the `identity` service
generates a throwaway RS256 signing key at `var/jwt/dev-key.pem` and grants the admin role to
`admin@plushki.local` on every startup (register that email first, then it is promoted on the next boot).

### Verify it's alive

```bash
make dev-verify     # curls through Caddy, then asks Loki + Tempo what they captured
```

Then open the **admin UI** at <http://localhost:8080/admin/> or the **POS** at <http://localhost:8080/>.

### Useful Make targets

| Target | What it does |
|---|---|
| `make dev-up` / `make dev-down` | Start / stop shared infra only |
| `make stack-up` / `make stack-down` | Start / stop the whole stack |
| `make stack-ps` | List stack containers |
| `make stack-build svc=crm` | Rebuild one service's image |
| `make dev-logs svc=rabbitmq` | Tail a shared-infra container's logs |
| `make dev-verify` | End-to-end smoke check across Caddy + Loki + Tempo |

### Configuration

Services are configured via `APP_*` environment variables, set in each service's `compose.yaml`.
Key ones: `APP_SERVICE`, `APP_ENV`, `APP_HTTP_ADDR`, `APP_DATABASE_URL`, `APP_AMQP_URL`,
`APP_OTLP_ENDPOINT`, `APP_LOG_LEVEL`, plus per-service `APP_JWT_*`, `APP_BOOTSTRAP_ADMIN_EMAIL`, and
`APP_TG_*`. A yaml overlay (`config/config.example.yaml`) is also supported; env wins.

**Credentials are not hard-coded in the compose files.** Every secret — RabbitMQ login, each
service's PostgreSQL credentials, the Telegram bot token, the bootstrap admin email — is read from
the environment with a dev default baked in, e.g.
`APP_DATABASE_URL=postgres://${IDENTITY_DB_USER:-identity}:${IDENTITY_DB_PASSWORD:-identity}@…`.
Copy [`.env.example`](.env.example) to `.env` (git-ignored) and set your own values; Docker Compose
loads it automatically from the repo root. With no `.env`, the defaults keep the stack working for
local development. **Change them before any non-local deployment.**

## Observability

The shared stack ships a full observability backend, all wired into Grafana:

| Tool | URL | Purpose |
|---|---|---|
| **Grafana** | <http://localhost:3000> | Dashboards (anonymous admin enabled) |
| **Prometheus** | <http://localhost:9090> | Metrics |
| **Loki** | <http://localhost:3100> | Logs |
| **Tempo** | <http://localhost:3200> | Traces |
| **RabbitMQ** | <http://localhost:15672> | Management UI (`plushki` / `plushki`) |

Every log line and span carries `trace_id` / `span_id`, so you can pivot from a log in Loki straight
to its trace in Tempo — including across the publish → consume hop on RabbitMQ.

## Development

Build one service whole, end-to-end, before starting the next. To add a service, copy
`services/_template/` and follow the layer order: migrations → DB adapter → domain → ports/app →
HTTP adapter → events.

Conventions worth preserving:

- **Hexagonal layout** per service (`Domain / App / Ports / Adapters / Platform`); the `Domain` layer
  is pure PHP with no framework dependency.
- **No service imports another** — the only couplings are HTTP (via the gateway) and AMQP events.
- **No floats for money or quantities** — integer kopecks and base units everywhere.
- **`tenant_id` on every row** (default `'default'`); soft-delete master data via `archived_at`.
- The event envelope, exchanges, and routing keys are the cross-service contract — keep them stable.

### Tests

Each backend service has a PHPUnit suite under `services/<svc>/tests/` — unit tests for the pure
`Domain` layer and usecase tests for `App` driven by in-memory port fakes (no database, broker, or
network). Run them all in a throwaway PHP 8.3 container (no local PHP needed):

```bash
make test                 # every service
make test svc="orders crm" # a subset
```

CI runs the same suites per service on every push and pull request
([.github/workflows/ci.yml](.github/workflows/ci.yml)).

## Status

PlushkiCRM covers the full set of bounded contexts — identity, catalog, orders, inventory,
production, crm, reporting, and notifications — plus the Telegram bot and the POS and admin web UIs.
See each service's `README.md` for details.

## License

Released under the [MIT License](LICENSE).
