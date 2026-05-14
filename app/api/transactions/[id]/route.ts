import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// ─── PATCH /api/transactions/:id ─────────────────────────────────────────────
export async function PATCH(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;

  // Verify ownership
  const existing = await prisma.transaction.findFirst({
    where: { id: params.id, userId },
  });
  if (!existing) {
    return NextResponse.json({ error: 'Transaction not found' }, { status: 404 });
  }

  try {
    const body = await req.json();
    const { type, category, amount, date, description } = body;

    const updated = await prisma.transaction.update({
      where: { id: params.id },
      data: {
        ...(type       && { type }),
        ...(category   && { category }),
        ...(amount     && { amount: parseFloat(amount) }),
        ...(date       && { date: new Date(date) }),
        ...(description !== undefined && { description: description || null }),
      },
    });

    return NextResponse.json({
      ...updated,
      date: updated.date.toISOString().split('T')[0],
      createdAt: updated.createdAt.toISOString(),
      updatedAt: updated.updatedAt.toISOString(),
    });
  } catch (error) {
    console.error('[PATCH /api/transactions/:id]', error);
    return NextResponse.json({ error: 'Failed to update transaction' }, { status: 500 });
  }
}

// ─── DELETE /api/transactions/:id ────────────────────────────────────────────
export async function DELETE(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;

  const existing = await prisma.transaction.findFirst({
    where: { id: params.id, userId },
  });
  if (!existing) {
    return NextResponse.json({ error: 'Transaction not found' }, { status: 404 });
  }

  await prisma.transaction.delete({ where: { id: params.id } });
  return NextResponse.json({ success: true });
}
