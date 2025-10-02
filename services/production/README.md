# production

Daily production planning for bakers. Accumulates confirmed orders into a daily plan; publishing the
plan materialises baker tasks; completing a task emits the recipe snapshot used.

- **Owns:** daily plan, baker tasks.
- **Emits:** `production.v1.plan_published`, `production.v1.task_started`,
  `production.v1.task_completed` (→ `PRODUCTION` exchange).
- **Listens to:** `orders.v1.confirmed` (accumulate into plan), `catalog.v1.recipe_updated` and
  `inventory.v1.stock_changed`.
- **Gateway:** `/api/production/*` → `production:8080`. Dev port `:8087`.

## Containers

`production-db` (Postgres) · `production-migrate` (one-shot) · `production` (HTTP) ·
`production-relay` (outbox relay) · `production-consume-catalog` · `production-consume-orders`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/production/compose.yaml up -d --build
curl -s localhost:8087/healthz
```
