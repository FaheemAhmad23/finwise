import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// ─── GET /api/people/:id ──────────────────────────────────────────────────────
export async function GET(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;

  const person = await prisma.person.findFirst({
    where: { id: params.id, userId },
    include: {
      transactions: { orderBy: { date: 'desc' } },
    },
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

// ─── POST /api/people/:id  →  Add a lent / repaid transaction ────────────────
export async function POST(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;
  const currency = (session.user as any).currency || 'PKR';

  const person = await prisma.person.findFirst({
    where: { id: params.id, userId },
  });
  if (!person) {
    return NextResponse.json({ error: 'Person not found' }, { status: 404 });
  }

  try {
    const { type, amount, note, date } = await req.json();

    if (!type || !amount) {
      return NextResponse.json({ error: 'type and amount are required' }, { status: 400 });
    }

    const parsedAmount = parseFloat(amount);
    const today = date ? new Date(date) : new Date();

    // lent = they owe you more (+balance), repaid = balance decreases
    const balanceDelta = type === 'lent' ? parsedAmount : -parsedAmount;

    // Create a mirrored transaction in the main expense ledger
    const expenseTransaction = await prisma.transaction.create({
      data: {
        userId,
        type: type === 'lent' ? 'expense' : 'income',
        category: type === 'lent' ? 'Lent Money' : 'Debt Repaid',
        amount: parsedAmount,
        currency,
        date: today,
        description: note || null,
        isDebtTransaction: true,
        personName: person.name,
      },
    });

    // Create the debt transaction entry
    const debtTransaction = await prisma.debtTransaction.create({
      data: {
        personId: params.id,
        userId,
        type,
        amount: parsedAmount,
        note: note || null,
        date: today,
        transactionId: expenseTransaction.id,
      },
    });

    // Update the person's running balance
    const updatedPerson = await prisma.person.update({
      where: { id: params.id },
      data: { balance: { increment: balanceDelta } },
      include: {
        transactions: { orderBy: { date: 'desc' } },
      },
    });

    return NextResponse.json({
      ...updatedPerson,
      createdAt: updatedPerson.createdAt.toISOString(),
      updatedAt: updatedPerson.updatedAt.toISOString(),
      transactions: updatedPerson.transactions.map((t) => ({
        ...t,
        date: t.date.toISOString().split('T')[0],
        createdAt: t.createdAt.toISOString(),
      })),
    }, { status: 201 });
  } catch (error) {
    console.error('[POST /api/people/:id]', error);
    return NextResponse.json({ error: 'Failed to record transaction' }, { status: 500 });
  }
}

// ─── DELETE /api/people/:id ───────────────────────────────────────────────────
export async function DELETE(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;

  const person = await prisma.person.findFirst({
    where: { id: params.id, userId },
  });
  if (!person) {
    return NextResponse.json({ error: 'Person not found' }, { status: 404 });
  }

  // Cascade deletes DebtTransactions automatically (schema: onDelete: Cascade)
  await prisma.person.delete({ where: { id: params.id } });

  return NextResponse.json({ success: true });
}
