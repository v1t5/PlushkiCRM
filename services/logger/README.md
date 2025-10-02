# logger

A stateless event sentinel. It binds an exclusive, auto-delete queue with routing key `#` to every
per-context topic exchange and logs each delivery, reconstructing the OpenTelemetry trace from the
envelope `trace_id`. The result: Grafana shows a single trace spanning HTTP → outbox publish → consume
→ log line.

- **Owns:** nothing. No Postgres, no HTTP.
- **Listens to:** every `*.v1.*` event on all exchanges.
- One container running `plushki:tap`.

Useful as a development sentinel to confirm the event bus and trace propagation are healthy; it can be
left running or removed without affecting anything else.

## Run

```bash
docker compose -f compose.shared.yaml -f services/logger/compose.yaml up -d --build
docker compose -p plushki-app logs -f logger
```
