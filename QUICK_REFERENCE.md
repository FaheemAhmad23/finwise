# 🚀 FinWise Quick Deployment Reference

**Status**: ✅ Ready to Deploy  
**Time Required**: ~30-45 minutes  
**Difficulty**: Easy (just follow the steps)

---

## Your Credentials (Don't Share!)

```
Supabase Project: mqfalhberxjdzzpzenav
Database Password: R0tYeelRkI3bT4y4
Supabase URL: https://mqfalhberxjdzzpzenav.supabase.co
Publishable Key: sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
```

---

## 3-Step Quick Deploy

### Step 1: Get 2 Keys from Supabase (5 min)

```
1. Go to https://app.supabase.com/projects
2. Click: mqfalhberxjdzzpzenav
3. Settings → API
4. Copy "service_role" key → SUPABASE_SERVICE_ROLE_KEY
5. Copy JWT secret → SUPABASE_JWT_SECRET
6. Run: openssl rand -base64 32 → NEXTAUTH_SECRET
```

### Step 2: Push to GitHub (5 min)

```bash
git add .
git commit -m "Deploy to Vercel"
git push origin main
```

### Step 3: Deploy on Vercel (20 min)

```
1. https://vercel.com/dashboard
2. Add New → Project
3. Select: finwise repo
4. Environment Variables: Add ALL from DEPLOYMENT_FINAL_CHECKLIST.md
5. Deploy!
```

---

## Environment Variables to Add

### Copy-Paste Block 1: Database URLs
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

### Copy-Paste Block 2: Supabase Keys
```
SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
NEXT_PUBLIC_SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
NEXT_PUBLIC_SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
SUPABASE_SERVICE_ROLE_KEY=[PASTE_SERVICE_ROLE_KEY]
SUPABASE_JWT_SECRET=[PASTE_JWT_SECRET]
```

### Copy-Paste Block 3: Auth & URL
```
NEXTAUTH_SECRET=[PASTE_YOUR_GENERATED_SECRET]
NEXTAUTH_URL=https://your-project.vercel.app
```

---

## After Deployment

### Initialize Database
```bash
npm i -g vercel
vercel link
vercel env pull
DATABASE_URL="postgresql://postgres:R0tYeelRkI3bT4y4@db.mqfalhberxjdzzpzenav.supabase.co:5432/postgres" npx prisma migrate deploy
```

### Test It Works
- [ ] App loads: `https://your-project.vercel.app`
- [ ] No errors in Vercel logs
- [ ] Can navigate pages
- [ ] Login flow works

---

## Detailed Guides

- **Full Vercel Walkthrough**: `VERCEL_DEPLOYMENT_GUIDE.md`
- **Complete Checklist**: `DEPLOYMENT_FINAL_CHECKLIST.md`
- **Supabase Setup**: `SUPABASE_SETUP_GUIDE.md`
- **All Issues Fixed**: `ISSUES_FIXED_AND_REQUIREMENTS.md`

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Can't find service role key | Make sure you're logged into Supabase, in Settings → API section |
| Build fails with env error | Did you add ALL the DATABASE_URL variables to Vercel? |
| Database connection fails | Use exact password: `R0tYeelRkI3bT4y4` with exact host: `db.mqfalhberxjdzzpzenav.supabase.co` |
| Stuck on deployment | Check Vercel logs tab under Deployments |

---

## Need Help?

- Stuck? Read `VERCEL_DEPLOYMENT_GUIDE.md` - it has detailed screenshots
- Database issue? See `SUPABASE_SETUP_GUIDE.md`
- Need everything? Check `DEPLOYMENT_FINAL_CHECKLIST.md`

---

**Your app will be live in ~30 minutes! 🎉**
