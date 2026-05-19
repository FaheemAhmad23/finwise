# FinWise — Supabase + Vercel Deployment Guide

This guide walks you through deploying FinWise to Vercel with Supabase as your PostgreSQL database. This is the **recommended production setup**.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Vercel (Frontend)                      │
│                  Next.js 15 + TypeScript                    │
│              Auto-scaling, global CDN, SSL/TLS              │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTPS
                           ▼
┌──────────────────────────────────────────────────────────────┐
│               Supabase (PostgreSQL + Auth)                  │
│         postgres://[project].supabase.co:5432               │
│    Auto-backups, Row-Level Security, Real-time APIs        │
└──────────────────────────────────────────────────────────────┘
```

---

## Prerequisites

- ✅ GitHub account
- ✅ Vercel account (free tier is fine)
- ✅ Supabase account (free tier is fine)
- ✅ This FinWise repository

---

## Step 1 — Set Up Supabase PostgreSQL Database

### 1.1 Create a Supabase Project

1. Go to [supabase.com](https://supabase.com)
2. Sign in or create a free account
3. Click **"New Project"** and fill in:
   - **Project name:** `finwise` (or your choice)
   - **Database password:** Use a strong password (save this!)
   - **Region:** Pick the region closest to your users (e.g., `us-east-1` for US)
4. Wait 2-3 minutes for the database to initialize

### 1.2 Get Your Database Credentials

Once the project is created:

1. Go to **Settings → Database**
2. Copy the connection string under "Connection string" → **Postgres URI** (looks like: `postgresql://postgres:[PASSWORD]@[PROJECT].supabase.co:5432/postgres`)
3. You'll use this in the next steps

### 1.3 Create Database Tables

1. In Supabase, go to the **SQL Editor** (left sidebar)
2. Click **"New query"** and copy-paste the SQL from `/prisma/schema.prisma` (or use Prisma migrations):

```bash
cd finwise
pnpm exec prisma migrate deploy
```

This creates all necessary tables automatically.

---

## Step 2 — Push Code to GitHub

### 2.1 Create a GitHub Repository

1. Go to [github.com/new](https://github.com/new)
2. Create a new repository called `finwise`
3. Push your code:

```bash
cd finwise
git remote add origin https://github.com/YOUR_USERNAME/finwise.git
git branch -M main
git push -u origin main
```

---

## Step 3 — Deploy to Vercel

### 3.1 Import from GitHub

1. Go to [vercel.com/new](https://vercel.com/new)
2. Click **"Import Git Repository"**
3. Select your `finwise` repository
4. Click **"Import"**

### 3.2 Set Environment Variables

Before clicking "Deploy", add your environment variables:

| Variable | Value | Where to find it |
|----------|-------|------------------|
| `DATABASE_URL` | `postgresql://postgres:[PASSWORD]@[PROJECT].supabase.co:5432/postgres` | Supabase → Settings → Database |
| `POSTGRES_URL` | Same as above | Supabase → Settings → Database |
| `POSTGRES_PRISMA_URL` | Same as above | Supabase → Settings → Database |
| `POSTGRES_URL_NON_POOLING` | Same as above | Supabase → Settings → Database |
| `NEXT_PUBLIC_SUPABASE_URL` | `https://[PROJECT].supabase.co` | Supabase → Settings → API |
| `NEXT_PUBLIC_SUPABASE_ANON_KEY` | The public anon key | Supabase → Settings → API |
| `SUPABASE_SERVICE_ROLE_KEY` | The service role key | Supabase → Settings → API |
| `NEXTAUTH_SECRET` | Generate with: `openssl rand -base64 32` | Generate it yourself |
| `NEXTAUTH_URL` | `https://yourdomain.vercel.app` | Will be your Vercel URL |
| `GOOGLE_CLIENT_ID` | (optional) | Google Cloud Console |
| `GOOGLE_CLIENT_SECRET` | (optional) | Google Cloud Console |

### 3.3 Deploy

Click **"Deploy"** and wait 3-5 minutes. Once complete, you'll get a live URL like `https://finwise-abc123.vercel.app`.

---

## Step 4 — Verify the Deployment

1. Open your Vercel URL in a browser
2. You should see the FinWise login page
3. Click **"Create one free"** to create an account
4. Test the app (add transactions, create debts, etc.)

---

## Step 5 — Optional: Set Up a Custom Domain

1. In Vercel project settings, go to **Domains**
2. Add your custom domain (e.g., `finwise.yourdomain.com`)
3. Update your DNS records according to Vercel's instructions
4. Update `.env` on Vercel:
   - `NEXTAUTH_URL=https://finwise.yourdomain.com`

---

## Step 6 — Database Backups

Supabase automatically backs up your database daily. To manually backup:

1. Go to Supabase → **Database → Backups**
2. Click **"Create backup"**

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Build fails with "DATABASE_URL not set" | Add all DATABASE_URL env vars in Vercel → Settings → Environment Variables |
| Login page shows blank | Check browser console for errors. Common: NEXTAUTH_SECRET not set |
| Transactions not saving | Check Supabase database — run a test query in SQL Editor |
| 502 Bad Gateway | Usually means Supabase connection failed. Check DATABASE_URL in Vercel env vars |
| "NEXTAUTH_URL mismatch" | Make sure NEXTAUTH_URL in Vercel matches your deployment URL (with https://) |

---

## Local Development

To develop locally with this Supabase setup:

1. Copy `.env.local.example` to `.env.local`
2. Fill in your Supabase credentials
3. Run: `pnpm dev`
4. Open [http://localhost:3000](http://localhost:3000)

---

## Next Steps

- ✅ Add Google OAuth (optional) — see GOOGLE_SETUP.md
- ✅ Enable Row-Level Security in Supabase for multi-tenant isolation
- ✅ Set up monitoring with Sentry or Vercel Analytics
- ✅ Configure custom branding and domain

---

## Helpful Links

- [Vercel Docs](https://vercel.com/docs)
- [Supabase Docs](https://supabase.com/docs)
- [Next.js 15 Docs](https://nextjs.org/docs)
- [Prisma Docs](https://prisma.io/docs)

