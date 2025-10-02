# Build stage: vite + vue-tsc compile to a static dist/.
FROM node:20-alpine AS build
WORKDIR /src

# Install deps first so the layer caches across source-only edits.
# --include=dev is the npm 9+ default but stated explicitly so a stray
# NODE_ENV=production never silently strips devDependencies (vue-tsc,
# vite, tailwind plugins all live there).
COPY package.json package-lock.json* ./
# Long fetch timeout + many retries: a single ECONNRESET on a heavy
# tarball can otherwise stall the whole install for the default 5min
# socket timeout, which we've seen lock the build up for 15+ minutes.
RUN npm config set fetch-timeout 120000 \
 && npm config set fetch-retry-mintimeout 10000 \
 && npm config set fetch-retry-maxtimeout 60000 \
 && npm config set fetch-retries 5 \
 && if [ -f package-lock.json ]; then \
      npm ci --include=dev --no-audit --no-fund; \
    else \
      npm install --include=dev --no-audit --no-fund; \
    fi \
 && ls node_modules/.bin/vue-tsc node_modules/.bin/vite

COPY . .
RUN npm run build

# Serve stage: Caddy sidecar serves the static bundle. SPA fallback so
# any path not matching a built asset returns index.html — important
# for client-side routing later (Phase 5+ admin routes).
FROM caddy:2.8-alpine
COPY --from=build /src/dist /srv
COPY Caddyfile /etc/caddy/Caddyfile
EXPOSE 80
