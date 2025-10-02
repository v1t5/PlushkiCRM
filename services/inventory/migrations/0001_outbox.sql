-- +goose Up
-- +goose StatementBegin

CREATE TABLE IF NOT EXISTS outbox_events (
    event_id        UUID         PRIMARY KEY,
    aggregate_id    UUID         NOT NULL,
    aggregate_type  TEXT         NOT NULL,
    schema          TEXT         NOT NULL,
    payload         JSONB        NOT NULL,
    occurred_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    published_at    TIMESTAMPTZ  NULL,
    tenant_id       TEXT         NOT NULL DEFAULT 'default',
    trace_id        TEXT         NULL
);

CREATE INDEX IF NOT EXISTS outbox_events_unpublished_idx
    ON outbox_events (occurred_at)
    WHERE published_at IS NULL;

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS outbox_events;

-- +goose StatementEnd
