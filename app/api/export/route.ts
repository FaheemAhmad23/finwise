import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import { prisma } from '@/lib/prisma';

/**
 * GET /api/export?format=csv&month=2026-05   → CSV of one month
 * GET /api/export?format=pdf&month=2026-05   → PDF report (server-side, jsPDF — no Puppeteer/Chromium needed)
 * GET /api/export?format=json                → Full JSON data dump (GDPR compliant)
 */
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session?.user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const userId = (session.user as any).id;
  const { searchParams } = new URL(req.url);
  const format = searchParams.get('format') || 'csv';
  const month  = searchParams.get('month');

  let dateFilter: any = {};
  if (month) {
    const [year, mon] = month.split('-').map(Number);
    dateFilter = { gte: new Date(year, mon - 1, 1), lt: new Date(year, mon, 1) };
  }

  const transactions = await prisma.transaction.findMany({
    where: { userId, ...(month ? { date: dateFilter } : {}) },
    orderBy: { date: 'desc' },
  });

  // ── JSON export ──────────────────────────────────────────────────────────────
  if (format === 'json') {
    const people = await prisma.person.findMany({
      where: { userId },
      include: { transactions: true },
    });
    const budgets = await prisma.budget.findMany({ where: { userId } });

    return new NextResponse(
      JSON.stringify({ transactions, people, budgets }, null, 2),
      {
        headers: {
          'Content-Type': 'application/json',
          'Content-Disposition': `attachment; filename="finwise-export.json"`,
        },
      }
    );
  }

  // ── PDF export ───────────────────────────────────────────────────────────────
  // Uses jsPDF (already in package.json) — runs in Node.js on the server.
  // Zero extra installs needed. Works on any Linux/Docker server running Next.js.
  if (format === 'pdf') {
    const { jsPDF }  = await import('jspdf');
    const autoTable  = (await import('jspdf-autotable')).default;

    const doc      = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const pageW    = doc.internal.pageSize.getWidth();
    const userName = session.user?.name || session.user?.email || 'User';
    const label    = month || 'All Time';
    const filename = month ? `finwise-${month}.pdf` : 'finwise-all-transactions.pdf';

    // ── Purple header bar ────────────────────────────────────────────────────
    doc.setFillColor(124, 58, 237);
    doc.rect(0, 0, pageW, 28, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(20);
    doc.text('FinWise', 14, 12);

    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text('Smart Money Manager', 14, 18);
    doc.text(`Report: ${label}`, 14, 23);

    // right-aligned user info
    const nameW = doc.getStringUnitWidth(userName) * 9 / doc.internal.scaleFactor;
    doc.text(userName, pageW - nameW - 14, 12);
    doc.text(
      new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
      pageW - nameW - 14,
      18
    );

    // ── Summary cards ────────────────────────────────────────────────────────
    let totalIn  = 0;
    let totalOut = 0;
    for (const t of transactions) {
      if (t.type === 'IN') totalIn  += Number(t.amount);
      else                  totalOut += Number(t.amount);
    }
    const net = totalIn - totalOut;

    const cardY = 34;
    const cardH = 18;
    const cardW = (pageW - 28 - 8) / 3;

    const cards: { label: string; value: string; bg: [number, number, number] }[] = [
      { label: 'Total In',  value: `+${totalIn.toLocaleString()} PKR`,                      bg: [22, 101, 52]  },
      { label: 'Total Out', value: `-${totalOut.toLocaleString()} PKR`,                     bg: [153, 27, 27]  },
      { label: 'Net',       value: (net >= 0 ? '+' : '') + net.toLocaleString() + ' PKR',   bg: [49, 46, 129]  },
    ];

    cards.forEach((card, i) => {
      const x = 14 + i * (cardW + 4);
      doc.setFillColor(...card.bg);
      doc.roundedRect(x, cardY, cardW, cardH, 3, 3, 'F');
      doc.setTextColor(255, 255, 255);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(8);
      doc.text(card.label, x + 4, cardY + 6);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(11);
      doc.text(card.value, x + 4, cardY + 14);
    });

    // ── Section title ─────────────────────────────────────────────────────────
    doc.setTextColor(30, 30, 30);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.text('Transactions', 14, cardY + cardH + 10);

    // ── Transactions table ────────────────────────────────────────────────────
    const tableRows = transactions.map((t) => [
      t.date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
      t.type,
      t.category || '—',
      (t.type === 'IN' ? '+' : '-') + Number(t.amount).toLocaleString() + ' PKR',
      t.description || '—',
    ]);

    autoTable(doc, {
      startY: cardY + cardH + 14,
      head: [['Date', 'Type', 'Category', 'Amount', 'Description']],
      body: tableRows,
      theme: 'grid',
      styles: { fontSize: 8.5, cellPadding: 3, font: 'helvetica' },
      headStyles: {
        fillColor: [124, 58, 237],
        textColor: [255, 255, 255],
        fontStyle: 'bold',
        fontSize: 9,
      },
      alternateRowStyles: { fillColor: [245, 243, 255] },
      columnStyles: {
        0: { cellWidth: 26 },
        1: { cellWidth: 14 },
        2: { cellWidth: 28 },
        3: { cellWidth: 32, halign: 'right' },
        4: { cellWidth: 'auto' },
      },
      didParseCell(data) {
        if (data.section === 'body' && data.column.index === 3) {
          const val = String(data.cell.raw ?? '');
          data.cell.styles.textColor = val.startsWith('+') ? [21, 128, 61] : [185, 28, 28];
          data.cell.styles.fontStyle = 'bold';
        }
      },
    });

    // ── Footer on every page ──────────────────────────────────────────────────
    const pageCount = (doc.internal as any).getNumberOfPages();
    for (let p = 1; p <= pageCount; p++) {
      doc.setPage(p);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(8);
      doc.setTextColor(150, 150, 150);
      doc.text(
        `FinWise · Generated ${new Date().toLocaleString()} · Page ${p} of ${pageCount}`,
        14,
        doc.internal.pageSize.getHeight() - 8
      );
    }

    const pdfBuffer = Buffer.from(doc.output('arraybuffer'));

    return new NextResponse(pdfBuffer, {
      headers: {
        'Content-Type':        'application/pdf',
        'Content-Disposition': `attachment; filename="${filename}"`,
      },
    });
  }

  // ── CSV export ───────────────────────────────────────────────────────────────
  const headers = ['Date', 'Type', 'Category', 'Amount', 'Currency', 'Description'];
  const rows = transactions.map((t) => [
    t.date.toISOString().split('T')[0],
    t.type,
    t.category,
    t.amount.toFixed(2),
    t.currency,
    `"${(t.description || '').replace(/"/g, '""')}"`,
  ]);

  const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
  const filename = month ? `finwise-${month}.csv` : 'finwise-all-transactions.csv';

  return new NextResponse(csv, {
    headers: {
      'Content-Type': 'text/csv',
      'Content-Disposition': `attachment; filename="${filename}"`,
    },
  });
}
