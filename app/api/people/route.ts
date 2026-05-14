import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// ─── GET /api/people ──────────────────────────────────────────────────────────
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;

  const people = await prisma.person.findMany({
    where: { userId },
    include: {
      transactions: {
        orderBy: { createdAt: 'desc' },
      },
    },
    orderBy: { name: 'asc' },
  });

  // Serialize dates
  const result = people.map((p) => ({
    ...p,
    createdAt: p.createdAt.toISOString(),
    updatedAt: p.updatedAt.toISOString(),
    transactions: p.transactions.map((t) => ({
      ...t,
      date: t.date.toISOString().split('T')[0],
      createdAt: t.createdAt.toISOString(),
    })),
  }));

  return NextResponse.json(result);
}

// ─── POST /api/people ─────────────────────────────────────────────────────────
export async function POST(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;

  try {
    const { name, phone } = await req.json();

    if (!name?.trim()) {
      return NextResponse.json({ error: 'Name is required' }, { status: 400 });
    }

    const person = await prisma.person.create({
      data: {
        userId,
        name: name.trim(),
        phone: phone?.trim() || null,
        balance: 0,
      },
      include: { transactions: true },
    });

    return NextResponse.json({
      ...person,
      createdAt: person.createdAt.toISOString(),
      updatedAt: person.updatedAt.toISOString(),
      transactions: [],
    }, { status: 201 });
  } catch (error) {
    console.error('[POST /api/people]', error);
    return NextResponse.json({ error: 'Failed to add person' }, { status: 500 });
  }
}
