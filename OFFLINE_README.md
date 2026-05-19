# FinWise - Offline Edition

Your FinWise app has been successfully converted to a **fully offline, no-login-required** personal finance manager. All data is saved locally on your device using IndexedDB.

## What Changed

### Removed
- NextAuth authentication system (no login required anymore)
- Prisma database and PostgreSQL integration  
- All server API routes
- Supabase/database dependencies
- 30+ unnecessary deployment and setup files

### Added
- **IndexedDB Storage Layer** (`/lib/storage.ts`) - Offline data persistence
- Simplified expense, debt, and bill managers using local storage
- Direct device storage - no servers needed

## Data Storage

All data is stored locally in your browser's IndexedDB:
- Expenses and income transactions
- Debts and lending history
- People you've tracked
- Bill split calculations

Data persists across browser sessions and never leaves your device.

## How to Use

1. **Open the app** - No login screen, goes straight to dashboard
2. **Add Transactions** - Track expenses and income
3. **Manage Debts** - Keep track of who owes whom
4. **Split Bills** - Calculate bill splits instantly
5. **Export Data** - Download your data as CSV/PDF

## Features

- ✓ Track income and expenses by category
- ✓ Manage multiple people and debts
- ✓ Split bills among friends
- ✓ View monthly trends
- ✓ Export to PDF/CSV
- ✓ Fully offline - works without internet
- ✓ No login or account required
- ✓ All data stored locally

## Developer Notes

- Main entry: `/app/page.tsx` (no auth redirects)
- Storage utility: `/lib/storage.ts` (IndexedDB wrapper)
- Component managers: `/components/*/` (updated for offline)
- Removed all `/app/api` routes (no backend needed)
- Build: `pnpm build` ✓ passes with no errors
- Dev: `pnpm dev` runs on port 3000

## Deployment

Deploy to Vercel or any static host:
```bash
pnpm build
pnpm start
```

No environment variables or database needed. It's a pure frontend app.

---

**FinWise Offline** - Your money, your device, always yours.
