import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

export async function GET(_req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const userId = (session.user as any).id;
  const allTx  = await prisma.transaction.findMany({ where: { userId } });

  let income            = 0;   // regular income (salary, freelance, etc.)
  let openingCurrent    = 0;   // [INITIAL_BALANCE] — starting current account
  let openingSavings    = 0;   // [INITIAL_SAVINGS] — starting savings (not from current)
  let spending          = 0;   // non-savings expenses
  let savingsDeposits   = 0;   // "Savings" category — transfers from current → savings
  let savingsWithdrawals = 0;  // [SAVINGS_WITHDRAWAL] — transfers from savings → current
  let loansFromCurrent  = 0;
  let loansFromSavings  = 0;
  let repaymentsIn      = 0;   // loan repayments to current
  let repaymentsToSavings = 0;

  for (const tx of allTx) {
    // ── Income side ────────────────────────────────────────
    if (tx.type === 'income' && !tx.isDebtTransaction) {
      const desc = tx.description || '';

      if (desc.includes('[INITIAL_BALANCE]')) {
        openingCurrent += tx.amount;    // sets starting current balance
      } else if (desc.includes('[INITIAL_SAVINGS]')) {
        openingSavings += tx.amount;    // sets starting savings (bypass current)
      } else if (desc.includes('[SAVINGS_WITHDRAWAL]')) {
        savingsWithdrawals += tx.amount; // moving from savings → current
        income += tx.amount;             // current balance goes up
      } else {
        income += tx.amount;
      }
    }

    // ── Debt repayments ────────────────────────────────────
    if (tx.type === 'income' && tx.isDebtTransaction) {
      if (tx.description?.includes('[TO:savings]')) {
        repaymentsToSavings += tx.amount;
      } else {
        repaymentsIn += tx.amount;
      }
    }

    // ── Expense side ───────────────────────────────────────
    if (tx.type === 'expense' && !tx.isDebtTransaction) {
      if (tx.category === 'Savings') {
        savingsDeposits += tx.amount;
      } else {
        spending += tx.amount;
      }
    }

    // ── Loans given ────────────────────────────────────────
    if (tx.type === 'expense' && tx.isDebtTransaction) {
      if (tx.description?.includes('[SRC:savings]')) {
        loansFromSavings += tx.amount;
      } else {
        loansFromCurrent += tx.amount;
      }
    }
  }

  // Current = opening + income + withdrawals from savings - spending - savings deposits - loans from current + repayments
  const currentBalance = openingCurrent + income - spending - savingsDeposits - loansFromCurrent + repaymentsIn;

  // Savings = opening savings + deposits from current - withdrawals - loans from savings + savings repayments
  const savingsBalance = openingSavings + savingsDeposits - savingsWithdrawals - loansFromSavings + repaymentsToSavings;

  const totalAssets   = currentBalance + savingsBalance;
  const totalLoansOut = Math.max(loansFromCurrent + loansFromSavings - repaymentsIn - repaymentsToSavings, 0);

  return NextResponse.json({
    currentBalance,
    savingsBalance,
    totalAssets,
    totalEarned:    income + openingCurrent,
    totalSpent:     spending,
    totalSaved:     savingsDeposits,
    totalLoansOut,
    hasOpeningBalance: openingCurrent > 0 || openingSavings > 0,
  });
}
