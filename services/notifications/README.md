# notifications

Outbound notifications. Consumes domain events and sends Telegram messages; keeps a log of what was
sent. The HTTP container only serves health/metrics — there is no public API.

- **Owns:** outbound message log, channel adapters.
- **Emits:** nothing.
- **Listens to:** `orders.v1.*`, `production.v1.plan_published`, `inventory.v1.low_stock`.
- **Gateway:** none (internal only). Dev port `:8084` (health/metrics).

## Telegram

Sends via the Telegram bot API (`APP_TG_API_BASE`). Runs in **dry-run mode** (logs, no outbound
sends) unless `APP_TG_BOT_TOKEN` is set; low-stock alerts are dropped unless `APP_ADMIN_CHAT_ID` is set.
Both are supplied via `.env` (see `.env.example`).

## Containers

`notifications-db` (Postgres) · `notifications-migrate` (one-shot) · `notifications` (HTTP
health/metrics) · `notifications-consume-orders` · `notifications-consume-inventory`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/notifications/compose.yaml up -d --build
curl -s localhost:8084/healthz
```
