import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// ── Helper: generate next invoice number ────────────────────────────────────
async function generateInvoiceNo(userId: string): Promise<string> {
  const profile = await prisma.businessProfile.findUnique({ where: { userId } });
  const prefix  = profile?.invoicePrefix || 'INV';
  const next    = profile?.nextInvoiceNo || 1;
  const year    = new Date().getFullYear();
  const padded  = String(next).padStart(4, '0');
  const invoiceNo = `${prefix}-${year}-${padded}`;

  // Increment counter
  await prisma.businessProfile.upsert({
    where: { userId },
    create: { userId, invoicePrefix: prefix, nextInvoiceNo: next + 1 },
    update: { nextInvoiceNo: next + 1 },
  });

  return invoiceNo;
}

// ── GET /api/invoices ────────────────────────────────────────────────────────
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const { searchParams } = new URL(req.url);
  const status = searchParams.get('status');

  const invoices = await prisma.invoice.findMany({
    where: {
      userId: user.id,
      ...(status ? { status } : {}),
    },
    include: {
      client: { select: { id: true, name: true, company: true, email: true } },
      items: true,
    },
    orderBy: { createdAt: 'desc' },
  });

  // Auto-flag overdue
  const now = new Date();
  const withOverdue = invoices.map(inv => ({
    ...inv,
    status: inv.status === 'sent' && inv.dueDate && new Date(inv.dueDate) < now
      ? 'overdue'
      : inv.status,
  }));

  return NextResponse.json(withOverdue);
}

// ── POST /api/invoices ───────────────────────────────────────────────────────
export async function POST(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'Not found' }, { status: 404 });

  const body = await req.json();
  const { clientId, currency, issueDate, dueDate, items, taxRate, discount, notes, status } = body;

  if (!items?.length) return NextResponse.json({ error: 'At least one line item is required' }, { status: 400 });

  const invoiceNo = await generateInvoiceNo(user.id);

  // Calculate totals
  const subtotal  = items.reduce((s: number, it: any) => s + it.quantity * it.unitPrice, 0);
  const discountAmt = discount || 0;
  const taxable   = subtotal - discountAmt;
  const taxAmt    = taxable * ((taxRate ?? 5) / 100);
  const total     = taxable + taxAmt;

  const invoice = await prisma.invoice.create({
    data: {
      userId:    user.id,
      clientId:  clientId || null,
      invoiceNo,
      status:    status || 'draft',
      currency:  currency || 'AED',
      issueDate: issueDate ? new Date(issueDate) : new Date(),
      dueDate:   dueDate ? new Date(dueDate) : null,
      subtotal,
      taxRate:   taxRate ?? 5,
      taxAmount: taxAmt,
      discount:  discountAmt,
      total,
      notes:     notes || null,
      items: {
        create: items.map((it: any) => ({
          description: it.description,
          quantity:    it.quantity,
          unitPrice:   it.unitPrice,
          total:       it.quantity * it.unitPrice,
          productId:   it.productId || null,
        })),
      },
    },
    include: { client: true, items: true },
  });

  return NextResponse.json(invoice, { status: 201 });
}
