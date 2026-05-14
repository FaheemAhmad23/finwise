#!/bin/sh
set -e

echo "🔄 Syncing database schema..."
node /app/node_modules/prisma/build/index.js db push

echo "🚀 Starting FinWise..."
exec node /app/server.js
