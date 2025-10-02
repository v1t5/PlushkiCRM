-- +goose Up
-- +goose StatementBegin

-- Inventory tracks two things: the master list of warehouses, and a running
-- ledger of stock movements per (warehouse, item). stock_levels is a
-- materialised running total maintained inside the same transaction as the
-- movement row, so reads are O(1).
--
-- Quantities are BIGINT in *base units* of the item's default unit
-- (ingredient.default_unit_id or product's implicit "pcs" unit). Catalog
-- emits each unit's factor on recipe_updated so we never have to call back
-- for conversion.

CREATE TABLE IF NOT EXISTS warehouses (
    id           UUID         PRIMARY KEY,
    tenant_id    TEXT         NOT NULL DEFAULT 'default',
    code         TEXT         NOT NULL,
    name         TEXT         NOT NULL,
    archived_at  TIMESTAMPTZ  NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS warehouses_tenant_code_unique
    ON warehouses (tenant_id, code)
    WHERE archived_at IS NULL;

-- TEXT + CHECK constraints rather than Postgres ENUMs — keeps scans
-- trivial (string in, string out) and lets us add new movement types in a
-- regular migration without ALTER TYPE pain.
CREATE TABLE IF NOT EXISTS stock_levels (
    tenant_id     TEXT         NOT NULL DEFAULT 'default',
    warehouse_id  UUID         NOT NULL REFERENCES warehouses(id) ON DELETE RESTRICT,
    item_kind     TEXT         NOT NULL CHECK (item_kind IN ('ingredient','product')),
    item_id       UUID         NOT NULL,
    qty_in_base   BIGINT       NOT NULL DEFAULT 0,
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    PRIMARY KEY (warehouse_id, item_kind, item_id)
);

CREATE INDEX IF NOT EXISTS stock_levels_item_idx
    ON stock_levels (item_kind, item_id);

CREATE TABLE IF NOT EXISTS stock_movements (
    id              UUID         PRIMARY KEY,
    tenant_id       TEXT         NOT NULL DEFAULT 'default',
    warehouse_id    UUID         NOT NULL REFERENCES warehouses(id) ON DELETE RESTRICT,
    item_kind       TEXT         NOT NULL CHECK (item_kind IN ('ingredient','product')),
    item_id         UUID         NOT NULL,
    type            TEXT         NOT NULL CHECK (type IN ('IN','OUT','WASTE','ADJUST','CONSUMED_BY_PRODUCTION','SOLD')),
    -- Signed delta in base units. IN / ADJUST-up: positive. OUT / WASTE /
    -- CONSUMED_BY_PRODUCTION / SOLD / ADJUST-down: negative. Stock-levels
    -- accumulator just adds qty_in_base.
    qty_in_base     BIGINT       NOT NULL,
    reason          TEXT         NOT NULL DEFAULT '',
    -- For event-sourced movements (orders.v1.fulfilled, production.v1.task_completed)
    -- this is the originating envelope event_id. The (source_event_id, item_kind,
    -- item_id) unique index makes redelivery a no-op. Manual API movements leave it NULL.
    source_event_id UUID         NULL,
    occurred_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS stock_movements_item_idx
    ON stock_movements (item_kind, item_id, occurred_at DESC);

CREATE UNIQUE INDEX IF NOT EXISTS stock_movements_source_event_unique
    ON stock_movements (source_event_id, item_kind, item_id)
    WHERE source_event_id IS NOT NULL;

-- Local projection of catalog ingredients. Populated by the
-- catalog.v1.ingredient_created consumer. We only need enough to do
-- low-stock comparisons in base units and produce a useful notification
-- payload — sku/name + threshold-in-base. Drift between this projection
-- and catalog is acceptable because catalog is the source of truth and
-- redelivers events on resub.
CREATE TABLE IF NOT EXISTS ingredient_projection (
    ingredient_id        UUID         PRIMARY KEY,
    tenant_id            TEXT         NOT NULL DEFAULT 'default',
    sku                  TEXT         NOT NULL,
    name                 TEXT         NOT NULL,
    default_unit_code    TEXT         NOT NULL,
    default_unit_factor  BIGINT       NOT NULL,
    threshold_qty_in_base BIGINT      NOT NULL DEFAULT 0,
    updated_at           TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS ingredient_projection;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS stock_levels;
DROP TABLE IF EXISTS warehouses;

-- +goose StatementEnd
