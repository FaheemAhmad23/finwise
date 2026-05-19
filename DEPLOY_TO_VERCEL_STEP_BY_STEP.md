# Deploy FinWise to Vercel - Step by Step Guide

## Overview
This guide will help you deploy FinWise to Vercel with your Supabase PostgreSQL database. The process takes about 30-45 minutes.

## Prerequisites
- ✓ Supabase project created (mqfalhberxjdzzpzenav)
- ✓ Database password saved (R0tYeelRkI3bT4y4)
- ✓ GitHub account with the repository
- ✓ Vercel account

## Your Supabase Credentials
```
Project URL:    https://mqfalhberxjdzzpzenav.supabase.co
Database Host:  db.mqfalhberxjdzzpzenav.supabase.co
Database User:  postgres
Database Pass:  R0tYeelRkI3bT4y4
Publishable Key: sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
```

---

## PHASE 1: Prepare Your Supabase Credentials (5 minutes)

### Step 1.1: Get Your Service Role Key

1. Go to https://app.supabase.com
2. Click on project: `mqfalhberxjdzzpzenav`
3. Go to **Settings** → **API** (left sidebar)
4. Find "Service Role" section
5. Copy the `Service Role Key` (starts with `eyJ...`)
6. Save it somewhere safe - you'll need it soon

### Step 1.2: Get Your JWT Secret

1. Still in Settings → API
2. Look for "JWT Secret"
3. Copy the JWT Secret value
4. Save it somewhere safe

### Step 1.3: Generate a NEXTAUTH_SECRET

Open terminal and run:
```bash
openssl rand -base64 32
```

Copy the output - this is your NEXTAUTH_SECRET

### Step 1.4: Prepare All Environment Variables

Copy all these values into a text file for quick reference:

```
DATABASE_URL=postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
POSTGRES_URL=postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
POSTGRES_PRISMA_URL=postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres?schema=public
POSTGRES_URL_NON_POOLING=postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
POSTGRES_USER=postgres
POSTGRES_PASSWORD=R0tYeelRkI3bT4y4
POSTGRES_HOST=db.mqfalhberxjdzzpzenav.supabase.co
POSTGRES_PORT=5432
POSTGRES_DATABASE=postgres

SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
NEXT_PUBLIC_SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
NEXT_PUBLIC_SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
SUPABASE_SERVICE_ROLE_KEY=[PASTE_SERVICE_ROLE_KEY_HERE]
SUPABASE_JWT_SECRET=[PASTE_JWT_SECRET_HERE]

NEXTAUTH_SECRET=[PASTE_NEXTAUTH_SECRET_HERE]
NEXTAUTH_URL=https://finwise.vercel.app

NODE_ENV=production
```

---

## PHASE 2: Push Code to GitHub (5 minutes)

### Step 2.1: Commit Your Changes

```bash
cd /path/to/finwise
git add .
git commit -m "Configure Supabase database and deployment settings"
git push origin main
```

### Step 2.2: Verify on GitHub

1. Go to https://github.com (your account)
2. Open the `finwise` repository
3. Verify the code is pushed (you should see recent commits)

---

## PHASE 3: Deploy to Vercel (15 minutes)

### Step 3.1: Create a Vercel Project

1. Go to https://vercel.com/dashboard
2. Click **"Add New"** → **"Project"**
3. Select your GitHub repository: `FaheemAhmad23/finwise`
4. Click **"Import"**

### Step 3.2: Configure Project Settings

1. Project Name: `finwise` (or your preferred name)
2. Framework: `Next.js`
3. Root Directory: `./` (default)

### Step 3.3: Add Environment Variables

Click **"Environment Variables"** and add ALL of these:

**Database URLs** (copy-paste groups):

```
KEY: DATABASE_URL
VALUE: postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
```

```
KEY: POSTGRES_URL
VALUE: postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
```

```
KEY: POSTGRES_PRISMA_URL
VALUE: postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres?schema=public
```

```
KEY: POSTGRES_URL_NON_POOLING
VALUE: postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
```

```
KEY: POSTGRES_USER
VALUE: postgres
```

```
KEY: POSTGRES_PASSWORD
VALUE: R0tYeelRkI3bT4y4
```

```
KEY: POSTGRES_HOST
VALUE: db.mqfalhberxjdzzpzenav.supabase.co
```

```
KEY: POSTGRES_PORT
VALUE: 5432
```

```
KEY: POSTGRES_DATABASE
VALUE: postgres
```

**Supabase URLs**:

```
KEY: SUPABASE_URL
VALUE: https://mqfalhberxjdzzpzenav.supabase.co
```

```
KEY: NEXT_PUBLIC_SUPABASE_URL
VALUE: https://mqfalhberxjdzzpzenav.supabase.co
```

```
KEY: SUPABASE_ANON_KEY
VALUE: sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
```

```
KEY: NEXT_PUBLIC_SUPABASE_ANON_KEY
VALUE: sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
```

```
KEY: SUPABASE_SERVICE_ROLE_KEY
VALUE: [PASTE_FROM_SUPABASE_SETTINGS_API]
```

```
KEY: SUPABASE_JWT_SECRET
VALUE: [PASTE_FROM_SUPABASE_SETTINGS_API]
```

**NextAuth**:

```
KEY: NEXTAUTH_SECRET
VALUE: [PASTE_FROM_OPENSSL_OUTPUT]
```

```
KEY: NEXTAUTH_URL
VALUE: https://finwise.vercel.app
```

(After deployment, Vercel will give you the final URL, and you can update this)

```
KEY: NODE_ENV
VALUE: production
```

### Step 3.4: Deploy

Click **"Deploy"** button

Vercel will now:
- Pull your code from GitHub
- Install dependencies
- Build Next.js application
- Deploy to serverless functions
- Give you a live URL

**Wait 2-5 minutes for deployment to complete...**

---

## PHASE 4: Initialize Your Database (5 minutes)

After Vercel deployment succeeds:

### Step 4.1: Run Database Migrations

Open Vercel deployment logs and look for the output or run locally:

```bash
export DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres"
pnpm exec prisma db push --skip-generate
```

Or if you want to be thorough:

```bash
export DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres"
pnpm exec prisma migrate deploy
```

### Step 4.2: Verify Database Tables

1. Go to Supabase Dashboard: https://app.supabase.com
2. Click project: `mqfalhberxjdzzpzenav`
3. Go to **SQL Editor** (left sidebar)
4. Run this query:
   ```sql
   SELECT table_name 
   FROM information_schema.tables 
   WHERE table_schema = 'public';
   ```
5. You should see tables like: `User`, `Transaction`, `Account`, `Session`, etc.

---

## PHASE 5: Test Your Application (5 minutes)

### Step 5.1: Open Your Live App

1. Go to Vercel Dashboard
2. Click on your `finwise` project
3. Click the **Preview** or **Visit** button
4. Your app should open!

### Step 5.2: Test Sign Up

1. Click "Sign Up"
2. Enter email and password
3. Click "Sign Up"
4. You should be logged in and see the dashboard

### Step 5.3: Test Basic Features

- Add a transaction
- Create a budget
- Check if data is saved (refresh page - data should persist)

### Step 5.4: Check Supabase Database

1. Go to Supabase Dashboard
2. Go to **Table Editor**
3. Click on `User` table
4. You should see your newly created user!

---

## 🎉 Congratulations!

Your FinWise app is now **LIVE** on production!

Your app URL: `https://finwise.vercel.app` (or whatever Vercel assigned)

---

## Troubleshooting

### Deployment Failed?

Check the Vercel logs:
1. Go to https://vercel.com/dashboard
2. Click your `finwise` project
3. Click **"Deployments"**
4. Click the failed deployment
5. Check the **"Logs"** section for errors

### Database Connection Error?

Make sure all DATABASE_URL variables are identical and match exactly:
```
postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres
```

### Tables Not Created?

Run migrations manually:
```bash
export DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres"
pnpm exec prisma db push
```

### NextAuth Login Not Working?

1. Make sure `NEXTAUTH_SECRET` is set
2. Make sure `NEXTAUTH_URL` matches your domain
3. Check Supabase User table has your user account

---

## Next Steps

1. **Custom Domain**: Add your custom domain in Vercel settings
2. **SSL Certificate**: Vercel automatically provides free SSL
3. **Email Notifications**: Set up email for password resets
4. **Analytics**: Enable Vercel Analytics to track usage
5. **Database Backups**: Supabase automatically backs up your data daily

---

## Support

- **Vercel Docs**: https://vercel.com/docs
- **Supabase Docs**: https://supabase.com/docs
- **Next.js Docs**: https://nextjs.org/docs
- **Prisma Docs**: https://www.prisma.io/docs
