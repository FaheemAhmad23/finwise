'use client';

import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus, Trash2, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import type { Invoice } from './invoice-manager';

interface Client { id: string; name: string; company: string | null; }
interface LineItem { description: string; quantity: number; unitPrice: number; }

interface Props {
  invoice?: Invoice | null;
  onClose: () => void;
  onSaved: () => void;
}

const CURRENCIES = ['AED', 'USD', 'PKR'];
const newItem = (): LineItem => ({ description: '', quantity: 1, unitPrice: 0 });

export default function InvoiceForm({ invoice, onClose, onSaved }: Props) {
  const isEdit = !!invoice;
  const [clients, setClients] = useState<Client[]>([]);
  const [saving, setSaving]   = useState(false);
  const [clientId,  setClientId]  = useState(invoice?.client?.id || '');
  const [currency,  setCurrency]  = useState(invoice?.currency || 'AED');
  const [issueDate, setIssueDate] = useState(
    invoice?.issueDate ? invoice.issueDate.slice(0, 10) : new Date().toISOString().slice(0, 10)
  );
  const [dueDate,  setDueDate]  = useState(invoice?.dueDate ? invoice.dueDate.slice(0, 10) : '');
  const [taxRate,  setTaxRate]  = useState(invoice?.taxRate ?? 5);
  const [discount, setDiscount] = useState(invoice?.discount ?? 0);
  const [notes,    setNotes]    = useState(invoice?.notes || '');
  const [status,   setStatus]   = useState(invoice?.status || 'draft');
  const [items, setItems] = useState<LineItem[]>(
    invoice?.items?.length
      ? invoice.items.map(it => ({ description: it.description, quantity: it.quantity, unitPrice: it.unitPrice }))
      : [newItem()]
  );

  useEffect(() => {
    fetch('/api/clients').then(r => r.json()).then(setClients).catch(() => {});
  }, []);

  const subtotal  = items.reduce((s, it) => s + it.quantity * it.unitPrice, 0);
  const taxable   = subtotal - discount;
  const taxAmount = taxable * (taxRate / 100);
  const total     = taxable + taxAmount;
  const sym       = currency === 'USD' ? '$' : `${currency} `;
  const fmt       = (n: number) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const updateItem = (i: number, field: keyof LineItem, val: string | number) =>
    setItems(prev => prev.map((it, idx) => idx === i ? { ...it, [field]: val } : it));
  const removeItem = (i: number) => setItems(prev => prev.filter((_, idx) => idx !== i));

  const handleSave = async () => {
    const validItems = items.filter(it => it.description.trim() && it.quantity > 0);
    if (!validItems.length) { toast.error('Add at least one line item'); return; }
    setSaving(true);
    try {
      const res = await fetch(isEdit ? `/api/invoices/${invoice!.id}` : '/api/invoices', {
        method: isEdit ? 'PATCH' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          clientId: clientId || null, currency, issueDate,
          dueDate: dueDate || null, taxRate, discount, notes, status,
          items: validItems,
        }),
      });
      if (!res.ok) throw new Error();
      toast.success(isEdit ? 'Invoice updated' : 'Invoice created');
      onSaved();
    } catch {
      toast.error('Failed to save invoice');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open onOpenChange={v => { if (!v) onClose(); }}>
      <DialogContent className="rounded-2xl max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? `Edit ${invoice!.invoiceNo}` : 'Create New Invoice'}</DialogTitle>
        </DialogHeader>
        <div className="space-y-5 pt-2">

          {/* Client + Currency */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Client</label>
              <select
                className="w-full px-3 py-2.5 rounded-xl border border-border bg-card text-foreground text-sm"
                value={clientId} onChange={e => setClientId(e.target.value)}
              >
                <option value="">— No client —</option>
                {clients.map(c => (
                  <option key={c.id} value={c.id}>{c.name}{c.company ? ` (${c.company})` : ''}</option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Currency</label>
              <div className="flex gap-2">
                {CURRENCIES.map(cur => (
                  <button key={cur} onClick={() => setCurrency(cur)}
                    className={`flex-1 py-2.5 rounded-xl text-sm font-medium border transition-all ${
                      currency === cur
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'bg-card border-border text-muted-foreground hover:text-foreground'
                    }`}
                  >{cur}</button>
                ))}
              </div>
            </div>
          </div>

          {/* Dates */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Issue Date</label>
              <Input type="date" value={issueDate} onChange={e => setIssueDate(e.target.value)} className="rounded-xl" />
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Due Date</label>
              <Input type="date" value={dueDate} onChange={e => setDueDate(e.target.value)} className="rounded-xl" />
            </div>
          </div>

          {/* Line Items */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <label className="text-sm font-medium">Line Items</label>
              <Button size="sm" variant="outline" onClick={() => setItems(p => [...p, newItem()])} className="h-7 px-3 text-xs rounded-xl gap-1">
                <Plus className="w-3 h-3" /> Add Row
              </Button>
            </div>
            <div className="grid grid-cols-12 gap-2 text-xs text-muted-foreground px-1">
              <span className="col-span-6">Description</span>
              <span className="col-span-2 text-center">Qty</span>
              <span className="col-span-2 text-right">Unit Price</span>
              <span className="col-span-1 text-right">Total</span>
              <span className="col-span-1" />
            </div>
            {items.map((item, i) => (
              <div key={i} className="grid grid-cols-12 gap-2 items-center">
                <Input className="col-span-6 rounded-xl text-sm h-9" placeholder="Description"
                  value={item.description} onChange={e => updateItem(i, 'description', e.target.value)} />
                <Input type="number" className="col-span-2 rounded-xl text-sm h-9 text-center" min={0.01} step={0.01}
                  value={item.quantity} onChange={e => updateItem(i, 'quantity', parseFloat(e.target.value) || 0)} />
                <Input type="number" className="col-span-2 rounded-xl text-sm h-9 text-right" min={0} step={0.01}
                  value={item.unitPrice} onChange={e => updateItem(i, 'unitPrice', parseFloat(e.target.value) || 0)} />
                <p className="col-span-1 text-right text-sm font-medium">{fmt(item.quantity * item.unitPrice)}</p>
                <Button variant="ghost" size="sm" onClick={() => removeItem(i)} disabled={items.length === 1}
                  className="col-span-1 h-9 w-9 p-0 hover:bg-red-500/10">
                  <Trash2 className="w-3.5 h-3.5 text-red-400" />
                </Button>
              </div>
            ))}
          </div>

          {/* Totals */}
          <div className="bg-muted/40 rounded-xl p-4 space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Subtotal</span>
              <span>{sym}{fmt(subtotal)}</span>
            </div>
            <div className="flex items-center justify-between text-sm gap-4">
              <div className="flex items-center gap-2 text-muted-foreground">
                <span>Discount</span>
                <Input type="number" min={0} step={0.01} value={discount}
                  onChange={e => setDiscount(parseFloat(e.target.value) || 0)}
                  className="h-7 w-24 rounded-lg text-xs text-right" />
              </div>
              <span className="text-red-400">− {sym}{fmt(discount)}</span>
            </div>
            <div className="flex items-center justify-between text-sm gap-4">
              <div className="flex items-center gap-2 text-muted-foreground">
                <span>VAT</span>
                <Input type="number" min={0} max={30} step={0.5} value={taxRate}
                  onChange={e => setTaxRate(parseFloat(e.target.value) || 0)}
                  className="h-7 w-16 rounded-lg text-xs text-right" />
                <span className="text-xs">%</span>
              </div>
              <span>+ {sym}{fmt(taxAmount)}</span>
            </div>
            <div className="flex justify-between font-bold text-base border-t border-border pt-2 mt-2">
              <span>Total</span>
              <span className="text-primary">{sym}{fmt(total)}</span>
            </div>
          </div>

          {/* Notes + Status */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Notes</label>
              <Input placeholder="Thank you for your business!" value={notes}
                onChange={e => setNotes(e.target.value)} className="rounded-xl" />
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Status</label>
              <select className="w-full px-3 py-2.5 rounded-xl border border-border bg-card text-foreground text-sm"
                value={status} onChange={e => setStatus(e.target.value)}>
                <option value="draft">Draft</option>
                <option value="sent">Sent</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-3 pt-1">
            <Button variant="outline" onClick={onClose} className="flex-1 rounded-xl">Cancel</Button>
            <Button onClick={handleSave} disabled={saving} className="flex-1 rounded-xl">
              {saving ? <><Loader2 className="w-4 h-4 mr-2 animate-spin" />Saving…</> : isEdit ? 'Save Changes' : 'Create Invoice'}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
