# orders

Order lifecycle: line items and status transitions
(`draft → placed → confirmed → in_production → ready → completed`, `cancelled` up to `ready`).

- **Owns:** orders, line items, status transitions.
- **Emits:** `orders.v1.created`, `orders.v1.confirmed`, `orders.v1.cancelled`, `orders.v1.fulfilled`
  (→ `ORDERS` exchange).
- **Listens to:** nothing. Reads product data from `catalog` over HTTP (`APP_CATALOG_URL`).
- **Gateway:** `/api/orders/*` → `orders:8080`. Dev port `:8083`.

## Containers

`orders-db` (Postgres) · `orders-migrate` (one-shot) · `orders` (HTTP) · `orders-relay` (outbox relay).

## Layout

Hexagonal: `src/Domain` / `src/App` / `src/Ports` / `src/Adapters/{Http,Db,Events}` / `src/Platform`.
Routes are served under `/v1/...` (gateway strips `/api/orders`); plus `/healthz`, `/readyz`,
`/metrics`. `GET /v1/orders?status=&channel=&customer_ref=&from=&to=&limit=` supports multi-filter
listing.

## Run

```bash
docker compose -f compose.shared.yaml -f services/orders/compose.yaml up -d --build
curl -s localhost:8083/healthz
```
