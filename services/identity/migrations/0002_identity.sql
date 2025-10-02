-- +goose Up
-- +goose StatementBegin

CREATE TABLE IF NOT EXISTS users (
    id              UUID         PRIMARY KEY,
    tenant_id       TEXT         NOT NULL DEFAULT 'default',
    email           TEXT         NOT NULL,
    password_hash   TEXT         NOT NULL,
    display_name    TEXT         NOT NULL,
    roles           TEXT[]       NOT NULL DEFAULT ARRAY['user']::TEXT[],
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    archived_at     TIMESTAMPTZ  NULL,
    UNIQUE (tenant_id, email)
);

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id              UUID         PRIMARY KEY,
    tenant_id       TEXT         NOT NULL DEFAULT 'default',
    user_id         UUID         NOT NULL,
    token_hash      TEXT         NOT NULL UNIQUE,
    expires_at      TIMESTAMPTZ  NOT NULL,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    used_at         TIMESTAMPTZ  NULL,
    revoked_at      TIMESTAMPTZ  NULL
);

CREATE INDEX IF NOT EXISTS refresh_tokens_user_idx ON refresh_tokens (user_id);

CREATE TABLE IF NOT EXISTS service_tokens (
    id              UUID         PRIMARY KEY,
    tenant_id       TEXT         NOT NULL DEFAULT 'default',
    name            TEXT         NOT NULL,
    actor_type      TEXT         NOT NULL CHECK (actor_type IN ('bot', 'service')),
    scopes          TEXT[]       NOT NULL DEFAULT ARRAY[]::TEXT[],
    token_hash      TEXT         NOT NULL UNIQUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    revoked_at      TIMESTAMPTZ  NULL,
    last_used_at    TIMESTAMPTZ  NULL
);

-- +goose StatementEnd

-- +goose Down
-- +goose StatementBegin

DROP TABLE IF EXISTS service_tokens;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS users;

-- +goose StatementEnd
