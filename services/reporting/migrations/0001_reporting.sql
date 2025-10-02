-- +goose Up
-- +goose StatementBegin

-- applied_events is a single shared idempotency table. Broker redelivery
-- replays the same envelope.event_id; every consumer's projection txn
-- inserts here first, and the unique-violation aborts the rest of the txn
-- as a no-op. Cheaper than per-projection applied_* tables (the snapshot
-- repo path in CRM uses the same trick).
CREATE TABLE IF NOT EXISTS applied_events (
    event_id   uuid PRIMARY KEY,
    schema     text        NOT NULL,
    applied_at timestamptz NOT NULL DEFAULT now()
);

-- sales_by_day: one row per (tenant, day, channel). Rolling totals; the
-- consumer upserts on every fulfilled envelope.
CREATE TABLE IF NOT EXISTS sales_by_day (
    tenant_id       text        NOT NULL,
    day             date        NOT NULL,
    channel         text        NOT NULL,
    order_count     bigint      NOT NULL DEFAULT 0,
    revenue_kopecks bigint      NOT NULL DEFAULT 0,
    updated_at      timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, day, channel)
);

CREATE INDEX IF NOT EXISTS sales_by_day_tenant_day_idx ON sales_by_day (tenant_id, day);

-- top_items: one row per (tenant, day, product). qty_sold and revenue
-- accumulate. SKU/name kept current at every upsert (catalog renames after
-- the fact will retro-rewrite the column, but only for days seen since the
-- rename — historical days remain frozen on their last-seen snapshot).
CREATE TABLE IF NOT EXISTS top_items (
    tenant_id       text        NOT NULL,
    day             date        NOT NULL,
    product_id      uuid        NOT NULL,
    sku             text        NOT NULL DEFAULT '',
    name            text        NOT NULL DEFAULT '',
    qty_sold        bigint      NOT NULL DEFAULT 0,
    revenue_kopecks bigint      NOT NULL DEFAULT 0,
    updated_at      timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, day, product_id)
);

CREATE INDEX IF NOT EXISTS top_items_tenant_day_qty_idx     ON top_items (tenant_id, day, qty_sold DESC);
CREATE INDEX IF NOT EXISTS top_items_tenant_day_revenue_idx ON top_items (tenant_id, day, revenue_kopecks DESC);

-- stock_low_events: append-only audit log of inventory.v1.stock_low
-- envelopes. event_id is unique to keep the table idempotent on replay.
CREATE TABLE IF NOT EXISTS stock_low_events (
    id                       uuid PRIMARY KEY,
    event_id                 uuid        NOT NULL UNIQUE,
    tenant_id                text        NOT NULL,
    ingredient_id            uuid        NOT NULL,
    sku                      text        NOT NULL DEFAULT '',
    name                     text        NOT NULL DEFAULT '',
    warehouse_id             uuid,
    threshold_qty_in_base    bigint      NOT NULL,
    current_qty_in_base      bigint      NOT NULL,
    default_unit_code        text        NOT NULL DEFAULT '',
    default_unit_factor      bigint      NOT NULL DEFAULT 1,
    occurred_at              timestamptz NOT NULL,
    applied_at               timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS stock_low_events_tenant_occurred_idx ON stock_low_events (tenant_id, occurred_at DESC);

-- movements_by_day: one row per (tenant, day, item, type). Stores the SUM
-- of qty_in_base for each movement type. WASTE percentage = waste / (waste
-- + consumed_by_production + sold + out) at query time. Item sku/name
-- come from the inventory.v1.movement_posted payload (populated for
-- ingredients, empty for products).
CREATE TABLE IF NOT EXISTS movements_by_day (
    tenant_id           text        NOT NULL,
    day                 date        NOT NULL,
    item_kind           text        NOT NULL,
    item_id             uuid        NOT NULL,
    type                text        NOT NULL,
    item_sku            text        NOT NULL DEFAULT '',
    item_name           text        NOT NULL DEFAULT '',
    total_qty_in_base   bigint      NOT NULL DEFAULT 0,
    updated_at          timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, day, item_kind, item_id, type)
);

CREATE INDEX IF NOT EXISTS movements_by_day_tenant_day_type_idx ON movements_by_day (tenant_id, day, type);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin
DROP TABLE IF EXISTS movements_by_day;
DROP TABLE IF EXISTS stock_low_events;
DROP TABLE IF EXISTS top_items;
DROP TABLE IF EXISTS sales_by_day;
DROP TABLE IF EXISTS applied_events;
-- +goose StatementEnd
