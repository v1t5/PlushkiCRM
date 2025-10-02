# pos-web

The café POS — a minimal Vue 3 + Vite single-page app for ringing up walk-in sales: product grid,
cart, cash payment, on-screen receipt. It talks to the backend only through the gateway
(browser-side relative `/api/*` requests), so there is no CORS in production.

- **Reaches:** `orders` and `catalog` via the gateway.
- **Served by:** a Caddy sidecar (static bundle) on `:80`.
- **Via gateway:** `http://localhost:8080/` (the Caddyfile fallback). Dev port `:8089`.

## Develop

```bash
npm install
npm run dev      # Vite dev server on http://localhost:5173, proxies /api/* to the gateway (:8080)
```

## Build / run as a container

```bash
docker compose -f compose.shared.yaml -f services/pos-web/compose.yaml up -d --build
```
