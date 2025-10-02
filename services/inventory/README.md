# inventory

Warehouses, stock levels, and an append-only stock-movement ledger (IN, OUT, WASTE, ADJUST).
Quantities are integer base units (mg/ml/pcs).

- **Owns:** warehouses, stock levels, stock movements.
- **Emits:** `inventory.v1.stock_changed`, `inventory.v1.low_stock`, `inventory.v1.movement_posted`
  (→ `INVENTORY` exchange).
- **Listens to:** `production.v1.task_completed` (ingredients used), `orders.v1.fulfilled` (goods sold),
  and `catalog` updates.
- **Gateway:** `/api/inventory/*` → `inventory:8080`. Dev port `:8086`.

## Containers

`inventory-db` (Postgres) · `inventory-migrate` (one-shot: migrations + default-warehouse bootstrap) ·
`inventory` (HTTP) · `inventory-relay` (outbox relay) · `inventory-consume-catalog` ·
`inventory-consume-orders` · `inventory-consume-production`.

The default warehouse is created on startup from `APP_DEFAULT_WAREHOUSE_CODE` / `APP_DEFAULT_WAREHOUSE_NAME`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/inventory/compose.yaml up -d --build
curl -s localhost:8086/healthz
```
