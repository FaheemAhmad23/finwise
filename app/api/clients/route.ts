import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// GET /api/clients — list all clients for the current user
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'User not found' }, { status: 404 });

  const clients = await prisma.client.findMany({
    where: { userId: user.id },
    include: {
      _count: { select: { invoices: true } },
    },
    orderBy: { name: 'asc' },
  });

  return NextResponse.json(clients);
}

// POST /api/clients — create a new client
export async function POST(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'User not found' }, { status: 404 });

  const body = await req.json();
  const { name, email, whatsapp, company, address, city, country, notes } = body;

  if (!name?.trim()) return NextResponse.json({ error: 'Name is required' }, { status: 400 });

  const client = await prisma.client.create({
    data: {
      userId: user.id,
      name: name.trim(),
      email: email?.trim() || null,
      whatsapp: whatsapp?.trim() || null,
      company: company?.trim() || null,
      address: address?.trim() || null,
      city: city?.trim() || null,
      country: country?.trim() || null,
      notes: notes?.trim() || null,
    },
  });

  return NextResponse.json(client, { status: 201 });
}
