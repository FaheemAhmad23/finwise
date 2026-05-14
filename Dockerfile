# ── Stage 1: Install dependencies ─────────────────────────────────────────────
FROM node:20-alpine AS deps
RUN apk add --no-cache libc6-compat openssl
WORKDIR /app

COPY package.json ./
# Delete any existing lock file — let npm resolve fresh from package.json
RUN npm install --legacy-peer-deps

# ── Stage 2: Generate Prisma client + Build ────────────────────────────────────
FROM node:20-alpine AS builder
RUN apk add --no-cache libc6-compat openssl
WORKDIR /app

COPY --from=deps /app/node_modules ./node_modules
COPY . .

# Use the locally installed prisma (v5) — NOT npx which downloads the latest (v7)
RUN ./node_modules/.bin/prisma generate

ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

# ── Stage 3: Production runner ─────────────────────────────────────────────────
FROM node:20-alpine AS runner
RUN apk add --no-cache libc6-compat openssl
WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1

RUN addgroup --system --gid 1001 nodejs && \
    adduser  --system --uid 1001 nextjs

# Copy standalone build
COPY --from=builder /app/public                                    ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone    ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static        ./.next/static

# Copy Prisma runtime files
COPY --from=builder /app/prisma                                    ./prisma
COPY --from=builder /app/node_modules/.prisma                      ./node_modules/.prisma
COPY --from=builder /app/node_modules/@prisma                      ./node_modules/@prisma
COPY --from=builder /app/node_modules/prisma                       ./node_modules/prisma

USER nextjs

EXPOSE 3000
ENV PORT=3000
ENV HOSTNAME="0.0.0.0"

# Use local prisma binary for migrations too
CMD ["sh", "-c", "./node_modules/prisma/bin/prisma.js migrate deploy && node server.js"]
