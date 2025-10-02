# crm

Customers and simple loyalty (visit count, total spent). A customer has a stable internal id; external
identities (TG user id, phone, email) are linked rows, so one human is never duplicated.

- **Owns:** customers, customer identities, loyalty.
- **Emits:** `crm.v1.customer_registered`, `crm.v1.loyalty_updated` (→ `CRM` exchange).
- **Listens to:** `orders.v1.fulfilled` (bumps loyalty once per order, idempotent via
  `applied_order_events`).
- **Gateway:** `/api/crm/*` → `crm:8080`. Dev port `:8088`.

## Containers

`crm-db` (Postgres) · `crm-migrate` (one-shot: migrations + walk-in customer bootstrap) ·
`crm` (HTTP) · `crm-relay` (outbox relay) · `crm-consume-orders`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/crm/compose.yaml up -d --build
curl -s localhost:8088/healthz
```
