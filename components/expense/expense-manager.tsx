'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSession } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { Trash2, Plus, Edit2, ChevronLeft, ChevronRight, FileDown, Loader2, TrendingUp, TrendingDown, ArrowUpRight } from 'lucide-react';
import { toast } from 'sonner';
import { generateMonthlyPDF } from '@/lib/expense-utils';

interface Expense {
  id: string; category: string; amount: number;
  type: 'income' | 'expense'; date: string; description: string;
  isDebtTransaction?: boolean; personName?: string; currency?: string;
}

const EXPENSE_CATEGORIES = [
  'Groceries','Transportation','Utilities','Entertainment',
  'Healthcare','Education','Rent/Mortgage','Savings',
  'Dining Out','Shopping','Salon/Barber','Other',
];
const INCOME_CATEGORIES = ['Salary','Freelance','Business','Investment','Gift','Bonus','Other Income'];

const CAT_ICONS: Record<string, string> = {
  Groceries:'🛒', Transportation:'🚗', Utilities:'⚡', Entertainment:'🎬',
  Healthcare:'💊', Education:'📚', 'Rent/Mortgage':'🏠', Savings:'💰',
  'Dining Out':'🍽️', Shopping:'🛍️', 'Salon/Barber':'💈', Other:'📌',
  Salary:'💼', Freelance:'💻', Business:'🏢', Investment:'📈',
  Gift:'🎁', Bonus:'⭐', 'Other Income':'💵',
};

function getMonthKey(d: Date) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`; }
function getMonthLabel(key: string) {
  const [y,m] = key.split('-').map(Number);
  return new Date(y,m-1,1).toLocaleString('default',{month:'long',year:'numeric'});
}
function prevMonth(key: string) { const [y,m]=key.split('-').map(Number); return getMonthKey(new Date(y,m-2,1)); }
function nextMonth(key: string) { const [y,m]=key.split('-').map(Number); return getMonthKey(new Date(y,m,1)); }

const EMPTY_FORM = { type: 'expense' as 'income'|'expense', category:'', amount:'', date: new Date().toISOString().slice(0,10), description:'' };

export default function ExpenseManager({ onUpdate }: { onUpdate: () => void }) {
  const { data: session } = useSession();
  const currency = (session?.user as any)?.currency || 'PKR';

  const [expenses, setExpenses]         = useState<Expense[]>([]);
  const [currentMonth, setCurrentMonth] = useState(getMonthKey(new Date()));
  const [loading, setLoading]           = useState(true);
  const [saving, setSaving]             = useState(false);
  const [open, setOpen]                 = useState(false);
  const [editingId, setEditingId]       = useState<string|null>(null);
  const [form, setForm]                 = useState(EMPTY_FORM);
  const [showChart, setShowChart]       = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/transactions?month=${currentMonth}`);
      setExpenses(await res.json());
    } catch { toast.error('Failed to load'); }
    finally { setLoading(false); }
  }, [currentMonth]);

  useEffect(() => { load(); }, [load]);

  const totalIncome   = expenses.filter(e => e.type==='income').reduce((s,e) => s+e.amount, 0);
  const totalExpenses = expenses.filter(e => e.type==='expense').reduce((s,e) => s+e.amount, 0);
  const balance       = totalIncome - totalExpenses;
  const savingsRate   = totalIncome > 0 ? Math.round((balance / totalIncome) * 100) : 0;

  const handleSave = async () => {
    if (!form.category || !form.amount) { toast.error('Fill all fields'); return; }
    setSaving(true);
    try {
      const url    = editingId ? `/api/transactions/${editingId}` : '/api/transactions';
      const method = editingId ? 'PATCH' : 'POST';
      const res    = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify({...form, amount: parseFloat(form.amount)}) });
      if (!res.ok) throw new Error();
      toast.success(editingId ? 'Updated' : 'Added');
      resetForm(); await load(); onUpdate();
    } catch { toast.error('Failed'); }
    finally { setSaving(false); }
  };

  const handleDelete = async (id: string) => {
    try {
      await fetch(`/api/transactions/${id}`, {method:'DELETE'});
      toast.success('Deleted'); await load(); onUpdate();
    } catch { toast.error('Failed'); }
  };

  const resetForm = () => { setForm(EMPTY_FORM); setEditingId(null); setOpen(false); };
  const openEdit  = (e: Expense) => { setForm({type:e.type,category:e.category,amount:e.amount.toString(),date:e.date,description:e.description||''}); setEditingId(e.id); setOpen(true); };

  const fmt = (n: number) => n.toLocaleString();

  // Chart data
  const dailyData = expenses.reduce((acc: any[], e) => {
    const day = new Date(e.date).toLocaleDateString('default',{day:'numeric',month:'short'});
    const ex  = acc.find(d => d.date===day);
    if (ex) { ex[e.type] = (ex[e.type]||0) + e.amount; }
    else    { acc.push({date:day, income:0, expense:0, [e.type]:e.amount}); }
    return acc;
  }, []);

  if (loading) return (
    <div className="animate-pulse space-y-4">
      <div className="h-10 bg-muted rounded-2xl w-48 mx-auto" />
      <div className="grid grid-cols-3 gap-3">
        <div className="col-span-2 h-36 bg-muted rounded-2xl" />
        <div className="space-y-3"><div className="h-16 bg-muted rounded-2xl"/><div className="h-16 bg-muted rounded-2xl"/></div>
      </div>
      <div className="h-64 bg-muted rounded-2xl" />
    </div>
  );

  const sorted = [...expenses].sort((a,b) => new Date(b.date).getTime() - new Date(a.date).getTime());

  return (
    <div className="space-y-5">

      {/* ── Month Nav ──────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <button onClick={() => setCurrentMonth(prevMonth(currentMonth))}
          className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
          <ChevronLeft className="w-4 h-4" />
        </button>

        <div className="text-center">
          <h2 className="text-base font-bold text-white">{getMonthLabel(currentMonth)}</h2>
          <p className="text-xs text-white/30 mt-0.5">{expenses.length} transactions</p>
        </div>

        <div className="flex items-center gap-1">
          <button
            onClick={async () => {
              try { const pdf = await generateMonthlyPDF(getMonthLabel(currentMonth),expenses,totalIncome,totalExpenses,balance,0); pdf.save(`FinWise-${currentMonth}.pdf`); toast.success('PDF ready'); }
              catch { toast.error('PDF failed'); }
            }}
            className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all"
            title="Download PDF"
          >
            <FileDown className="w-4 h-4" />
          </button>
          <button onClick={() => setCurrentMonth(nextMonth(currentMonth))}
            className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* ── Hero Stats Grid ────────────────────────────────── */}
      <div className="grid grid-cols-3 gap-3">

        {/* Balance — Hero Card (takes 2 cols) */}
        <div className="col-span-2 relative overflow-hidden rounded-2xl p-5"
          style={{ background: 'linear-gradient(135deg, oklch(0.17 0.008 265) 0%, oklch(0.13 0.006 265) 100%)', border: '1px solid oklch(1 0 0 / 0.08)', boxShadow: '0 4px 24px oklch(0 0 0 / 0.35)' }}
        >
          {/* Background glow */}
          <div className="absolute top-0 right-0 w-32 h-32 rounded-full opacity-20"
            style={{background: balance >= 0 ? 'radial-gradient(circle, #4ade80, transparent)' : 'radial-gradient(circle, #f87171, transparent)', filter: 'blur(30px)'}} />

          <p className="text-xs font-semibold text-white/40 uppercase tracking-widest mb-3">Net Balance</p>
          <p className={`text-3xl md:text-4xl font-black leading-none mb-1 ${balance >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
            {balance >= 0 ? '+' : ''}{fmt(balance)}
          </p>
          <p className="text-sm text-white/30 font-medium">{currency}</p>

          <div className="mt-4 flex items-center gap-2">
            <div className={`flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full ${balance >= 0 ? 'bg-emerald-400/10 text-emerald-400' : 'bg-red-400/10 text-red-400'}`}>
              {balance >= 0 ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
              {savingsRate}% saved
            </div>
          </div>
        </div>

        {/* Right column — Income + Expenses stacked */}
        <div className="flex flex-col gap-3">
          {/* Income */}
          <div className="flex-1 rounded-2xl p-4 relative overflow-hidden"
            style={{ background: 'oklch(0.72 0.18 150 / 0.10)', border: '1px solid oklch(0.72 0.18 150 / 0.18)', boxShadow: '0 4px 16px oklch(0 0 0 / 0.25)' }}
          >
            <p className="text-[10px] font-bold text-white/35 uppercase tracking-widest mb-2">Income</p>
            <p className="text-lg font-black text-emerald-400 leading-none">{fmt(totalIncome)}</p>
            <p className="text-[10px] text-white/25 mt-0.5">{currency}</p>
            <TrendingUp className="absolute bottom-3 right-3 w-5 h-5 text-emerald-400/20" />
          </div>

          {/* Expenses */}
          <div className="flex-1 rounded-2xl p-4 relative overflow-hidden"
            style={{ background: 'oklch(0.62 0.22 27 / 0.10)', border: '1px solid oklch(0.62 0.22 27 / 0.18)', boxShadow: '0 4px 16px oklch(0 0 0 / 0.25)' }}
          >
            <p className="text-[10px] font-bold text-white/35 uppercase tracking-widest mb-2">Spent</p>
            <p className="text-lg font-black text-red-400 leading-none">{fmt(totalExpenses)}</p>
            <p className="text-[10px] text-white/25 mt-0.5">{currency}</p>
            <TrendingDown className="absolute bottom-3 right-3 w-5 h-5 text-red-400/20" />
          </div>
        </div>
      </div>

      {/* ── Trend Chart (toggle) ───────────────────────────── */}
      {expenses.length > 0 && (
        <div>
          <button onClick={() => setShowChart(v => !v)}
            className="text-xs text-white/30 hover:text-white/60 flex items-center gap-1.5 transition-colors mb-3">
            <ArrowUpRight className="w-3 h-3" />
            {showChart ? 'Hide' : 'Show'} monthly trend
          </button>
          {showChart && (
            <div className="rounded-2xl p-4" style={{background:'oklch(0.13 0.006 260)', border:'1px solid oklch(1 0 0 / 0.07)'}}>
              <ResponsiveContainer width="100%" height={160}>
                <LineChart data={dailyData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.04)" />
                  <XAxis dataKey="date" stroke="rgba(255,255,255,0.20)" style={{fontSize:'10px'}} tickLine={false} axisLine={false} />
                  <YAxis stroke="rgba(255,255,255,0.20)" style={{fontSize:'10px'}} tickLine={false} axisLine={false} />
                  <Tooltip
                    formatter={(v:any) => `${Number(v).toLocaleString()} ${currency}`}
                    contentStyle={{background:'oklch(0.16 0.007 260)',border:'1px solid oklch(1 0 0 / 0.10)',borderRadius:'12px',color:'white',fontSize:'12px'}}
                  />
                  <Line type="monotone" dataKey="income"  stroke="#4ade80" strokeWidth={2} dot={false} />
                  <Line type="monotone" dataKey="expense" stroke="#e8622a" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
              <div className="flex items-center gap-4 mt-2 justify-center">
                <span className="flex items-center gap-1.5 text-xs text-white/35"><span className="w-3 h-0.5 bg-emerald-400 rounded inline-block"/>Income</span>
                <span className="flex items-center gap-1.5 text-xs text-white/35"><span className="w-3 h-0.5 bg-primary rounded inline-block"/>Spending</span>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ── Statement ─────────────────────────────────────── */}
      <div>
        {/* Statement header */}
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="font-bold text-white text-sm">Statement</h3>
            <p className="text-xs text-white/30 mt-0.5">{getMonthLabel(currentMonth)}</p>
          </div>

          {/* Add button */}
          <Dialog open={open} onOpenChange={v => { if (!v) resetForm(); setOpen(v); }}>
            <DialogTrigger asChild>
              <button onClick={resetForm}
                className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all glow-orange-sm"
                style={{background:'oklch(0.65 0.195 34)'}}>
                <Plus className="w-3.5 h-3.5" /> Add
              </button>
            </DialogTrigger>
            <DialogContent className="rounded-2xl max-w-sm">
              <DialogHeader><DialogTitle>{editingId ? 'Edit Transaction' : 'Add Transaction'}</DialogTitle></DialogHeader>
              <div className="space-y-4 pt-1">
                {/* Type toggle */}
                <div className="grid grid-cols-2 gap-2">
                  {(['expense','income'] as const).map(t => (
                    <button key={t} onClick={() => setForm({...form, type:t, category:''})}
                      className={`py-2.5 rounded-xl text-sm font-semibold transition-all ${form.type===t ? 'bg-primary text-white' : 'bg-white/[0.05] text-white/50 hover:text-white'}`}>
                      {t === 'expense' ? '↑ Expense' : '↓ Income'}
                    </button>
                  ))}
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Category</label>
                  <select className="w-full rounded-xl border border-border bg-card text-foreground px-4 py-2.5 text-sm"
                    value={form.category} onChange={e => setForm({...form, category:e.target.value})}>
                    <option value="">Select category</option>
                    {(form.type==='income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES).map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Amount ({currency})</label>
                  <Input type="number" placeholder="0.00" step="0.01" value={form.amount}
                    onChange={e => setForm({...form, amount:e.target.value})} className="rounded-xl" />
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Date</label>
                  <Input type="date" value={form.date} onChange={e => setForm({...form, date:e.target.value})} className="rounded-xl" />
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Notes</label>
                  <Input placeholder="Optional…" value={form.description}
                    onChange={e => setForm({...form, description:e.target.value})} className="rounded-xl" />
                </div>
                <Button onClick={handleSave} className="w-full rounded-xl" disabled={saving}>
                  {saving ? <><Loader2 className="w-4 h-4 mr-2 animate-spin"/>Saving…</> : editingId ? 'Save Changes' : 'Add Transaction'}
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        {/* Transaction list */}
        {expenses.length === 0 ? (
          <div className="py-16 text-center">
            <p className="text-4xl mb-3">💸</p>
            <p className="text-white/40 text-sm font-medium">No transactions yet</p>
            <p className="text-white/20 text-xs mt-1">Tap Add to log your first entry</p>
          </div>
        ) : (
          <div className="space-y-1.5">
            {sorted.map(t => (
              <div key={t.id}
                className="flex items-center gap-3 px-4 py-3 rounded-2xl group transition-all hover:bg-white/[0.04]"
                style={{border:'1px solid transparent'}}
                onMouseEnter={e => (e.currentTarget.style.borderColor='oklch(1 0 0 / 0.06)')}
                onMouseLeave={e => (e.currentTarget.style.borderColor='transparent')}
              >
                {/* Category icon */}
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-lg flex-shrink-0 ${t.type==='income' ? 'bg-emerald-400/10' : 'bg-primary/10'}`}>
                  {CAT_ICONS[t.category] || '📌'}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="font-semibold text-white text-sm truncate">{t.category}</p>
                    <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-bold leading-none ${
                      t.isDebtTransaction ? 'bg-violet-500/15 text-violet-400'
                      : t.type==='income' ? 'bg-emerald-500/15 text-emerald-400'
                      : 'bg-primary/15 text-primary'}`}>
                      {t.isDebtTransaction ? (t.type==='expense'?'Lent':'Repaid') : t.type==='income' ? 'IN' : 'OUT'}
                    </span>
                  </div>
                  <p className="text-xs text-white/30 mt-0.5">
                    {new Date(t.date).toLocaleDateString('default',{month:'short',day:'numeric'})}
                    {t.description && ` · ${t.description}`}
                  </p>
                </div>

                {/* Amount */}
                <div className="flex items-center gap-2 flex-shrink-0">
                  <p className={`font-bold text-base tabular-nums ${t.type==='income' ? 'text-emerald-400' : 'text-red-400'}`}>
                    {t.type==='income' ? '+' : '-'}{fmt(t.amount)}
                  </p>

                  {/* Actions — show on hover */}
                  <div className="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    {!t.isDebtTransaction && (
                      <button onClick={() => openEdit(t)}
                        className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/[0.08] text-white/30 hover:text-white transition-all">
                        <Edit2 className="w-3 h-3" />
                      </button>
                    )}
                    <button onClick={() => handleDelete(t.id)}
                      className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-500/10 text-white/30 hover:text-red-400 transition-all">
                      <Trash2 className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
