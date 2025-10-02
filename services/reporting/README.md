# reporting

Read-only projector over the event bus. It **publishes nothing**, so it has no outbox table or relay
(an intentional break from the canonical service shape). A single shared `applied_events (event_id PK)`
table enforces idempotency across all consumers.

- **Owns:** denormalised read models (`sales_by_day`, `top_items`, `stock_low_events`,
  `movements_by_day`).
- **Emits:** nothing.
- **Listens to:** `orders.v1.fulfilled`, `inventory.v1.stock_low`, `inventory.v1.movement_posted`.
- **Gateway:** `/api/reporting/*` → `reporting:8080`. Dev port `:8090`.

## Read endpoints (`/v1/...`, query-string `tenant_id`)

```
GET /v1/sales/daily?from=&to=
GET /v1/sales/by-channel?date=
GET /v1/sales/top-items?date=&limit=&order_by=qty|revenue
GET /v1/inventory/low-stock-events?from=&to=&limit=
GET /v1/inventory/waste-percentage?from=&to=
GET /v1/inventory/waste?from=&to=&limit=
```

Grafana is provisioned with a `Reporting` Postgres datasource against `reporting-db` and a starter
overview dashboard.

## Containers

`reporting-db` (Postgres) · `reporting-migrate` (one-shot) · `reporting` (HTTP read API) ·
`reporting-consume-orders` (fulfilled) · `reporting-consume-stock-low` · `reporting-consume-movements`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/reporting/compose.yaml up -d --build
curl -s localhost:8090/healthz
```
