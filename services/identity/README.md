# identity

The auth provider: users, roles, sessions (refresh tokens), RS256 access JWTs, JWKS, and opaque
service tokens.

## Layout

| Layer | Contents |
|---|---|
| `src/Domain` | `User`, `RefreshToken`, `ServiceToken`, `ErrorCode`, `DomainException` |
| `src/Ports` | interfaces + `UserListParams`, `OutboxEvent` |
| `src/App` | `AuthService`, `MeService`, `IntrospectService`, `CreateService`, `UserAdminService`, `JwtIssuer`, … |
| `src/Adapters/Http` | controllers, DTOs, `AuthSubscriber`, exception mapping |
| `src/Adapters/Db` | DBAL repositories, hand-written SQL |
| `src/Platform/Events` | `OutboxRelay`, driven by `plushki:outbox-relay` |
| `src/Kernel.php` | wire-up + the migrate / bootstrap / relay console workers |

## Routes (served at the service root; gateway strips `/api/identity`)

```
GET  /.well-known/jwks.json          public
POST /auth/register                  public
POST /auth/login                     public
POST /auth/refresh                   public
POST /auth/introspect                public (gateway → service-token check)
GET  /me                             requires a valid access JWT
GET  /admin/users   (+ POST)         admin role
GET|PATCH /admin/users/{id}          admin role
PUT  /admin/users/{id}/roles         admin role
PUT  /admin/users/{id}/password      admin role
POST /admin/users/{id}/archive       admin role
POST /admin/users/{id}/restore       admin role
POST /admin/service-tokens           dev-only (gateway-gated in prod)
GET  /healthz  /readyz  /metrics     platform
```

## Notes

- UUIDs are v7 (`symfony/uid`). Passwords: bcrypt cost 10 (`password_hash`). Service tokens: argon2id
  (`password_hash`, m=64MiB/t=1/p=2). Refresh tokens: SHA-256 hex of a base64url secret.
- Events: `identity.v1.user_created` is written to `outbox_events` in the same transaction as the user
  row (`OutboxRepo::insertWithUser`) and published by the relay to the `IDENTITY` topic exchange with
  routing key `identity.v1.user_created.default`.
- **JWT dev key:** with no `APP_JWT_PRIVATE_KEY_PATH`, dev persists a generated RSA key under
  `var/jwt/dev-key.pem` so tokens validate across the stateless request-per-boot PHP model. Clearing
  `var/` rotates it. In prod, set `APP_JWT_PRIVATE_KEY_PATH`.

## Run

```bash
docker compose -f compose.shared.yaml -f services/identity/compose.yaml up -d --build
curl -s localhost:8080/api/identity/.well-known/jwks.json    # via gateway
curl -s localhost:8081/healthz                               # direct (dev port)
```
