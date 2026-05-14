import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

/**
 * GET /api/export?format=csv&month=2026-05   → CSV of one month
 * GET /api/export?format=json                → Full JSON data dump (GDPR compliant)
 */
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;
  const { searchParams } = new URL(req.url);
  const format = searchParams.get('format') || 'csv';
  const month  = searchParams.get('month');

  let dateFilter: any = {};
  if (month) {
    const [year, mon] = month.split('-').map(Number);
    dateFilter = { gte: new Date(year, mon - 1, 1), lt: new Date(year, mon, 1) };
  }

  const transactions = await prisma.transaction.findMany({
    where: { userId, ...(month ? { date: dateFilter } : {}) },
    orderBy: { date: 'desc' },
  });

  // ── JSON export ──────────────────────────────────────────────────────────────
  if (format === 'json') {
    const people = await prisma.person.findMany({
      where: { userId },
      include: { transactions: true },
    });
    const budgets = await prisma.budget.findMany({ where: { userId } });

    return new NextResponse(
      JSON.stringify({ transactions, people, budgets }, null, 2),
      {
        headers: {
          'Content-Type': 'application/json',
          'Content-Disposition': `attachment; filename="finwise-export.json"`,
        },
      }
    );
  }

  // ── CSV export ───────────────────────────────────────────────────────────────
  const headers = ['Date', 'Type', 'Category', 'Amount', 'Currency', 'Description'];
  const rows = transactions.map((t) => [
    t.date.toISOString().split('T')[0],
    t.type,
    t.category,
    t.amount.toFixed(2),
    t.currency,
    `"${(t.description || '').replace(/"/g, '""')}"`,
  ]);

  const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
  const filename = month ? `finwise-${month}.csv` : 'finwise-all-transactions.csv';

  return new NextResponse(csv, {
    headers: {
      'Content-Type': 'text/csv',
      'Content-Disposition': `attachment; filename="${filename}"`,
    },
  });
}
