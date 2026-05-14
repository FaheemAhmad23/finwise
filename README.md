// New README for FinWise
# FinWise — Smart Money Manager

A full-stack personal finance app built with **Next.js 16**, **PostgreSQL**, **Prisma ORM**, and **NextAuth**.

## Features
- 📊 Monthly expense & income tracking
- 💳 Interpersonal debt management (lent/repaid)
- 💸 Bill splitting with auto-debt creation
- 📄 PDF statement export
- 🔒 Email/password & Google authentication
- 🌍 Multi-currency support
- 📦 CSV & JSON data export

## Getting Started

### Prerequisites
- Node.js 18+
- PostgreSQL 14+ (self-hosted or managed)

### Local Development

```bash
# 1. Clone and install
npm install

# 2. Copy env template
cp .env.local.example .env.local
# Fill in DATABASE_URL, NEXTAUTH_SECRET, NEXTAUTH_URL

# 3. Set up database
npx prisma generate
npx prisma migrate dev --name init

# 4. Run
npm run dev
```

### Production (Docker)
See [DEPLOYMENT.md](DEPLOYMENT.md) for full Docker deployment guide.

## Tech Stack
| Layer | Technology |
|---|---|
| Frontend | Next.js 16, React 19, TailwindCSS |
| Auth | NextAuth.js v4 |
| ORM | Prisma |
| Database | PostgreSQL |
| PDF | jsPDF + jspdf-autotable |
| Charts | Recharts |
| UI Components | Radix UI / shadcn |
