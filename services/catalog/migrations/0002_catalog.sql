-- +goose Up
-- +goose StatementBegin

CREATE TABLE IF NOT EXISTS categories (
    id           UUID         PRIMARY KEY,
    tenant_id    TEXT         NOT NULL DEFAULT 'default',
    name         TEXT         NOT NULL,
    slug         TEXT         NOT NULL,
    sort_order   INTEGER      NOT NULL DEFAULT 0,
    archived_at  TIMESTAMPTZ  NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS categories_tenant_slug_unique
    ON categories (tenant_id, slug)
    WHERE archived_at IS NULL;

CREATE TABLE IF NOT EXISTS products (
    id             UUID         PRIMARY KEY,
    tenant_id      TEXT         NOT NULL DEFAULT 'default',
    category_id    UUID         NULL REFERENCES categories(id) ON DELETE RESTRICT,
    sku            TEXT         NOT NULL,
    name           TEXT         NOT NULL,
    description    TEXT         NOT NULL DEFAULT '',
    price_kopecks  BIGINT       NOT NULL CHECK (price_kopecks >= 0),
    archived_at    TIMESTAMPTZ  NULL,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS products_tenant_sku_unique
    ON products (tenant_id, sku)
    WHERE archived_at IS NULL;

CREATE INDEX IF NOT EXISTS products_tenant_category_idx
    ON products (tenant_id, category_id)
    WHERE archived_at IS NULL;

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;

-- +goose StatementEnd
