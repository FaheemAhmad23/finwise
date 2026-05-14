import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// ─── GET /api/transactions?month=2026-05 ─────────────────────────────────────
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;
  const { searchParams } = new URL(req.url);
  const month = searchParams.get('month'); // e.g. '2026-05'

  let dateFilter: any = {};
  if (month) {
    const [year, mon] = month.split('-').map(Number);
    dateFilter = {
      gte: new Date(year, mon - 1, 1),
      lt:  new Date(year, mon, 1),
    };
  }

  const transactions = await prisma.transaction.findMany({
    where: {
      userId,
      ...(month ? { date: dateFilter } : {}),
    },
    orderBy: { date: 'desc' },
  });

  // Serialize dates to ISO strings
  const result = transactions.map((t) => ({
    ...t,
    date: t.date.toISOString().split('T')[0],
    createdAt: t.createdAt.toISOString(),
    updatedAt: t.updatedAt.toISOString(),
  }));

  return NextResponse.json(result);
}

// ─── POST /api/transactions ───────────────────────────────────────────────────
export async function POST(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;
  const currency = (session.user as any).currency || 'PKR';

  try {
    const body = await req.json();
    const {
      type,
      category,
      amount,
      date,
      description,
      isDebtTransaction,
      personName,
      isRecurring,
      recurInterval,
    } = body;

    if (!type || !category || !amount || !date) {
      return NextResponse.json(
        { error: 'type, category, amount, and date are required' },
        { status: 400 }
      );
    }

    const transaction = await prisma.transaction.create({
      data: {
        userId,
        type,
        category,
        amount: parseFloat(amount),
        currency,
        date: new Date(date),
        description: description || null,
        isDebtTransaction: isDebtTransaction || false,
        personName: personName || null,
        isRecurring: isRecurring || false,
        recurInterval: recurInterval || null,
      },
    });

    return NextResponse.json({
      ...transaction,
      date: transaction.date.toISOString().split('T')[0],
      createdAt: transaction.createdAt.toISOString(),
      updatedAt: transaction.updatedAt.toISOString(),
    }, { status: 201 });
  } catch (error) {
    console.error('[POST /api/transactions]', error);
    return NextResponse.json({ error: 'Failed to create transaction' }, { status: 500 });
  }
}
