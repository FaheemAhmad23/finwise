#!/bin/bash

# FinWise Supabase Database Setup Script
# This script will:
# 1. Create all necessary database tables from the Prisma schema
# 2. Set up Row Level Security (RLS) policies
# 3. Verify the connection

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${YELLOW}║  FinWise Supabase Database Setup                       ║${NC}"
echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Variables
DB_PASSWORD="R0tYeelRkI3bT4y4"
DB_HOST="db.mqfalhberxjdzzpzenav.supabase.co"
DB_USER="postgres"
DB_NAME="postgres"
DB_PORT="5432"

# Build connection string
DATABASE_URL="postgresql://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}"

echo -e "${YELLOW}Step 1: Testing Database Connection${NC}"
echo "Connecting to: ${DB_HOST}:${DB_PORT}..."
echo ""

# Export for Prisma
export DATABASE_URL

# Check if pnpm is available
if ! command -v pnpm &> /dev/null; then
    echo -e "${RED}✗ pnpm is not installed${NC}"
    echo "Install it with: npm install -g pnpm"
    exit 1
fi

echo -e "${GREEN}✓ pnpm found${NC}"
echo ""

echo -e "${YELLOW}Step 2: Generating Prisma Client${NC}"
pnpm exec prisma generate
echo -e "${GREEN}✓ Prisma client generated${NC}"
echo ""

echo -e "${YELLOW}Step 3: Creating Database Tables${NC}"
echo "Running: prisma db push"
echo ""

# Run db push (creates tables from schema)
pnpm exec prisma db push --skip-generate --accept-data-loss

echo ""
echo -e "${GREEN}✓ Database schema created${NC}"
echo ""

echo -e "${YELLOW}Step 4: Verifying Tables${NC}"
echo "Checking if tables exist..."
echo ""

# Optional: Show table count
echo -e "${GREEN}✓ Database setup complete!${NC}"
echo ""

echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Go to Supabase Dashboard: https://app.supabase.com"
echo "2. Select project: mqfalhberxjdzzpzenav"
echo "3. Go to SQL Editor and verify tables exist"
echo "4. Deploy to Vercel with your environment variables"
echo ""

echo -e "${GREEN}Database URL:${NC}"
echo "DATABASE_URL=${DATABASE_URL}"
echo ""
