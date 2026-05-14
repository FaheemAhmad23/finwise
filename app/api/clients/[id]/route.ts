import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

type Params = { params: Promise<{ id: string }> };

// PATCH /api/clients/[id]
export async function PATCH(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const { id } = await params;
  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'User not found' }, { status: 404 });

  const existing = await prisma.client.findFirst({ where: { id, userId: user.id } });
  if (!existing) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const body = await req.json();
  const { name, email, whatsapp, company, address, city, country, notes } = body;

  const updated = await prisma.client.update({
    where: { id },
    data: {
      name: name?.trim() ?? existing.name,
      email: email?.trim() || null,
      whatsapp: whatsapp?.trim() || null,
      company: company?.trim() || null,
      address: address?.trim() || null,
      city: city?.trim() || null,
      country: country?.trim() || null,
      notes: notes?.trim() || null,
    },
  });

  return NextResponse.json(updated);
}

// DELETE /api/clients/[id]
export async function DELETE(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const { id } = await params;
  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'User not found' }, { status: 404 });

  const existing = await prisma.client.findFirst({ where: { id, userId: user.id } });
  if (!existing) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  await prisma.client.delete({ where: { id } });
  return NextResponse.json({ success: true });
}
