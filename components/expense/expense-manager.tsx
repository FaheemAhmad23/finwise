'use client';

import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { Trash2, Plus, Edit2, ChevronLeft, ChevronRight, FileDown, Loader2, TrendingUp, TrendingDown, Wallet, PiggyBank, ArrowUpRight, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { generateMonthlyPDF } from '@/lib/expense-utils';
import { getAllData, saveData, deleteData, initDB } from '@/lib/storage';

interface Expense {
  id: string; category: string; amount: number;
  type: 'income'|'expense'; date: string; description: string;
}

interface BalanceSummary {
  currentBalance: number; savingsBalance: number;
  totalEarned: number; totalSpent: number; totalSaved: number;
}

const EXPENSE_CATEGORIES = [
  'Groceries','Transportation','Utilities','Entertainment','Healthcare',
  'Education','Rent/Mortgage','Savings','Dining Out','Shopping','Salon/Barber','Other',
];
const INCOME_CATEGORIES = ['Salary','Freelance','Business','Investment','Gift','Bonus','Other Income'];

const CAT_ICONS: Record<string,string> = {
  Groceries:'🛒', Transportation:'🚗', Utilities:'⚡', Entertainment:'🎬',
  Healthcare:'💊', Education:'📚', 'Rent/Mortgage':'🏠', Savings:'🐷',
  'Dining Out':'🍽️', Shopping:'🛍️', 'Salon/Barber':'💈', Other:'📌',
  Salary:'💼', Freelance:'💻', Business:'🏢', Investment:'📈',
  Gift:'🎁', Bonus:'⭐', 'Other Income':'💵',
};

function mKey(d:Date){return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;}
function mLabel(k:string){const[y,m]=k.split('-').map(Number);return new Date(y,m-1,1).toLocaleString('default',{month:'long',year:'numeric'});}
function prev(k:string){const[y,m]=k.split('-').map(Number);return mKey(new Date(y,m-2,1));}
function next(k:string){const[y,m]=k.split('-').map(Number);return mKey(new Date(y,m,1));}

const EMPTY = {type:'expense' as 'income'|'expense',category:'',amount:'',date:new Date().toISOString().slice(0,10),description:''};
const currency = 'PKR';

export default function ExpenseManager({ onUpdate }: { onUpdate: () => void }) {
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [month, setMonth] = useState(mKey(new Date()));
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<string|null>(null);
  const [form, setForm] = useState(EMPTY);
  const [showChart, setShowChart] = useState(false);

  const loadMonth = useCallback(async () => {
    setLoading(true);
    try {
      await initDB();
      const allExpenses = await getAllData<Expense>('expenses');
      const filtered = allExpenses.filter(e => {
        const eMonth = e.date.substring(0, 7);
        return eMonth === month;
      });
      setExpenses(filtered);
    } catch (e) {
      console.error('Failed to load expenses:', e);
      toast.error('Failed to load expenses');
    } finally {
      setLoading(false);
    }
  }, [month]);

  useEffect(() => { loadMonth(); }, [loadMonth]);

  const monthIncome = expenses.filter(e => e.type === 'income').reduce((s, e) => s + e.amount, 0);
  const monthSpent = expenses.filter(e => e.type === 'expense' && e.category !== 'Savings').reduce((s, e) => s + e.amount, 0);
  const monthSaved = expenses.filter(e => e.category === 'Savings').reduce((s, e) => s + e.amount, 0);
  const monthBalance = monthIncome - monthSpent - monthSaved;

  const handleSave = async () => {
    if (!form.category || !form.amount) { toast.error('Fill all fields'); return; }

    setSaving(true);
    try {
      const id = editId || `exp_${Date.now()}`;
      await saveData('expenses', {
        id,
        ...form,
        amount: parseFloat(form.amount),
      });
      toast.success(editId ? 'Updated' : 'Added');
      resetForm();
      await loadMonth();
      onUpdate();
    } catch (e) {
      console.error('Failed to save:', e);
      toast.error('Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await deleteData('expenses', id);
      toast.success('Deleted');
      await loadMonth();
      onUpdate();
    } catch (e) {
      console.error('Failed to delete:', e);
      toast.error('Failed to delete');
    }
  };

  const resetForm = () => { setForm(EMPTY); setEditId(null); setOpen(false); };
  const openEdit = (e: Expense) => {
    setForm({
      type: e.type,
      category: e.category,
      amount: e.amount.toString(),
      date: e.date,
      description: e.description || ''
    });
    setEditId(e.id);
    setOpen(true);
  };
  const fmt = (n: number) => n.toLocaleString('en-US', { maximumFractionDigits: 0 });

  const dailyData = expenses.reduce((acc: any[], e) => {
    const day = new Date(e.date).toLocaleDateString('default', { day: 'numeric', month: 'short' });
    const ex = acc.find(d => d.date === day);
    if (ex) { ex[e.type] = (ex[e.type] || 0) + e.amount; }
    else { acc.push({ date: day, income: 0, expense: 0, [e.type]: e.amount }); }
    return acc;
  }, []);

  const sorted = [...expenses].sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());

  if (loading) return (
    <div className="animate-pulse space-y-4">
      <div className="grid grid-cols-3 gap-3">{[1, 2, 3].map(i => <div key={i} className="h-28 bg-muted rounded-2xl" />)}</div>
      <div className="h-10 bg-muted rounded-2xl w-48 mx-auto" />
      <div className="space-y-2">{[1, 2, 3].map(i => <div key={i} className="h-14 bg-muted rounded-2xl" />)}</div>
    </div>
  );

  return (
    <div className="space-y-5">
      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div className="col-span-2 md:col-span-1 relative overflow-hidden rounded-2xl p-4 md:p-5"
          style={{ background: 'linear-gradient(135deg, oklch(0.65 0.195 34) 0%, oklch(0.52 0.18 30) 100%)', boxShadow: '0 8px 32px oklch(0.65 0.195 34 / 0.40)' }}>
          <div className="flex items-center gap-1.5 mb-3">
            <Wallet className="w-3.5 h-3.5 text-white/70" />
            <p className="text-[10px] font-bold text-white/70 uppercase tracking-widest">Total Income</p>
          </div>
          <p className="text-2xl md:text-3xl font-black text-white leading-none mb-1">{fmt(monthIncome)}</p>
          <p className="text-xs text-white/60 font-medium">{currency}</p>
        </div>

        <div className="col-span-1 relative overflow-hidden rounded-2xl p-4 md:p-5"
          style={{ background: 'oklch(0.72 0.18 150 / 0.12)', border: '1px solid oklch(0.72 0.18 150 / 0.22)', boxShadow: '0 4px 24px oklch(0 0 0 / 0.30)' }}>
          <div className="flex items-center gap-1.5 mb-3">
            <TrendingDown className="w-3.5 h-3.5 text-emerald-400/70" />
            <p className="text-[10px] font-bold text-emerald-400/70 uppercase tracking-widest">Spent</p>
          </div>
          <p className="text-2xl md:text-3xl font-black text-emerald-400 leading-none mb-1">{fmt(monthSpent)}</p>
          <p className="text-xs text-white/30 font-medium">{currency}</p>
        </div>

        <div className="col-span-1 rounded-2xl p-4 space-y-3" style={{ background: 'oklch(0.13 0.006 260)', border: '1px solid oklch(1 0 0 / 0.08)' }}>
          <p className="text-[10px] font-bold text-white/30 uppercase tracking-widest">Net</p>
          <span className={`text-2xl font-black ${monthBalance >= 0 ? 'text-white' : 'text-red-400'}`}>
            {monthBalance >= 0 ? '+' : ''}{fmt(monthBalance)}
          </span>
        </div>
      </div>

      {/* Month Nav */}
      <div className="flex items-center justify-between">
        <button onClick={() => setMonth(prev(month))}
          className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
          <ChevronLeft className="w-4 h-4" />
        </button>
        <div className="text-center">
          <h2 className="text-sm font-bold text-white">{mLabel(month)}</h2>
          <p className="text-xs text-white/30 mt-0.5">{expenses.length} transactions</p>
        </div>
        <button onClick={() => setMonth(next(month))}
          className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>

      {/* Add Transaction Button */}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogTrigger asChild>
          <Button className="w-full" onClick={() => { resetForm(); setOpen(true); }}>
            <Plus className="w-4 h-4 mr-2" /> Add Transaction
          </Button>
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editId ? 'Edit' : 'Add'} Transaction</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value as 'income' | 'expense' })}
              className="w-full px-3 py-2 bg-muted rounded-lg text-white">
              <option value="expense">Expense</option>
              <option value="income">Income</option>
            </select>
            <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}
              className="w-full px-3 py-2 bg-muted rounded-lg text-white">
              <option value="">Select Category</option>
              {(form.type === 'income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES).map(cat => (
                <option key={cat} value={cat}>{CAT_ICONS[cat]} {cat}</option>
              ))}
            </select>
            <Input type="number" placeholder="Amount" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
            <Input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
            <Input type="text" placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            <Button onClick={handleSave} disabled={saving} className="w-full">
              {saving ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : null}
              {editId ? 'Update' : 'Add'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Transactions List */}
      <div className="space-y-2">
        {sorted.length === 0 ? (
          <p className="text-center text-white/30 py-8">No transactions this month</p>
        ) : (
          sorted.map(exp => (
            <div key={exp.id} className="flex items-center justify-between p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
              <div className="flex-1">
                <p className="text-white font-medium text-sm">{CAT_ICONS[exp.category] || '📌'} {exp.category}</p>
                <p className="text-white/40 text-xs">{exp.date} • {exp.description}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className={`font-bold text-sm ${exp.type === 'income' ? 'text-emerald-400' : 'text-red-400'}`}>
                  {exp.type === 'income' ? '+' : '-'}{fmt(exp.amount)}
                </span>
                <button onClick={() => openEdit(exp)} className="p-1.5 hover:bg-white/10 rounded transition-colors">
                  <Edit2 className="w-4 h-4 text-white/50 hover:text-white" />
                </button>
                <button onClick={() => handleDelete(exp.id)} className="p-1.5 hover:bg-white/10 rounded transition-colors">
                  <Trash2 className="w-4 h-4 text-white/50 hover:text-red-400" />
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Chart */}
      {expenses.length > 0 && (
        <div>
          <button onClick={() => setShowChart(v => !v)}
            className="text-xs text-white/30 hover:text-white/60 flex items-center gap-1.5 transition-colors mb-3">
            <ArrowUpRight className="w-3 h-3" />
            {showChart ? 'Hide' : 'Show'} trend
          </button>
          {showChart && (
            <div className="rounded-2xl p-4" style={{ background: 'oklch(0.13 0.006 260)', border: '1px solid oklch(1 0 0 / 0.07)' }}>
              <ResponsiveContainer width="100%" height={200}>
                <LineChart data={dailyData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.04)" />
                  <XAxis dataKey="date" stroke="rgba(255,255,255,0.18)" style={{ fontSize: '10px' }} tickLine={false} axisLine={false} />
                  <YAxis stroke="rgba(255,255,255,0.18)" style={{ fontSize: '10px' }} tickLine={false} axisLine={false} />
                  <Tooltip formatter={(v: any) => `${Number(v).toLocaleString()} ${currency}`} contentStyle={{ background: 'oklch(0.16 0.007 260)', border: '1px solid oklch(1 0 0 / 0.10)', borderRadius: '12px', color: 'white', fontSize: '12px' }} />
                  <Line type="monotone" dataKey="income" stroke="#4ade80" strokeWidth={2} dot={false} />
                  <Line type="monotone" dataKey="expense" stroke="#e8622a" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
