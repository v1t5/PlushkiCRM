-- +goose Up
-- +goose StatementBegin

-- Customers carry a stable internal id. External identities (TG handle,
-- phone, email, the per-tenant POS walk-in marker) live in
-- customer_identities, so a customer can pick up a new contact channel
-- without losing their loyalty history.
CREATE TABLE IF NOT EXISTS customers (
    id            UUID         PRIMARY KEY,
    tenant_id     TEXT         NOT NULL DEFAULT 'default',
    display_name  TEXT         NOT NULL DEFAULT '',
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS customers_tenant_idx ON customers (tenant_id);

-- Identity types Phase 3 cares about: 'tg' (Telegram chat id), 'phone'
-- (E.164), 'email', 'pos_walkin' (sentinel for unattributed cafe sales).
-- The unique key is on (tenant_id, type, value): two tenants may both
-- have a customer reachable by phone +49…, but within a tenant one phone
-- belongs to at most one customer.
CREATE TABLE IF NOT EXISTS customer_identities (
    id           UUID         PRIMARY KEY,
    tenant_id    TEXT         NOT NULL DEFAULT 'default',
    customer_id  UUID         NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    type         TEXT         NOT NULL CHECK (type IN ('tg','phone','email','pos_walkin')),
    value        TEXT         NOT NULL,
    verified_at  TIMESTAMPTZ  NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, type, value)
);

CREATE INDEX IF NOT EXISTS customer_identities_customer_idx
    ON customer_identities (customer_id);

-- One loyalty row per customer. Counters bump in the same txn that records
-- an applied_order_events row so redelivery can't double-credit.
CREATE TABLE IF NOT EXISTS loyalty (
    customer_id    UUID         PRIMARY KEY REFERENCES customers(id) ON DELETE CASCADE,
    tenant_id      TEXT         NOT NULL DEFAULT 'default',
    visit_count    INTEGER      NOT NULL DEFAULT 0 CHECK (visit_count >= 0),
    total_kopecks  BIGINT       NOT NULL DEFAULT 0 CHECK (total_kopecks >= 0),
    last_visit_at  TIMESTAMPTZ  NULL,
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Idempotency for orders.v1.fulfilled: each fulfilled order applies once.
-- Unlike production / inventory we don't fan out per-line — loyalty is one
-- bump per order — so the PK is just event_id. The recorded customer_id
-- helps debug "which order rolled into which customer".
CREATE TABLE IF NOT EXISTS applied_order_events (
    event_id       UUID         PRIMARY KEY,
    customer_id    UUID         NOT NULL,
    order_id       UUID         NOT NULL,
    total_kopecks  BIGINT       NOT NULL,
    applied_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS applied_order_events;
DROP TABLE IF EXISTS loyalty;
DROP TABLE IF EXISTS customer_identities;
DROP TABLE IF EXISTS customers;

-- +goose StatementEnd
