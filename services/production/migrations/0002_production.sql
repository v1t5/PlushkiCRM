-- +goose Up
-- +goose StatementBegin

-- Plans accumulate per (tenant, plan_date). When the day's accumulation is
-- frozen via POST /v1/plans/{date}/publish the plan flips to 'published',
-- one task is materialised per plan_item, and production.v1.plan_published
-- is emitted. The accumulation phase is idempotent against orders.v1.confirmed
-- redelivery via applied_order_lines.
CREATE TABLE IF NOT EXISTS plans (
    id            UUID         PRIMARY KEY,
    tenant_id     TEXT         NOT NULL DEFAULT 'default',
    plan_date     DATE         NOT NULL,
    status        TEXT         NOT NULL CHECK (status IN ('draft','published')) DEFAULT 'draft',
    published_at  TIMESTAMPTZ  NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS plans_tenant_date_unique
    ON plans (tenant_id, plan_date);

-- One row per (plan, product). Updated incrementally as orders.v1.confirmed
-- events accumulate; frozen at publish time.
CREATE TABLE IF NOT EXISTS plan_items (
    id          UUID         PRIMARY KEY,
    plan_id     UUID         NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    product_id  UUID         NOT NULL,
    qty         INTEGER      NOT NULL CHECK (qty > 0),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (plan_id, product_id)
);

-- Tasks: one per plan_item generated at publish time. FSM:
-- open → in_progress → completed
-- open → cancelled, in_progress → cancelled. Terminal: completed, cancelled.
CREATE TABLE IF NOT EXISTS tasks (
    id            UUID         PRIMARY KEY,
    tenant_id     TEXT         NOT NULL DEFAULT 'default',
    plan_id       UUID         NOT NULL REFERENCES plans(id) ON DELETE RESTRICT,
    product_id    UUID         NOT NULL,
    qty           INTEGER      NOT NULL CHECK (qty > 0),
    status        TEXT         NOT NULL CHECK (status IN ('open','in_progress','completed','cancelled')) DEFAULT 'open',
    baker_id      UUID         NULL,
    started_at    TIMESTAMPTZ  NULL,
    completed_at  TIMESTAMPTZ  NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS tasks_status_idx ON tasks (status, plan_id);
CREATE INDEX IF NOT EXISTS tasks_plan_idx ON tasks (plan_id);

-- Idempotency for orders.v1.confirmed processing — tracking per-line so
-- redelivery doesn't double-count. The unique key is (event_id, product_id)
-- because a single confirm event carries multiple item lines and we record
-- each one as a row.
CREATE TABLE IF NOT EXISTS applied_order_lines (
    event_id    UUID         NOT NULL,
    product_id  UUID         NOT NULL,
    qty         INTEGER      NOT NULL,
    plan_date   DATE         NOT NULL,
    applied_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    PRIMARY KEY (event_id, product_id)
);

-- Local projection of catalog product recipes (BOM). Populated by the
-- catalog.v1.recipe_updated consumer. Lines are stored as JSONB so the
-- task_completed event payload can attach the snapshot in one read. We
-- don't need to join against catalog at task completion time.
CREATE TABLE IF NOT EXISTS recipe_projection (
    product_id   UUID         PRIMARY KEY,
    tenant_id    TEXT         NOT NULL DEFAULT 'default',
    product_sku  TEXT         NOT NULL,
    -- JSONB array of {ingredient_id, ingredient_sku, ingredient_name,
    -- qty, unit_id, unit_code, unit_factor, qty_in_base}
    lines        JSONB        NOT NULL,
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS recipe_projection;
DROP TABLE IF EXISTS applied_order_lines;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS plan_items;
DROP TABLE IF EXISTS plans;

-- +goose StatementEnd
