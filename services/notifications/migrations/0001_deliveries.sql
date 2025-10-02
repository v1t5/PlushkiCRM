-- +goose Up
-- +goose StatementBegin

-- delivered_notifications is the consumer-side dedup record for incoming
-- domain events. RabbitMQ guarantees at-least-once delivery: this row is
-- our "we already handled this event_id" marker, written after the channel
-- send succeeds (or is short-circuited in dry-run mode).
--
-- channel:    'tg' for now; sms/email/push later — column is here so adding
--             one doesn't require a migration.
-- recipient:  free-form per channel. For tg it's the chat_id as text.
-- subject:    the AMQP routing key we received the event on, for diagnostics.
-- attempt:    bumped on each retry; useful when something gets stuck and we
--             grep the table to see what's stale.
CREATE TABLE IF NOT EXISTS delivered_notifications (
    event_id     UUID         PRIMARY KEY,
    schema       TEXT         NOT NULL,
    subject      TEXT         NOT NULL,
    channel      TEXT         NOT NULL,
    recipient    TEXT         NOT NULL,
    delivered_at TIMESTAMPTZ  NOT NULL DEFAULT now(),
    attempt      INTEGER      NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS delivered_notifications_recipient_idx
    ON delivered_notifications (channel, recipient, delivered_at DESC);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS delivered_notifications;

-- +goose StatementEnd
