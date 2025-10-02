# tg-bot

Telegram bot — the customer-facing order channel. Stateless: no Postgres, no AMQP, no migrations. It
holds no business logic of its own; it translates the user flow into HTTP calls to other services
(`catalog`, `orders`) and relies on them for all state.

- **Owns:** nothing.
- **Gateway:** none (internal only). Dev port `:8085` (health/metrics).
- Calls `catalog` (`APP_CATALOG_URL`) and `orders` (`APP_ORDERS_URL`).

## Telegram

Long-poll worker (`plushki:poll`). The poller exits cleanly when `APP_TG_BOT_TOKEN` is empty, so dev
stacks without a token just run the HTTP probes. Set the token via `.env` (see `.env.example`).
`APP_TG_POLL_TIMEOUT_S` controls the long-poll timeout.

## Containers

`tg-bot` (HTTP health/metrics) · `tg-bot-poll` (Telegram long-poll worker).

## Run

```bash
docker compose -f compose.shared.yaml -f services/tg-bot/compose.yaml up -d --build
curl -s localhost:8085/healthz
```
