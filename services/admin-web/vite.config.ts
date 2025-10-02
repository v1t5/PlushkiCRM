import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// admin-web is mounted at /admin/ on the central Caddy gateway (see
// infra/caddy/Caddyfile). The Vite `base` makes built asset URLs include
// the prefix; vue-router uses the same base so deep links resolve. In dev
// (`npm run dev`) Vite serves at :5174 and proxies /api/* to :8080, the
// gateway. The dev server runs at the same /admin/ base so client routing
// matches prod.
export default defineConfig({
  base: '/admin/',
  plugins: [vue(), tailwindcss()],
  server: {
    host: true,
    port: 5174,
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
