# FinWise Deployment Checklist

Use this checklist to deploy your app step-by-step. Check off each item as you complete it.

---

## Your Supabase Details (Save These!)

```
Project: mqfalhberxjdzzpzenav
URL: https://mqfalhberxjdzzpzenav.supabase.co
Database Host: db.mqfalhberxjdzzpzenav.supabase.co
Database Password: R0tYeelRkI3bT4y4
Publishable Key: sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
```

---

## Phase 1: Prepare Your Supabase Project

### Get Missing Credentials
- [ ] Go to https://app.supabase.com/projects
- [ ] Click project: **mqfalhberxjdzzpzenav**
- [ ] Go to **Settings** → **API**
- [ ] Find and copy **"service_role"** key
- [ ] Save it (will need in Vercel setup): `SUPABASE_SERVICE_ROLE_KEY=___________`
- [ ] Find and copy **JWT secret**
- [ ] Save it: `SUPABASE_JWT_SECRET=___________`

### Generate NextAuth Secret
- [ ] Run: `openssl rand -base64 32`
- [ ] Save the output: `NEXTAUTH_SECRET=___________`

---

## Phase 2: Push Code to GitHub

### Using Git Command Line
- [ ] Open terminal in your project directory
- [ ] Run: `git init` (if not already a git repo)
- [ ] Run: `git add .`
- [ ] Run: `git commit -m "Initial commit: FinWise"`
- [ ] Create new repo on GitHub.com
- [ ] Run: `git remote add origin https://github.com/YOUR_USERNAME/finwise.git`
- [ ] Run: `git branch -M main`
- [ ] Run: `git push -u origin main`

### Using v0's GitHub Integration
- [ ] Click the settings button (⚙️) in v0
- [ ] Connect to GitHub if not already connected
- [ ] Select your repository
- [ ] Changes should auto-commit

- [ ] ✅ Code is now on GitHub and ready to deploy

---

## Phase 3: Deploy to Vercel

### Create Vercel Project
- [ ] Go to https://vercel.com/dashboard
- [ ] Click **Add New...** → **Project**
- [ ] Select your GitHub repo: **finwise**
- [ ] Click **Import**

### Set Environment Variables in Vercel

**Before clicking Deploy**, add ALL these environment variables:

#### Database URLs (copy exactly as shown)
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

#### Supabase Configuration
```
SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
NEXT_PUBLIC_SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
NEXT_PUBLIC_SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
SUPABASE_SERVICE_ROLE_KEY=[paste-your-service-role-key-here]
SUPABASE_JWT_SECRET=[paste-your-jwt-secret-here]
```

#### NextAuth Configuration
```
NEXTAUTH_SECRET=[paste-your-generated-secret-here]
NEXTAUTH_URL=https://your-app-name.vercel.app
```

### Deploy to Production
- [ ] Double-check all environment variables are added
- [ ] Click **Deploy** button
- [ ] Wait for deployment to complete (3-5 minutes)
- [ ] Copy your app URL: `https://___________vercel.app`
- [ ] ✅ Your app is now live!

---

## Phase 4: Initialize Database

After deployment completes, set up your database:

### Option A: Using Vercel CLI (Recommended)
```bash
# Install Vercel CLI
npm i -g vercel

# Link to your project
vercel link

# Pull environment variables
vercel env pull

# Run migrations
DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres" \
npx prisma migrate deploy
```

- [ ] Successfully ran `prisma migrate deploy`
- [ ] No errors in the output
- [ ] Database tables created

### Option B: Via Vercel Dashboard
- [ ] Go to your Vercel project dashboard
- [ ] Check the latest deployment logs
- [ ] Look for "Database migrations completed" message

---

## Phase 5: Verify Everything Works

### Test the Live App
- [ ] Open your Vercel URL in a browser
- [ ] Page loads without errors
- [ ] Try to navigate around
- [ ] Try authentication flow

### Verify Database Tables
- [ ] Go to https://app.supabase.com/projects
- [ ] Click **mqfalhberxjdzzpzenav**
- [ ] Click **SQL Editor**
- [ ] Run this query:
```sql
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'public';
```
- [ ] These tables exist:
  - `Account`
  - `Session`
  - `User`
  - `VerificationToken`

### Check for Errors
- [ ] No red errors in Vercel deployment logs
- [ ] App loads and functions properly
- [ ] Login flow works correctly

---

## 🎉 You're Done!

Your app is now:
- ✅ **Live** on Vercel with a production URL
- ✅ **Secure** with HTTPS/SSL
- ✅ **Fast** with global CDN
- ✅ **Scalable** with automatic deployments
- ✅ **Connected** to Supabase PostgreSQL
- ✅ **Ready** for real users

---

## What to Do Next

### Immediate (Optional)
- [ ] Set a custom domain (in Vercel settings)
- [ ] Configure Google OAuth (if not already done)
- [ ] Test user registration and login

### Soon (Optional)
- [ ] Set up error monitoring (Sentry)
- [ ] Set up analytics (Vercel Analytics)
- [ ] Configure email notifications
- [ ] Add more features to your app

### Production Best Practices
- [ ] Enable database backups in Supabase
- [ ] Set up a status page
- [ ] Configure CI/CD for automatic testing
- [ ] Add monitoring and alerting

---

## Troubleshooting Quick Reference

| Issue | Solution |
|-------|----------|
| Build fails: "DATABASE_URL not found" | Add all DATABASE_URL vars to Vercel env settings, restart deploy |
| App loads but database errors | Check DATABASE_URL is exact, verify POSTGRES_PRISMA_URL has `?schema=public` |
| Can't login | Verify NEXTAUTH_SECRET and NEXTAUTH_URL are set |
| Migration fails | Run `DATABASE_URL="..." npx prisma db push` locally, then redeploy |
| Slow performance | Check Vercel Analytics dashboard for bottlenecks |

---

## Support Resources

- **Vercel Docs**: https://vercel.com/docs
- **Supabase Docs**: https://supabase.com/docs  
- **NextAuth Docs**: https://next-auth.js.org
- **Prisma Docs**: https://www.prisma.io/docs
- **Vercel Support**: https://vercel.com/help

---

**Save this checklist! You might need it for future deployments or team onboarding.**
