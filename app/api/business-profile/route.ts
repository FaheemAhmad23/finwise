import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

// GET /api/business-profile
export async function GET() {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'User not found' }, { status: 404 });

  const profile = await prisma.businessProfile.findUnique({ where: { userId: user.id } });
  return NextResponse.json(profile || {});
}

// PUT /api/business-profile — upsert
export async function PUT(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user?.email) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });

  const user = await prisma.user.findUnique({ where: { email: session.user.email } });
  if (!user) return NextResponse.json({ error: 'User not found' }, { status: 404 });

  const body = await req.json();
  const {
    companyName, logoUrl, address, city, country,
    phone, whatsapp, website, trn, currency,
    invoiceNote, invoicePrefix,
  } = body;

  const profile = await prisma.businessProfile.upsert({
    where: { userId: user.id },
    create: {
      userId: user.id,
      companyName: companyName?.trim() || null,
      logoUrl: logoUrl?.trim() || null,
      address: address?.trim() || null,
      city: city?.trim() || null,
      country: country?.trim() || 'UAE',
      phone: phone?.trim() || null,
      whatsapp: whatsapp?.trim() || null,
      website: website?.trim() || null,
      trn: trn?.trim() || null,
      currency: currency || 'AED',
      invoiceNote: invoiceNote?.trim() || null,
      invoicePrefix: invoicePrefix?.trim() || 'INV',
    },
    update: {
      companyName: companyName?.trim() || null,
      logoUrl: logoUrl?.trim() || null,
      address: address?.trim() || null,
      city: city?.trim() || null,
      country: country?.trim() || 'UAE',
      phone: phone?.trim() || null,
      whatsapp: whatsapp?.trim() || null,
      website: website?.trim() || null,
      trn: trn?.trim() || null,
      currency: currency || 'AED',
      invoiceNote: invoiceNote?.trim() || null,
      invoicePrefix: invoicePrefix?.trim() || 'INV',
    },
  });

  return NextResponse.json(profile);
}
