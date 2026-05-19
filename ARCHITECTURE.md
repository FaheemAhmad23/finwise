# FinWise Production Architecture

## System Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER'S BROWSER                          │
│  (Chrome, Safari, Firefox, etc.)                                │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ HTTPS Request
                             │
┌─────────────────────────────┼────────────────────────────────────┐
│                    VERCEL (Global CDN)                           │
│                    finwise.vercel.app                            │
├─────────────────────────────┼────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  Next.js 15 Application (Serverless Functions)             │ │
│  ├─────────────────────────────────────────────────────────────┤ │
│  │  Pages:                      API Routes:                     │ │
│  │  • / (Dashboard)             • /api/auth/[...nextauth]      │ │
│  │  • /login                    • /api/transactions            │ │
│  │  • /register                 • /api/people                  │ │
│  │  • /profile                  • /api/export                  │ │
│  │  • /settings                 • /api/dashboard               │ │
│  │  • /accounts                                                 │ │
│  │  • /categories                                              │ │
│  │  • /transactions                                            │ │
│  │  • /budgets                                                 │ │
│  │  • /reports                                                 │ │
│  │                                                              │ │
│  │ Framework: React 19 + TypeScript + Tailwind CSS            │ │
│  │ Auth: NextAuth.js (Session + Google OAuth ready)           │ │
│  │ Middleware: Route protection & auth checks                 │ │
│  └────────────┬──────────────────────────────────────────────┘ │
│               │                                                  │
└───────────────┼──────────────────────────────────────────────────┘
                │
                │ SQL Queries
                │ (Prisma ORM)
                │
┌───────────────┼──────────────────────────────────────────────────┐
│               │     SUPABASE (PostgreSQL Database)               │
│               │     db.mqfalhberxjdzzpzenav.supabase.co         │
├───────────────┼──────────────────────────────────────────────────┤
│               ▼                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  PostgreSQL Database                                     │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │  Tables:                                                 │  │
│  │  • Account (OAuth provider accounts)                     │  │
│  │  • Session (Login sessions)                              │  │
│  │  • User (User profiles & credentials)                    │  │
│  │  • VerificationToken (Email verification)                │  │
│  │                                                          │  │
│  │  • Accounts (Financial accounts)                         │  │
│  │  • Categories (Transaction categories)                   │  │
│  │  • Transactions (All transactions)                       │  │
│  │  • Budgets (Budget planning)                             │  │
│  │  • People (Contact management)                           │  │
│  │                                                          │  │
│  │  Storage: 500MB free tier included                       │  │
│  │  Backups: Daily (PITR available)                         │  │
│  │  RLS: Row-level security configured                      │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Also Provided by Supabase:                                      │
│  • Auth service (email/password, OAuth providers)               │
│  • Real-time subscriptions                                      │
│  • Vector embeddings (pgvector)                                 │
│  • Edge functions                                               │
│  • File storage (S3-like)                                       │
└──────────────────────────────────────────────────────────────────┘
```

---

## Data Flow

### 1. User Login Flow
```
User → Browser → Vercel (Next.js) → NextAuth → Supabase Auth
  ↓
  Credentials validated ← PostgreSQL ← User table checked
  ↓
  Session created → JWT token stored in secure cookie
  ↓
  Redirect to dashboard
```

### 2. Data Request Flow
```
Browser Request → Vercel API Route → Middleware (Auth check)
  ↓
  If authenticated, proceed
  ↓
  Prisma ORM → SQL Query → PostgreSQL (Supabase)
  ↓
  Response with data
  ↓
  React component renders → User sees data
```

### 3. Data Modification Flow
```
User submits form → React component → POST to API Route
  ↓
  Authentication verified
  ↓
  Input validation
  ↓
  Prisma → SQL INSERT/UPDATE → PostgreSQL
  ↓
  Response sent back
  ↓
  Component updates (UI reflects change)
```

---

## Environment Variables Required

### Database Connection (Vercel)
```
DATABASE_URL=postgresql://postgres:PASSWORD@db.PROJECT.supabase.co:5432/postgres
POSTGRES_URL=postgresql://postgres:PASSWORD@db.PROJECT.supabase.co:5432/postgres
POSTGRES_PRISMA_URL=postgresql://postgres:PASSWORD@db.PROJECT.supabase.co:5432/postgres?schema=public
POSTGRES_URL_NON_POOLING=postgresql://postgres:PASSWORD@db.PROJECT.supabase.co:5432/postgres
POSTGRES_USER=postgres
POSTGRES_PASSWORD=PASSWORD
POSTGRES_HOST=db.PROJECT.supabase.co
POSTGRES_PORT=5432
POSTGRES_DATABASE=postgres
```

### Supabase Configuration
```
SUPABASE_URL=https://PROJECT.supabase.co
NEXT_PUBLIC_SUPABASE_URL=https://PROJECT.supabase.co
SUPABASE_ANON_KEY=sb_publishable_XXXX
NEXT_PUBLIC_SUPABASE_ANON_KEY=sb_publishable_XXXX
SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIs...
SUPABASE_JWT_SECRET=your_jwt_secret_here
```

### Authentication
```
NEXTAUTH_SECRET=randomly_generated_secret_32_chars
NEXTAUTH_URL=https://your-app.vercel.app
```

---

## Deployment Pipeline

```
Local Development
      ↓
  git commit
      ↓
  git push origin main
      ↓
GitHub Repository
      ↓
GitHub Webhook → Vercel
      ↓
Vercel Build Process
  • Install dependencies (pnpm install)
  • Build Next.js app (pnpm build)
  • Run type checks
  • Generate Prisma client
  • Create optimized bundle
      ↓
Vercel Deployment
  • Deploy to edge network globally
  • Health checks pass
  • DNS updated (auto)
      ↓
Live at: https://your-project.vercel.app
```

---

## Scaling & Performance

### Vercel Hosting (Auto-scaling)
```
Traffic Increases
      ↓
Vercel auto-scales serverless functions
      ↓
Multiple instances deployed globally
      ↓
CDN caches static assets
      ↓
Edge functions cache at 300+ locations
      ↓
Database connection pooling (Vercel → Supabase)
      ↓
Always stays fast ⚡
```

### Database Connection Pooling
```
Vercel App Connections (many) → Connection Pool → Supabase PostgreSQL
```
- Max connections: 100
- Pool automatically optimizes
- Prevents database overload

---

## Security Architecture

```
HTTPS/TLS
  ↓
Vercel SSL certificate (auto-renewal)
  ↓
Encrypted connection to Supabase
  ↓
Database password encrypted in env vars
  ↓
Authentication via NextAuth (secure sessions)
  ↓
Middleware protects routes
  ↓
Row-level security (RLS) on database
  ↓
No user can access other users' data
```

### Authentication Layers
```
Layer 1: Browser → HTTPS/TLS
Layer 2: NextAuth session (secure cookie)
Layer 3: JWT token validation
Layer 4: Database RLS policies
Layer 5: API route authorization checks
```

---

## Key Features of This Architecture

### ✅ Reliability
- 99.99% uptime SLA (Vercel)
- Automatic failover
- Daily database backups (Supabase)
- Point-in-time recovery available

### ✅ Performance
- Global CDN (300+ edge locations)
- Serverless auto-scaling
- Database query optimization with Prisma
- Static page pre-rendering
- Image optimization

### ✅ Security
- End-to-end encryption (HTTPS)
- Secure session management
- Password hashing (bcrypt via NextAuth)
- SQL injection protection (Prisma parameterized queries)
- Row-level security on database
- Environment variable encryption

### ✅ Developer Experience
- Type-safe with TypeScript
- Next.js framework (full-stack)
- Prisma ORM (intuitive queries)
- Automatic deployments from GitHub
- Vercel CLI for local development

### ✅ Scalability
- Handles thousands of concurrent users
- Database auto-scales (Supabase)
- Serverless functions auto-scale (Vercel)
- CDN scales globally
- No server management required

---

## Cost Breakdown (Estimated Monthly)

```
Vercel (Next.js Hosting):
  • Free tier: 100GB bandwidth
  • Typical small app: ~$20-50/month

Supabase (PostgreSQL Database):
  • Free tier: 500MB storage, 2GB bandwidth
  • Small production: ~$25/month
  • Typical app: ~$50-100/month

Total: $50-150/month for production app
(First few months often free tier is sufficient)
```

---

## Monitoring & Observability

### Vercel Dashboard
```
✓ Deployment logs
✓ Build times
✓ Function performance
✓ Error tracking
✓ Analytics
✓ Usage metrics
```

### Supabase Dashboard
```
✓ Database query performance
✓ Storage usage
✓ Connection stats
✓ Backup history
✓ API usage
✓ Auth logs
```

### Optional Integrations
```
• Sentry: Error tracking & performance monitoring
• Vercel Analytics: Real user monitoring (RUM)
• PostHog: Product analytics & feature flags
• Uptime monitoring: Status page alerts
```

---

## Disaster Recovery

### If Vercel Goes Down
```
Your domain points to Vercel
  ↓
Vercel's global infrastructure handles it
  ↓
Auto-failover to other regions
  ↓
99.99% uptime SLA
```

### If Database Issues
```
Supabase automated backups (daily)
  ↓
Point-in-time recovery available (30-day window)
  ↓
24-hour Supabase support
  ↓
Data always safe
```

---

## Next Steps to Production

1. ✅ **Infrastructure**: PostgreSQL + Next.js (configured)
2. ✅ **Authentication**: NextAuth.js (configured)
3. ⏳ **Environment Setup**: Add credentials to Vercel
4. ⏳ **Database Migration**: Initialize schema
5. ⏳ **Deployment**: Push to GitHub → Auto-deploy

---

## Architecture Benefits

| Aspect | Benefit |
|--------|---------|
| Serverless | No servers to manage, auto-scaling |
| PostgreSQL | Powerful, reliable, battle-tested |
| Next.js | Full-stack JavaScript, best performance |
| Vercel | Optimized for Next.js, global CDN |
| Supabase | Modern PostgreSQL, managed for you |
| NextAuth | Production-ready auth solution |
| Prisma | Type-safe ORM, migrations built-in |

---

**This is a modern, scalable, production-ready architecture! 🚀**
