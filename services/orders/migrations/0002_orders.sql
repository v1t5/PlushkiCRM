-- +goose Up
-- +goose StatementBegin

CREATE TABLE IF NOT EXISTS orders (
    id             UUID         PRIMARY KEY,
    tenant_id      TEXT         NOT NULL DEFAULT 'default',
    channel        TEXT         NOT NULL CHECK (channel IN ('tg','pos','web')),
    customer_ref   TEXT         NOT NULL DEFAULT '',
    status         TEXT         NOT NULL CHECK (status IN ('placed','confirmed','cancelled','fulfilled')),
    total_kopecks  BIGINT       NOT NULL CHECK (total_kopecks >= 0),
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS orders_tenant_customer_idx
    ON orders (tenant_id, customer_ref, created_at DESC);

CREATE INDEX IF NOT EXISTS orders_tenant_status_idx
    ON orders (tenant_id, status, created_at DESC);

CREATE TABLE IF NOT EXISTS order_items (
    order_id              UUID         NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    line_no               INTEGER      NOT NULL,
    product_id            UUID         NOT NULL,
    name_snapshot         TEXT         NOT NULL,
    sku_snapshot          TEXT         NOT NULL,
    price_kopecks_snapshot BIGINT      NOT NULL CHECK (price_kopecks_snapshot >= 0),
    qty                   INTEGER      NOT NULL CHECK (qty > 0),
    PRIMARY KEY (order_id, line_no)
);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;

-- +goose StatementEnd
