'use client';

import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Printer, X } from 'lucide-react';
import type { Invoice } from './invoice-manager';

interface BusinessProfile {
  companyName?: string; logoUrl?: string; address?: string;
  city?: string; country?: string; phone?: string; whatsapp?: string;
  trn?: string; invoiceNote?: string;
}

function fmt(n: number, currency: string) {
  const sym: Record<string, string> = { AED: 'AED ', USD: '$', PKR: 'PKR ' };
  return `${sym[currency] || ''}${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const STATUS_COLORS: Record<string, string> = {
  draft: '#94a3b8', sent: '#60a5fa', paid: '#4ade80', overdue: '#f87171', cancelled: '#64748b',
};

export default function InvoicePrint({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
  const [profile, setProfile] = useState<BusinessProfile | null>(null);

  useEffect(() => {
    fetch('/api/business-profile').then(r => r.json()).then(setProfile).catch(() => {});
  }, []);

  const handlePrint = () => window.print();

  return (
    <Dialog open onOpenChange={v => { if (!v) onClose(); }}>
      <DialogContent className="rounded-2xl max-w-3xl max-h-[95vh] overflow-y-auto p-0">
        {/* Toolbar — hidden when printing */}
        <div className="flex items-center justify-between p-4 border-b border-border print:hidden">
          <span className="font-mono text-sm text-muted-foreground">{invoice.invoiceNo}</span>
          <div className="flex gap-2">
            <Button onClick={handlePrint} className="rounded-xl gap-2 h-9">
              <Printer className="w-4 h-4" /> Print / Save PDF
            </Button>
            <Button variant="ghost" size="sm" onClick={onClose} className="h-9 w-9 p-0">
              <X className="w-4 h-4" />
            </Button>
          </div>
        </div>

        {/* Print area */}
        <div id="invoice-print" className="p-8 md:p-12 bg-white text-gray-900 print:p-8">

          {/* Header */}
          <div className="flex justify-between items-start mb-10">
            <div>
              {profile?.companyName
                ? <h1 className="text-2xl font-black text-gray-900">{profile.companyName}</h1>
                : <h1 className="text-2xl font-black text-gray-900">FinWise Pro</h1>
              }
              {profile?.address && <p className="text-sm text-gray-500 mt-1">{profile.address}</p>}
              {profile?.city && <p className="text-sm text-gray-500">{profile.city}{profile.country ? `, ${profile.country}` : ''}</p>}
              {profile?.phone && <p className="text-sm text-gray-500 mt-1">📞 {profile.phone}</p>}
              {profile?.whatsapp && <p className="text-sm text-gray-500">💬 {profile.whatsapp}</p>}
              {profile?.trn && <p className="text-xs text-gray-400 mt-1">TRN: {profile.trn}</p>}
            </div>
            <div className="text-right">
              <p className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-1">INVOICE</p>
              <p className="text-2xl font-black text-indigo-600">{invoice.invoiceNo}</p>
              <div className="mt-2">
                <span className="inline-block text-xs font-bold px-2 py-1 rounded-full" style={{ backgroundColor: `${STATUS_COLORS[invoice.status]}22`, color: STATUS_COLORS[invoice.status] }}>
                  {invoice.status.toUpperCase()}
                </span>
              </div>
            </div>
          </div>

          {/* Client + Dates */}
          <div className="grid grid-cols-2 gap-8 mb-10">
            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Bill To</p>
              {invoice.client ? (
                <>
                  <p className="font-bold text-lg">{invoice.client.name}</p>
                  {invoice.client.company && <p className="text-sm text-gray-500">{invoice.client.company}</p>}
                  {invoice.client.email && <p className="text-sm text-gray-500">{invoice.client.email}</p>}
                </>
              ) : (
                <p className="text-gray-400 italic text-sm">No client specified</p>
              )}
            </div>
            <div className="text-right">
              <p className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Invoice Details</p>
              <div className="space-y-1 text-sm">
                <div className="flex justify-between gap-4">
                  <span className="text-gray-500">Issue Date</span>
                  <span className="font-medium">{new Date(invoice.issueDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                </div>
                {invoice.dueDate && (
                  <div className="flex justify-between gap-4">
                    <span className="text-gray-500">Due Date</span>
                    <span className="font-medium">{new Date(invoice.dueDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                  </div>
                )}
                <div className="flex justify-between gap-4">
                  <span className="text-gray-500">Currency</span>
                  <span className="font-medium">{invoice.currency}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Line Items */}
          <table className="w-full text-sm mb-10">
            <thead>
              <tr className="border-b-2 border-gray-200">
                <th className="text-left pb-3 font-bold text-xs uppercase tracking-wider text-gray-400">Description</th>
                <th className="text-center pb-3 font-bold text-xs uppercase tracking-wider text-gray-400 w-16">Qty</th>
                <th className="text-right pb-3 font-bold text-xs uppercase tracking-wider text-gray-400 w-28">Unit Price</th>
                <th className="text-right pb-3 font-bold text-xs uppercase tracking-wider text-gray-400 w-28">Total</th>
              </tr>
            </thead>
            <tbody>
              {invoice.items.map((item, i) => (
                <tr key={i} className="border-b border-gray-100">
                  <td className="py-3 font-medium">{item.description}</td>
                  <td className="py-3 text-center text-gray-500">{item.quantity}</td>
                  <td className="py-3 text-right text-gray-600">{fmt(item.unitPrice, invoice.currency)}</td>
                  <td className="py-3 text-right font-semibold">{fmt(item.total, invoice.currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {/* Summary */}
          <div className="flex justify-end mb-10">
            <div className="w-64 space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">Subtotal</span>
                <span>{fmt(invoice.subtotal, invoice.currency)}</span>
              </div>
              {invoice.discount > 0 && (
                <div className="flex justify-between">
                  <span className="text-gray-500">Discount</span>
                  <span className="text-red-500">- {fmt(invoice.discount, invoice.currency)}</span>
                </div>
              )}
              {invoice.taxRate > 0 && (
                <div className="flex justify-between">
                  <span className="text-gray-500">VAT ({invoice.taxRate}%)</span>
                  <span>{fmt(invoice.taxAmount, invoice.currency)}</span>
                </div>
              )}
              <div className="flex justify-between font-bold text-base border-t border-gray-200 pt-3 mt-3">
                <span>Total</span>
                <span className="text-indigo-600">{fmt(invoice.total, invoice.currency)}</span>
              </div>
              {invoice.paidAt && (
                <div className="flex justify-between text-green-600 text-xs pt-1">
                  <span>Paid on</span>
                  <span>{new Date(invoice.paidAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                </div>
              )}
            </div>
          </div>

          {/* Notes + Footer */}
          {(invoice.notes || profile?.invoiceNote) && (
            <div className="border-t border-gray-100 pt-6 mb-6">
              <p className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Notes</p>
              <p className="text-sm text-gray-600">{invoice.notes || profile?.invoiceNote}</p>
            </div>
          )}

          <div className="border-t border-gray-100 pt-6 text-center">
            <p className="text-xs text-gray-400">
              {profile?.companyName || 'FinWise Pro'} · Thank you for your business!
            </p>
          </div>
        </div>

        {/* Print Styles */}
        <style>{`
          @media print {
            body * { visibility: hidden !important; }
            #invoice-print, #invoice-print * { visibility: visible !important; }
            #invoice-print { position: fixed; left: 0; top: 0; width: 100%; background: white !important; }
          }
        `}</style>
      </DialogContent>
    </Dialog>
  );
}
