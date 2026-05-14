'use client';

import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, FileText, CheckCircle2, Clock, AlertCircle, XCircle, Printer, Edit2, Trash2, ChevronDown, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import InvoiceForm from './invoice-form';
import InvoicePrint from './invoice-print';

export interface InvoiceItem {
  id?: string;
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
  productId?: string | null;
}

export interface Invoice {
  id: string;
  invoiceNo: string;
  status: string;
  currency: string;
  issueDate: string;
  dueDate: string | null;
  subtotal: number;
  taxRate: number;
  taxAmount: number;
  discount: number;
  total: number;
  notes: string | null;
  paidAt: string | null;
  createdAt: string;
  client: { id: string; name: string; company: string | null; email: string | null } | null;
  items: InvoiceItem[];
}

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: any }> = {
  draft:     { label: 'Draft',    color: 'text-muted-foreground bg-muted',           icon: FileText },
  sent:      { label: 'Sent',     color: 'text-blue-400 bg-blue-500/10',             icon: Clock },
  paid:      { label: 'Paid',     color: 'text-green-400 bg-green-500/10',           icon: CheckCircle2 },
  overdue:   { label: 'Overdue',  color: 'text-red-400 bg-red-500/10',              icon: AlertCircle },
  cancelled: { label: 'Cancelled', color: 'text-muted-foreground bg-muted/50',       icon: XCircle },
};

function fmt(amount: number, currency: string) {
  const symbols: Record<string, string> = { AED: 'AED ', USD: '$', PKR: 'PKR ' };
  return `${symbols[currency] || ''}${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export default function InvoiceManager({ onUpdate }: { onUpdate: () => void }) {
  const [invoices, setInvoices]   = useState<Invoice[]>([]);
  const [loading, setLoading]     = useState(true);
  const [filter, setFilter]       = useState('all');
  const [showForm, setShowForm]   = useState(false);
  const [editInvoice, setEditInvoice] = useState<Invoice | null>(null);
  const [printInvoice, setPrintInvoice] = useState<Invoice | null>(null);
  const [statusDropdown, setStatusDropdown] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/invoices');
      if (!res.ok) throw new Error();
      setInvoices(await res.json());
    } catch {
      toast.error('Failed to load invoices');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this invoice permanently?')) return;
    try {
      await fetch(`/api/invoices/${id}`, { method: 'DELETE' });
      toast.success('Invoice deleted');
      await load();
      onUpdate();
    } catch {
      toast.error('Failed to delete');
    }
  };

  const updateStatus = async (id: string, status: string) => {
    try {
      await fetch(`/api/invoices/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status }),
      });
      toast.success(`Marked as ${status}`);
      setStatusDropdown(null);
      await load();
      onUpdate();
    } catch {
      toast.error('Failed to update status');
    }
  };

  const filtered = filter === 'all' ? invoices : invoices.filter(i => i.status === filter);

  // Stats
  const totalRevenue    = invoices.filter(i => i.status === 'paid').reduce((s, i) => s + i.total, 0);
  const outstanding     = invoices.filter(i => ['sent','overdue'].includes(i.status)).reduce((s, i) => s + i.total, 0);
  const overdueCount    = invoices.filter(i => i.status === 'overdue').length;

  if (loading) return (
    <div className="space-y-3 animate-pulse">
      <div className="grid grid-cols-3 gap-3">
        {[1,2,3].map(i => <div key={i} className="h-20 bg-muted rounded-xl" />)}
      </div>
      {[1,2,3].map(i => <div key={i} className="h-16 bg-muted rounded-xl" />)}
    </div>
  );

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <h3 className="font-semibold text-foreground text-base">Invoices</h3>
        <Button onClick={() => { setEditInvoice(null); setShowForm(true); }} className="rounded-xl gap-2">
          <Plus className="w-4 h-4" /> New Invoice
        </Button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-3">
        <div className="bg-green-500/10 border border-green-500/20 rounded-xl p-4 text-center">
          <p className="text-lg md:text-2xl font-bold text-green-400">{fmt(totalRevenue, 'AED')}</p>
          <p className="text-xs text-muted-foreground mt-1">Revenue (Paid)</p>
        </div>
        <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 text-center">
          <p className="text-lg md:text-2xl font-bold text-blue-400">{fmt(outstanding, 'AED')}</p>
          <p className="text-xs text-muted-foreground mt-1">Outstanding</p>
        </div>
        <div className={`rounded-xl p-4 text-center ${overdueCount > 0 ? 'bg-red-500/10 border border-red-500/20' : 'bg-card border border-border'}`}>
          <p className={`text-lg md:text-2xl font-bold ${overdueCount > 0 ? 'text-red-400' : 'text-foreground'}`}>{overdueCount}</p>
          <p className="text-xs text-muted-foreground mt-1">Overdue</p>
        </div>
      </div>

      {/* Filter Pills */}
      <div className="flex gap-2 flex-wrap">
        {['all', 'draft', 'sent', 'paid', 'overdue'].map(s => (
          <button
            key={s}
            onClick={() => setFilter(s)}
            className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
              filter === s
                ? 'bg-primary text-primary-foreground'
                : 'bg-muted text-muted-foreground hover:text-foreground'
            }`}
          >
            {s.charAt(0).toUpperCase() + s.slice(1)}
            {s !== 'all' && (
              <span className="ml-1.5 opacity-60">
                {invoices.filter(i => i.status === s).length}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Invoice List */}
      {filtered.length === 0 ? (
        <div className="text-center py-16 text-muted-foreground">
          <FileText className="w-12 h-12 mx-auto mb-4 opacity-20" />
          <p className="font-medium">{filter === 'all' ? 'No invoices yet' : `No ${filter} invoices`}</p>
          <p className="text-sm mt-1 opacity-60">Create your first invoice to get started</p>
          <Button onClick={() => { setEditInvoice(null); setShowForm(true); }} className="mt-4 rounded-xl" variant="outline">
            <Plus className="w-4 h-4 mr-2" /> Create Invoice
          </Button>
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map(invoice => {
            const cfg = STATUS_CONFIG[invoice.status] || STATUS_CONFIG.draft;
            const Icon = cfg.icon;
            const isOverdue = invoice.status === 'overdue';
            const dueDate = invoice.dueDate ? new Date(invoice.dueDate) : null;

            return (
              <div key={invoice.id} className="bg-card border border-border rounded-xl p-4 hover:border-border/80 transition-all">
                <div className="flex items-start gap-3">
                  {/* Left */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      <span className="font-mono text-sm font-bold text-foreground">{invoice.invoiceNo}</span>
                      <span className={`inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium ${cfg.color}`}>
                        <Icon className="w-3 h-3" />
                        {cfg.label}
                      </span>
                      {isOverdue && dueDate && (
                        <span className="text-xs text-red-400">
                          {Math.floor((Date.now() - dueDate.getTime()) / 86400000)}d overdue
                        </span>
                      )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {invoice.client
                        ? <><span className="text-foreground font-medium">{invoice.client.name}</span>{invoice.client.company && ` · ${invoice.client.company}`}</>
                        : <span className="italic">No client</span>
                      }
                    </p>
                    <div className="flex items-center gap-3 mt-1 text-xs text-muted-foreground flex-wrap">
                      <span>{new Date(invoice.issueDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                      {dueDate && (
                        <span className={isOverdue ? 'text-red-400' : ''}>
                          Due {dueDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Right: amount + actions */}
                  <div className="flex flex-col items-end gap-2 flex-shrink-0">
                    <p className="font-bold text-foreground text-base">{fmt(invoice.total, invoice.currency)}</p>
                    <div className="flex items-center gap-1">
                      {/* Status changer */}
                      <div className="relative">
                        <button
                          onClick={() => setStatusDropdown(statusDropdown === invoice.id ? null : invoice.id)}
                          className="h-7 px-2 text-xs rounded-lg bg-muted hover:bg-muted/80 text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1"
                        >
                          Status <ChevronDown className="w-3 h-3" />
                        </button>
                        {statusDropdown === invoice.id && (
                          <div className="absolute right-0 top-8 z-50 bg-card border border-border rounded-xl shadow-xl overflow-hidden min-w-[130px]">
                            {['draft','sent','paid','cancelled'].map(s => (
                              <button
                                key={s}
                                onClick={() => updateStatus(invoice.id, s)}
                                className={`w-full text-left px-3 py-2 text-xs hover:bg-muted transition-colors ${invoice.status === s ? 'text-primary font-medium' : 'text-foreground'}`}
                              >
                                {s.charAt(0).toUpperCase() + s.slice(1)}
                              </button>
                            ))}
                          </div>
                        )}
                      </div>
                      <Button variant="ghost" size="sm" onClick={() => setPrintInvoice(invoice)} className="h-7 w-7 p-0 hover:bg-muted" title="Print / View">
                        <Printer className="w-3.5 h-3.5" />
                      </Button>
                      <Button variant="ghost" size="sm" onClick={() => { setEditInvoice(invoice); setShowForm(true); }} className="h-7 w-7 p-0 hover:bg-muted" title="Edit">
                        <Edit2 className="w-3.5 h-3.5" />
                      </Button>
                      <Button variant="ghost" size="sm" onClick={() => handleDelete(invoice.id)} className="h-7 w-7 p-0 hover:bg-red-500/10" title="Delete">
                        <Trash2 className="w-3.5 h-3.5 text-red-400" />
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Click outside to close dropdown */}
      {statusDropdown && (
        <div className="fixed inset-0 z-40" onClick={() => setStatusDropdown(null)} />
      )}

      {/* Invoice Form Modal */}
      {showForm && (
        <InvoiceForm
          invoice={editInvoice}
          onClose={() => { setShowForm(false); setEditInvoice(null); }}
          onSaved={async () => { setShowForm(false); setEditInvoice(null); await load(); onUpdate(); }}
        />
      )}

      {/* Print View Modal */}
      {printInvoice && (
        <InvoicePrint
          invoice={printInvoice}
          onClose={() => setPrintInvoice(null)}
        />
      )}
    </div>
  );
}
