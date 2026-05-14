import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

/**
 * GET /api/dashboard
 * Returns all summary data needed for the dashboard in a single request:
 * - Current month income, expenses, balance
 * - 6-month trend data
 * - Expense breakdown by category
 * - Net people debt balance
 * - Budget utilisation for current month
 */
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;
  const now = new Date();
  const currentMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);
  const currentMonthEnd   = new Date(now.getFullYear(), now.getMonth() + 1, 1);

  // ── This month's transactions ────────────────────────────────────────────────
  const currentMonthTx = await prisma.transaction.findMany({
    where: {
      userId,
      date: { gte: currentMonthStart, lt: currentMonthEnd },
    },
  });

  const totalIncome   = currentMonthTx.filter(t => t.type === 'income').reduce((s, t) => s + t.amount, 0);
  const totalExpenses = currentMonthTx.filter(t => t.type === 'expense').reduce((s, t) => s + t.amount, 0);

  // ── 6-month trend ────────────────────────────────────────────────────────────
  const sixMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 5, 1);
  const allRecentTx = await prisma.transaction.findMany({
    where: { userId, date: { gte: sixMonthsAgo } },
    orderBy: { date: 'asc' },
  });

  const monthlyTrend: Record<string, { month: string; income: number; expense: number }> = {};
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    monthlyTrend[key] = {
      month: d.toLocaleString('default', { month: 'short', year: '2-digit' }),
      income: 0,
      expense: 0,
    };
  }
  allRecentTx.forEach((t) => {
    const key = `${t.date.getFullYear()}-${String(t.date.getMonth() + 1).padStart(2, '0')}`;
    if (monthlyTrend[key]) {
      if (t.type === 'income')  monthlyTrend[key].income  += t.amount;
      if (t.type === 'expense') monthlyTrend[key].expense += t.amount;
    }
  });

  // ── Category breakdown for current month ────────────────────────────────────
  const categoryBreakdown: Record<string, number> = {};
  currentMonthTx
    .filter(t => t.type === 'expense')
    .forEach(t => {
      categoryBreakdown[t.category] = (categoryBreakdown[t.category] || 0) + t.amount;
    });

  // ── People net balance ───────────────────────────────────────────────────────
  const people = await prisma.person.findMany({
    where: { userId },
    select: { balance: true },
  });
  const netPeopleBalance = people.reduce((s, p) => s + p.balance, 0);

  // ── Budget utilisation ───────────────────────────────────────────────────────
  const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const budgets = await prisma.budget.findMany({
    where: { userId, month: monthKey },
  });

  const budgetUtilisation = budgets.map((b) => ({
    category: b.category,
    limit: b.limitAmount,
    spent: categoryBreakdown[b.category] || 0,
    percentage: Math.round(((categoryBreakdown[b.category] || 0) / b.limitAmount) * 100),
  }));

  return NextResponse.json({
    currentMonth: {
      totalIncome,
      totalExpenses,
      balance: totalIncome - totalExpenses,
    },
    monthlyTrend: Object.values(monthlyTrend),
    categoryBreakdown: Object.entries(categoryBreakdown).map(([name, value]) => ({ name, value })),
    netPeopleBalance,
    budgetUtilisation,
  });
}
