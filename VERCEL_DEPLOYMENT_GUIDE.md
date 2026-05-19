# Deploy FinWise to Vercel with Supabase

This guide walks you through deploying your FinWise app to Vercel with Supabase as the database.

**Time Required**: ~30 minutes  
**Cost**: Free (Vercel & Supabase both have generous free tiers)

---

## Prerequisites ✓

Before starting, you should have:
- ✅ Supabase project created (mqfalhberxjdzzpzenav)
- ✅ GitHub account (to push code)
- ✅ Vercel account (https://vercel.com/signup)
- ✅ Project credentials:
  - Database password: `R0tYeelRkI3bT4y4`
  - Project URL: `https://mqfalhberxjdzzpzenav.supabase.co`
  - Publishable Key: `sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss`

---

## Phase 1: Get Remaining Credentials (5 minutes)

### Get Service Role Key

1. Go to https://app.supabase.com/projects
2. Click **mqfalhberxjdzzpzenav**
3. Click **Settings** (⚙️ icon, bottom left)
4. Click **API**
5. Find **"service_role"** key under "Your API keys"
6. Copy it and save it somewhere safe (treat as a password)

Example format: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`

### Get JWT Secret

1. While in Settings → API, scroll down to find **JWT secret** (often labeled as "jwt_secret" or similar)
2. Copy it and save it

---

## Phase 2: Push Code to GitHub (5 minutes)

Your code is already set up. Push it to GitHub:

```bash
# Navigate to your project
cd /path/to/finwise

# Initialize git if not already done
git init
git add .
git commit -m "Initial commit: FinWise with Supabase"

# Add remote and push
git remote add origin https://github.com/YOUR_USERNAME/finwise.git
git branch -M main
git push -u origin main
```

> If you're using v0's GitHub integration, this may already be done!

---

## Phase 3: Deploy to Vercel (10 minutes)

### Step 1: Create Vercel Project

1. Go to https://vercel.com/dashboard
2. Click **"Add New..."** → **"Project"**
3. Select your GitHub repository: **finwise**
4. Click **Import**

### Step 2: Configure Environment Variables

Before deploying, add all environment variables:

Click **Environment Variables** and add each one:

**Database URLs:**
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
```

**Supabase Configuration:**
```
SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
NEXT_PUBLIC_SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
NEXT_PUBLIC_SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
SUPABASE_SERVICE_ROLE_KEY=[PASTE_YOUR_SERVICE_ROLE_KEY_HERE]
SUPABASE_JWT_SECRET=[PASTE_YOUR_JWT_SECRET_HERE]
```

**Authentication:**
```
NEXTAUTH_SECRET=your-secure-random-secret-here
NEXTAUTH_URL=https://your-app-name.vercel.app
```

> Generate NEXTAUTH_SECRET: `openssl rand -base64 32`

### Step 3: Deploy

1. Click **Deploy**
2. Vercel will build and deploy your app
3. This takes ~3-5 minutes

Your app will be live at: `https://your-app-name.vercel.app`

---

## Phase 4: Initialize Database (5 minutes)

Once deployed, the database schema needs to be created.

### Option A: Via Vercel CLI (Recommended)

```bash
# Install Vercel CLI if you don't have it
npm i -g vercel

# Link to your project
vercel link

# Run migrations
vercel env pull
DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres" \
npx prisma migrate deploy
```

### Option B: Via Vercel Dashboard

1. Go to your Vercel project dashboard
2. Click **Deployments**
3. Click the latest deployment
4. Open **Logs**
5. Look for database migration output

The database will be automatically initialized on first deployment. If you see any migration errors, run:

```bash
DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres" \
npx prisma db push
```

---

## Phase 5: Verify It Works (5 minutes)

### Test Your Deployment

1. Go to your Vercel URL: `https://your-app-name.vercel.app`
2. Try to sign in with Google (if configured)
3. Check the login flow works
4. Verify data is being saved to Supabase

### Verify Database Connection

1. Go to Supabase dashboard: https://app.supabase.com/projects
2. Click **mqfalhberxjdzzpzenav**
3. Click **SQL Editor**
4. Run this query to verify tables exist:

```sql
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'public';
```

You should see these tables:
- `Account`
- `Session`
- `User`
- `VerificationToken`

### Check Logs for Errors

On Vercel dashboard:
1. Click **Deployments** → Latest deployment
2. Click **Logs** tab
3. Look for any errors in red

---

## Troubleshooting

### Build fails: "DATABASE_URL not found"
- Make sure all environment variables are set in Vercel project settings
- Restart the deployment after adding variables

### Build succeeds but app shows database errors
- Check that DATABASE_URL is exactly: `postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres`
- Verify POSTGRES_PRISMA_URL includes `?schema=public`

### Can't login
- Check NEXTAUTH_SECRET is set (not empty)
- Verify NEXTAUTH_URL matches your Vercel domain
- Check Google OAuth credentials if using Google login

### Database migration stuck or failing
```bash
# Force reset (development only!)
DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres" \
npx prisma db push --force-reset
```

---

## What Gets Created

### On Vercel:
- Production URL (e.g., `finwise.vercel.app`)
- Free SSL certificate (HTTPS)
- Global CDN for fast loading
- Automatic deployments when you push to GitHub
- Environment variables securely stored

### On Supabase:
- PostgreSQL database
- 4 tables for user authentication
- Row-level security (RLS) policies
- Real-time subscriptions capability

---

## Next Steps

After deployment:

1. **Custom Domain** (optional):
   - Go to Vercel project settings
   - Click **Domains**
   - Add your custom domain

2. **Email Configuration** (optional):
   - Set up email provider for password resets
   - Configure in Supabase Auth settings

3. **Monitoring**:
   - Enable Sentry for error tracking
   - Set up uptime monitoring

---

## Support

- **Vercel Docs**: https://vercel.com/docs
- **Supabase Docs**: https://supabase.com/docs
- **NextAuth Docs**: https://next-auth.js.org
- **Prisma Docs**: https://www.prisma.io/docs

---

**Your app will be live and production-ready! 🚀**
