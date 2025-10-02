-- +goose Up
-- +goose StatementBegin

-- Quantities are stored as BIGINT in the unit named on the row (no floats,
-- no decimals — kopecks-style integer math). To convert any qty to its base
-- unit: qty_in_base = qty * units.factor. Choose the smallest sensible unit
-- on input ("250 g", not "0.25 kg") so a quantity always fits an int64.

CREATE TABLE IF NOT EXISTS units (
    id            UUID         PRIMARY KEY,
    tenant_id     TEXT         NOT NULL DEFAULT 'default',
    code          TEXT         NOT NULL,
    name          TEXT         NOT NULL,
    base_unit_id  UUID         NULL REFERENCES units(id) ON DELETE RESTRICT,
    factor        BIGINT       NOT NULL CHECK (factor > 0),
    archived_at   TIMESTAMPTZ  NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    -- A base unit's factor is 1 by definition; non-base units must point at a base.
    CHECK ((base_unit_id IS NULL AND factor = 1) OR (base_unit_id IS NOT NULL))
);

CREATE UNIQUE INDEX IF NOT EXISTS units_tenant_code_unique
    ON units (tenant_id, code)
    WHERE archived_at IS NULL;

CREATE TABLE IF NOT EXISTS ingredients (
    id                       UUID         PRIMARY KEY,
    tenant_id                TEXT         NOT NULL DEFAULT 'default',
    sku                      TEXT         NOT NULL,
    name                     TEXT         NOT NULL,
    default_unit_id          UUID         NOT NULL REFERENCES units(id) ON DELETE RESTRICT,
    -- Threshold is expressed in default_unit — easier on the admin UI. Inventory
    -- multiplies by default_unit.factor when comparing against base-unit stock.
    low_stock_threshold_qty  BIGINT       NOT NULL DEFAULT 0 CHECK (low_stock_threshold_qty >= 0),
    archived_at              TIMESTAMPTZ  NULL,
    created_at               TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS ingredients_tenant_sku_unique
    ON ingredients (tenant_id, sku)
    WHERE archived_at IS NULL;

-- One row per (product, ingredient). Recipes are replaced wholesale by PUT,
-- so we don't carry per-line timestamps — created_at/updated_at on the
-- product change when the recipe changes (caller's concern).
CREATE TABLE IF NOT EXISTS recipe_lines (
    id              UUID         PRIMARY KEY,
    product_id      UUID         NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    ingredient_id   UUID         NOT NULL REFERENCES ingredients(id) ON DELETE RESTRICT,
    qty             BIGINT       NOT NULL CHECK (qty > 0),
    unit_id         UUID         NOT NULL REFERENCES units(id) ON DELETE RESTRICT,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (product_id, ingredient_id)
);

CREATE INDEX IF NOT EXISTS recipe_lines_product_idx ON recipe_lines (product_id);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS recipe_lines;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS units;

-- +goose StatementEnd
