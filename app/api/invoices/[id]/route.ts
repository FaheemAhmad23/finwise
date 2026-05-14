import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

type Params = { params: Promise<{ id: string }> };

// GET /api/invoices/[id]
export async function GET(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const { id } = await params;
  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const invoice = await prisma.invoice.findFirst({
    where: { id, userId: user.id },
    include: {
      client: true,
      items: { include: { product: true } },
      user: {
        select: { name: true, email: true, businessProfile: true },
      },
    },
  });

  if (!invoice) return NextResponse.json({ error: 'Not found' }, { status: 404 });
  return NextResponse.json(invoice);
}

// PATCH /api/invoices/[id]
export async function PATCH(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const { id } = await params;
  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const existing = await prisma.invoice.findFirst({ where: { id, userId: user.id } });
  if (!existing) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const body = await req.json();
  const { clientId, currency, issueDate, dueDate, items, taxRate, discount, notes, status } = body;

  // Recalculate totals if items provided
  let subtotal = existing.subtotal;
  let taxAmount = existing.taxAmount;
  let total = existing.total;

  if (items?.length) {
    subtotal  = items.reduce((s: number, it: any) => s + it.quantity * it.unitPrice, 0);
    const discountAmt = discount ?? existing.discount;
    const taxable = subtotal - discountAmt;
    taxAmount = taxable * ((taxRate ?? existing.taxRate) / 100);
    total = taxable + taxAmount;

    // Replace all items
    await prisma.invoiceItem.deleteMany({ where: { invoiceId: id } });
    await prisma.invoiceItem.createMany({
      data: items.map((it: any) => ({
        invoiceId:   id,
        description: it.description,
        quantity:    it.quantity,
        unitPrice:   it.unitPrice,
        total:       it.quantity * it.unitPrice,
        productId:   it.productId || null,
      })),
    });
  }

  const updated = await prisma.invoice.update({
    where: { id },
    data: {
      clientId:  clientId !== undefined ? clientId : existing.clientId,
      currency:  currency  || existing.currency,
      issueDate: issueDate ? new Date(issueDate) : existing.issueDate,
      dueDate:   dueDate ? new Date(dueDate) : existing.dueDate,
      taxRate:   taxRate   ?? existing.taxRate,
      discount:  discount  ?? existing.discount,
      notes:     notes     !== undefined ? notes : existing.notes,
      status:    status    || existing.status,
      paidAt:    status === 'paid' && existing.status !== 'paid' ? new Date() : existing.paidAt,
      subtotal,
      taxAmount,
      total,
    },
    include: { client: true, items: true },
  });

  return NextResponse.json(updated);
}

// DELETE /api/invoices/[id]
export async function DELETE(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const { id } = await params;
  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const existing = await prisma.invoice.findFirst({ where: { id, userId: user.id } });
  if (!existing) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  await prisma.invoice.delete({ where: { id } });
  return NextResponse.json({ success: true });
}
