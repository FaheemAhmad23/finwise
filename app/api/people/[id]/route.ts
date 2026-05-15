import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// Next.js 15: params is a Promise
type RouteContext = { params: Promise<{ id: string }> };

export async function GET(req: NextRequest, context: RouteContext) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const { id } = await context.params;
  const userId = (session.user as any).id;

  const person = await prisma.person.findFirst({
    where: { id, userId },
    include: { transactions: { orderBy: { date: 'desc' } } },
  });

  if (!person) {
    return NextResponse.json({ error: 'Person not found' }, { status: 404 });
  }

  return NextResponse.json({
    ...person,
    createdAt: person.createdAt.toISOString(),
    updatedAt: person.updatedAt.toISOString(),
    transactions: person.transactions.map((t) => ({
      ...t,
      date: t.date.toISOString().split('T')[0],
      createdAt: t.createdAt.toISOString(),
    })),
  });
}

export async function POST(req: NextRequest, context: RouteContext) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const { id } = await context.params;
  const userId = (session.user as any).id;
  const currency = (session.user as any).currency || 'PKR';

  const person = await prisma.person.findFirst({ where: { id, userId } });
  if (!person) {
    return NextResponse.json({ error: 'Person not found' }, { status: 404 });
  }

  try {
    const { type, amount, note, date, fundingSource, repayToSavings } = await req.json();

    if (!type || !amount) {
      return NextResponse.json({ error: 'type and amount are required' }, { status: 400 });
    }

    const parsedAmount = parseFloat(amount);
    const today        = date ? new Date(date) : new Date();
    const balanceDelta = type === 'lent' ? parsedAmount : -parsedAmount;

    // Encode funding/repay source into description so balance-summary can calculate correctly
    let enhancedNote = note || null;
    if (type === 'lent' && fundingSource === 'savings') {
      enhancedNote = `[SRC:savings]${note ? ' ' + note : ''}`;
    }
    if (type === 'repaid' && repayToSavings) {
      enhancedNote = `[TO:savings]${note ? ' ' + note : ''}`;
    }

    // Mirror into the main transaction ledger
    const expenseTx = await prisma.transaction.create({
      data: {
        userId,
        type:              type === 'lent' ? 'expense' : 'income',
        category:          type === 'lent' ? 'Lent Money' : 'Debt Repaid',
        amount:            parsedAmount,
        currency,
        date:              today,
        description:       enhancedNote,
        isDebtTransaction: true,
        personName:        person.name,
      },
    });

    await prisma.debtTransaction.create({
      data: {
        personId:      id,
        userId,
        type,
        amount:        parsedAmount,
        note:          note || null,
        date:          today,
        transactionId: expenseTx.id,
      },
    });

    const updated = await prisma.person.update({
      where: { id },
      data:  { balance: { increment: balanceDelta } },
      include: { transactions: { orderBy: { date: 'desc' } } },
    });

    return NextResponse.json({
      ...updated,
      createdAt: updated.createdAt.toISOString(),
      updatedAt: updated.updatedAt.toISOString(),
      transactions: updated.transactions.map((t) => ({
        ...t,
        date:      t.date.toISOString().split('T')[0],
        createdAt: t.createdAt.toISOString(),
      })),
    }, { status: 201 });
  } catch (error) {
    console.error('[POST /api/people/:id]', error);
    return NextResponse.json({ error: 'Failed to record transaction' }, { status: 500 });
  }
}

export async function DELETE(req: NextRequest, context: RouteContext) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const { id } = await context.params;
  const userId = (session.user as any).id;

  const person = await prisma.person.findFirst({ where: { id, userId } });
  if (!person) {
    return NextResponse.json({ error: 'Person not found' }, { status: 404 });
  }

  await prisma.person.delete({ where: { id } });
  return NextResponse.json({ success: true });
}
