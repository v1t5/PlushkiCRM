import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// In dev (npm run dev), Vite proxies /api/* to the central Caddy gateway
// at :8080 so the SPA can use relative `/api/*` URLs and stay same-origin
// from the browser's POV. In production the SPA is served by the same
// Caddy gateway (see infra/caddy/Caddyfile fallback), so the proxy goes
// away — relative /api/* hits the same host.
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: false,
      },
    },
  },
  build: {
    target: 'es2022',
    sourcemap: true,
  },
})
