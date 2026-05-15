import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

export async function GET(_req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const userId = (session.user as any).id;
  const allTx  = await prisma.transaction.findMany({ where: { userId } });

  let income            = 0;
  let spending          = 0;  // non-savings expenses (bills, food, etc.)
  let savingsDeposits   = 0;  // "Savings" category → moves money to savings pot
  let loansFromCurrent  = 0;  // lent from current balance
  let loansFromSavings  = 0;  // lent from savings balance
  let repaymentsIn      = 0;  // money returned to current
  let repaymentsToSavings = 0; // money returned to savings

  for (const tx of allTx) {
    if (tx.type === 'income' && !tx.isDebtTransaction) {
      income += tx.amount;
    }

    if (tx.type === 'income' && tx.isDebtTransaction) {
      // Debt repayment coming in
      if (tx.description?.includes('[TO:savings]')) {
        repaymentsToSavings += tx.amount;
      } else {
        repaymentsIn += tx.amount;
      }
    }

    if (tx.type === 'expense' && !tx.isDebtTransaction) {
      if (tx.category === 'Savings') {
        savingsDeposits += tx.amount;
      } else {
        spending += tx.amount;
      }
    }

    if (tx.type === 'expense' && tx.isDebtTransaction) {
      // Loan given
      if (tx.description?.includes('[SRC:savings]')) {
        loansFromSavings += tx.amount;
      } else {
        loansFromCurrent += tx.amount;
      }
    }
  }

  const currentBalance = income - spending - savingsDeposits - loansFromCurrent + repaymentsIn;
  const savingsBalance = savingsDeposits - loansFromSavings + repaymentsToSavings;
  const totalAssets    = currentBalance + savingsBalance;
  const totalLoansOut  = loansFromCurrent + loansFromSavings - repaymentsIn - repaymentsToSavings;

  return NextResponse.json({
    currentBalance,
    savingsBalance,
    totalAssets,
    totalEarned:    income,
    totalSpent:     spending,
    totalSaved:     savingsDeposits,
    totalLoansOut:  Math.max(totalLoansOut, 0),
  });
}
