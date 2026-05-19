#!/bin/bash
# Automatic database migration script for Vercel

echo "🚀 FinWise Database Setup Script"
echo "================================"
echo ""
echo "This script will:"
echo "  1. Run Prisma migrations"
echo "  2. Set up your Supabase database schema"
echo "  3. Initialize necessary tables for authentication"
echo ""

# Check if DATABASE_URL is set
if [ -z "$DATABASE_URL" ]; then
    echo "❌ ERROR: DATABASE_URL environment variable is not set!"
    echo ""
    echo "Set it in your Vercel project settings before running this script."
    echo "Value should be:"
    echo "postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres"
    exit 1
fi

echo "✅ DATABASE_URL is configured"
echo ""

# Run Prisma migrations
echo "📦 Running database migrations..."
npx prisma migrate deploy

if [ $? -eq 0 ]; then
    echo "✅ Database migrations completed successfully!"
    echo ""
    echo "📊 Tables created:"
    echo "  - Account"
    echo "  - Session"
    echo "  - User"
    echo "  - VerificationToken"
    echo ""
    echo "🎉 Your Supabase database is ready!"
else
    echo "❌ Migration failed. Check the errors above."
    exit 1
fi
