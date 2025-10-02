# catalog

Master data for the menu: products, recipes (bill of materials), categories, and units of measure.

- **Owns:** products, recipes (BOM), categories, units.
- **Emits:** `catalog.v1.product_updated`, `catalog.v1.recipe_updated` (→ `CATALOG` exchange).
- **Listens to:** nothing.
- **Gateway:** `/api/catalog/*` → `catalog:8080`. Dev port `:8082`.

## Containers

`catalog-db` (Postgres) · `catalog-migrate` (one-shot) · `catalog` (HTTP) · `catalog-relay` (outbox relay).

## Layout

Hexagonal: `src/Domain` (pure PHP), `src/App` (usecases), `src/Ports` (interfaces),
`src/Adapters/{Http,Db,Events}`, `src/Platform` (per-service cross-cutting utilities). HTTP routes are
served at the service root under `/v1/...`; the gateway strips the `/api/catalog` prefix. Every service
also serves `/healthz`, `/readyz`, `/metrics`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/catalog/compose.yaml up -d --build
curl -s localhost:8082/healthz
```
