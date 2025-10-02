# admin-web

The operations UI for owners and staff — a Vue 3 + Vite + Pinia + vue-router single-page app. Manages
the catalog, views orders, drives the production task board, and shows reporting KPIs. It talks to the
backend only through the gateway (browser-side relative `/api/*` requests).

- **Reaches:** `identity` (auth), `catalog`, `orders`, `production`, `crm`, `reporting` via the gateway.
- **Served by:** a Caddy sidecar (static bundle) on `:80`, mounted under `/admin/`.
- **Via gateway:** `http://localhost:8080/admin/`. Dev port `:8091`.

## Auth & roles

Bearer-token auth: login posts to `/api/identity/auth/login`; tokens persist in `localStorage`
(`plushki-admin-auth-v1`). The API client auto-attaches `Authorization: Bearer …` and redirects to
`/login` on 401. Routes marked `meta.adminOnly` are gated by a router guard; write actions hide for
non-admins.

## Develop

```bash
npm install
npm run dev      # Vite dev server on http://localhost:5174/admin/, proxies /api/* to the gateway (:8080)
```

## Build / run as a container

```bash
docker compose -f compose.shared.yaml -f services/admin-web/compose.yaml up -d --build
```
