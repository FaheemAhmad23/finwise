# Supabase Setup Guide for FinWise

## Your Project Details
- **Project URL**: https://mqfalhberxjdzzpzenav.supabase.co
- **Project Ref**: mqfalhberxjdzzpzenav
- **Status**: ✅ Ready to configure

---

## Step 1: Get Your Service Role Key (2 minutes)

Your publishable key is already provided. Now we need the **Service Role Key**:

1. Go to [https://app.supabase.com/projects](https://app.supabase.com/projects)
2. Click on your project: **mqfalhberxjdzzpzenav**
3. Go to **Settings** (⚙️ icon, bottom left)
4. Click **API**
5. Find the **"service_role" key** under "Your API keys"
6. Copy it (this is sensitive - treat like a password!)

---

## Step 2: Generate NEXTAUTH_SECRET (1 minute)

Run this command to generate a secure secret:

```bash
openssl rand -base64 32
```

Copy the output - you'll need it in Step 3.

---

## Step 3: Set Environment Variables (3 minutes)

In your Vercel project settings, add these environment variables:

### Database Configuration
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

### Supabase Configuration
```
SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
NEXT_PUBLIC_SUPABASE_URL=https://mqfalhberxjdzzpzenav.supabase.co
SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
NEXT_PUBLIC_SUPABASE_ANON_KEY=sb_publishable_ocdjIfHfPIgK9Z19uQsTnA_FeMnyiss
SUPABASE_SERVICE_ROLE_KEY=[PASTE_YOUR_SERVICE_ROLE_KEY_HERE]
SUPABASE_JWT_SECRET=[PASTE_YOUR_JWT_SECRET_FROM_SUPABASE_SETTINGS]
```

### Authentication
```
NEXTAUTH_SECRET=[PASTE_YOUR_GENERATED_SECRET_HERE]
NEXTAUTH_URL=https://yourdomain.vercel.app
```

---

## Step 4: Run Database Migrations

Once environment variables are set:

```bash
pnpm exec prisma migrate deploy
```

This creates all necessary tables in your Supabase PostgreSQL database.

---

## Step 5: Deploy to Vercel

1. Push code to GitHub
2. Connect to Vercel
3. Add environment variables in Vercel project settings
4. Deploy!

---

## Database Tables Created

Your Prisma schema will create these tables:
- `Account` - OAuth/login account info
- `Session` - Active user sessions
- `User` - User profiles
- `VerificationToken` - Email verification tokens

---

## Troubleshooting

### Can't connect to database?
- Check the password is exactly: `R0tYeelRkI3bT4y4`
- Verify connection string has correct host: `db.mqfalhberxjdzzpzenav.supabase.co`
- Ensure DATABASE_URL uses PostgreSQL (not MySQL)

### Prisma migrate fails?
- Run `pnpm exec prisma db push --force-reset` (only for development!)
- Check that POSTGRES_PRISMA_URL includes `?schema=public`

### Build fails on Vercel?
- Verify all DATABASE_URL environment variables are set
- Check NEXTAUTH_SECRET is set (not empty)
- Ensure SUPABASE_JWT_SECRET is set

---

## Need Help?
- Supabase Docs: https://supabase.com/docs
- Prisma Docs: https://www.prisma.io/docs
- NextAuth Docs: https://next-auth.js.org
