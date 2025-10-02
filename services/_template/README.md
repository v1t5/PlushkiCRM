# _template

The service skeleton every backend service is cloned from. It carries the per-service `Platform/`
donor copy (cross-cutting utilities) plus the Symfony wiring, but no domain.

> `_template` is a scaffold — it is **not** part of `make stack-up`. It is never built or deployed,
> only copied.

## Layout

```
composer.json              # Symfony + DBAL + php-amqplib + OTel + Monolog + firebase/php-jwt
Dockerfile                 # FrankenPHP image (serves HTTP on :8080; also runs the workers)
compose.yaml               # canonical per-service compose: db + migrate + http + relay/consumers
public/index.php           # HTTP front controller (symfony/runtime)
bin/console                # Symfony console (migrate / relay / consumer workers)
src/
  Kernel.php               # wire-up: config, logger, OTel, AMQP, DB, routing
  Platform/                # this service's OWN copy of cross-cutting code (drift allowed):
    Config.php             #   APP_* env + optional config.yaml
    Db.php                 #   DBAL connection from a postgres:// DSN
    Migrator.php           #   applies goose-annotated *.sql
    Amqp.php               #   php-amqplib connection
    Log.php                #   Monolog JSON + trace_id/span_id
    Otel.php               #   OTLP/HTTP tracer -> Tempo
    Problem.php            #   RFC 7807 problem+json
    ProblemException.php   #   throwable carrying a Problem
    Http/                  #   request subscribers + health controller + not-found/405 mapping:
      ProblemSubscriber.php, AccessLogSubscriber.php, TraceRequestSubscriber.php, HealthController.php
    Events/                #   outbox relay + consumer machinery + envelope:
      Envelope.php, OutboxStore.php, OutboxRow.php, OutboxRelay.php, Consumer.php, PoisonException.php
    Console/               #   plushki:migrate, plushki:outbox-relay
migrations/
  0001_outbox.sql          # shared outbox table (goose-annotated)
```

## Cloning a new service

1. Copy this directory to `services/<svc>/`.
2. Rename the namespace `Plushki\Template` → `Plushki\<Svc>` (in `composer.json`, `src/**`,
   `public/index.php`, `bin/console`) and the literal `template` → `<svc>` in `.env` and
   `compose.yaml`. The helper `dev/tools_rename_svc.php` automates the namespace rename.
3. Add `src/Domain`, `src/App`, `src/Ports`, `src/Adapters/{Http,Db,Events}`.
4. Drop the service's migrations into `migrations/` and extend
   `Kernel::configureContainer()` / `configureRoutes()`.
5. Publishing services: bind `OutboxStore` to the DB outbox repo, register
   `Platform\Console\OutboxRelayCommand`, and set `APP_OUTBOX_EXCHANGE`.

## Run model (one process per loop)

The HTTP server and each background loop (outbox relay, consumers) run as their own container from the
same image: `<svc>` (HTTP), `<svc>-relay` (outbox relay), `<svc>-consume-<src>` (one per subscribed
exchange), plus a one-shot `<svc>-migrate`.
