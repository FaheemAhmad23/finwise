'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSession } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Trash2, Plus, Edit2, ChevronLeft, ChevronRight, FileDown, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { generateMonthlyPDF } from '@/lib/expense-utils';

interface Expense {
  id: string;
  category: string;
  amount: number;
  type: 'income' | 'expense';
  date: string;
  description: string;
  isDebtTransaction?: boolean;
  personName?: string;
  currency?: string;
}

const EXPENSE_CATEGORIES = [
  'Groceries', 'Transportation', 'Utilities', 'Entertainment',
  'Healthcare', 'Education', 'Rent/Mortgage', 'Savings',
  'Dining Out', 'Shopping', 'Salon/Barber', 'Other',
];

const INCOME_CATEGORIES = [
  'Salary', 'Freelance', 'Business', 'Investment', 'Gift', 'Bonus', 'Other Income',
];

const COLORS = ['#6366f1', '#ef4444', '#f59e0b', '#8b5cf6', '#ec4899', '#10b981', '#06b6d4'];

function getMonthKey(date: Date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function getMonthDisplayName(key: string) {
  const [year, month] = key.split('-').map(Number);
  return new Date(year, month - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
}

function getPreviousMonthKey(key: string) {
  const [year, month] = key.split('-').map(Number);
  const d = new Date(year, month - 2, 1);
  return getMonthKey(d);
}

function getNextMonthKey(key: string) {
  const [year, month] = key.split('-').map(Number);
  const d = new Date(year, month, 1);
  return getMonthKey(d);
}

export default function ExpenseManager({ onUpdate }: { onUpdate: () => void }) {
  const { data: session } = useSession();
  const currency = (session?.user as any)?.currency || 'PKR';

  const [expenses, setExpenses]       = useState<Expense[]>([]);
  const [currentMonth, setCurrentMonth] = useState<string>(getMonthKey(new Date()));
  const [loading, setLoading]         = useState(true);
  const [saving, setSaving]           = useState(false);
  const [open, setOpen]               = useState(false);
  const [editingId, setEditingId]     = useState<string | null>(null);
  const [formData, setFormData]       = useState({
    type: 'expense' as 'income' | 'expense',
    category: '',
    amount: '',
    date: new Date().toISOString().split('T')[0],
    description: '',
  });

  // ─── Fetch transactions for the current month ─────────────────────────────
  const loadExpenses = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/transactions?month=${currentMonth}`);
      if (!res.ok) throw new Error('Failed to fetch');
      const data = await res.json();
      setExpenses(data);
    } catch {
      toast.error('Failed to load transactions');
    } finally {
      setLoading(false);
    }
  }, [currentMonth]);

  useEffect(() => { loadExpenses(); }, [loadExpenses]);

  // ─── Derived totals ───────────────────────────────────────────────────────
  const totalIncome   = expenses.filter(e => e.type === 'income').reduce((s, e) => s + e.amount, 0);
  const totalExpenses = expenses.filter(e => e.type === 'expense').reduce((s, e) => s + e.amount, 0);
  const closingBalance = totalIncome - totalExpenses;

  // ─── Add / Edit transaction ───────────────────────────────────────────────
  const handleSave = async () => {
    if (!formData.category || !formData.amount) {
      toast.error('Please fill in all required fields');
      return;
    }

    setSaving(true);
    try {
      if (editingId) {
        const res = await fetch(`/api/transactions/${editingId}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: formData.type,
            category: formData.category,
            amount: parseFloat(formData.amount),
            date: formData.date,
            description: formData.description,
          }),
        });
        if (!res.ok) throw new Error('Update failed');
        toast.success('Transaction updated');
      } else {
        const res = await fetch('/api/transactions', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: formData.type,
            category: formData.category,
            amount: parseFloat(formData.amount),
            date: formData.date,
            description: formData.description,
          }),
        });
        if (!res.ok) throw new Error('Create failed');
        toast.success('Transaction added');
      }

      resetForm();
      await loadExpenses();
      onUpdate();
    } catch {
      toast.error('Failed to save transaction');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: string) => {
    try {
      const res = await fetch(`/api/transactions/${id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Delete failed');
      toast.success('Transaction deleted');
      await loadExpenses();
      onUpdate();
    } catch {
      toast.error('Failed to delete transaction');
    }
  };

  const handleEdit = (expense: Expense) => {
    setFormData({
      type: expense.type,
      category: expense.category,
      amount: expense.amount.toString(),
      date: expense.date,
      description: expense.description || '',
    });
    setEditingId(expense.id);
    setOpen(true);
  };

  const resetForm = () => {
    setFormData({
      type: 'expense',
      category: '',
      amount: '',
      date: new Date().toISOString().split('T')[0],
      description: '',
    });
    setEditingId(null);
    setOpen(false);
  };

  // ─── Chart data ───────────────────────────────────────────────────────────
  const categoryBreakdown = Object.entries(
    expenses.reduce((acc: Record<string, number>, e) => {
      if (e.type === 'expense') acc[e.category] = (acc[e.category] || 0) + e.amount;
      return acc;
    }, {})
  ).map(([name, value]) => ({ name, value }));

  const dailyData = expenses.reduce((acc: any[], e) => {
    const existing = acc.find(d => d.date === e.date);
    if (existing) {
      if (e.type === 'income')  existing.income  += e.amount;
      if (e.type === 'expense') existing.expense += e.amount;
    } else {
      acc.push({
        date: new Date(e.date).toLocaleDateString('default', { day: 'numeric', month: 'short' }),
        income:  e.type === 'income'  ? e.amount : 0,
        expense: e.type === 'expense' ? e.amount : 0,
      });
    }
    return acc;
  }, []).sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());

  // ─── Skeleton loading ────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="space-y-4 pb-4 animate-pulse">
        <div className="h-14 bg-slate-100 rounded-2xl" />
        <div className="h-24 bg-slate-100 rounded-2xl" />
        <div className="grid grid-cols-2 gap-3">
          <div className="h-20 bg-slate-100 rounded-2xl" />
          <div className="h-20 bg-slate-100 rounded-2xl" />
        </div>
        <div className="h-64 bg-slate-100 rounded-2xl" />
      </div>
    );
  }

  return (
    <div className="space-y-3 md:space-y-6 pb-4">
      {/* Month Navigation */}
      <div className="sticky top-0 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 z-20 py-3 md:py-4">
        <div className="flex items-center justify-between gap-2 md:gap-6">
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(getPreviousMonthKey(currentMonth))} className="h-10 w-10 p-0 flex-shrink-0">
            <ChevronLeft className="w-5 h-5" />
          </Button>

          <div className="text-center flex-1 min-w-0">
            <h2 className="text-lg md:text-2xl font-semibold truncate">{getMonthDisplayName(currentMonth)}</h2>
          </div>

          <div className="flex items-center gap-1 flex-shrink-0">
            <Button
              variant="ghost" size="sm"
              onClick={async () => {
                try {
                  const pdf = await generateMonthlyPDF(
                    getMonthDisplayName(currentMonth),
                    expenses, totalIncome, totalExpenses, closingBalance, 0
                  );
                  pdf.save(`FinWise-${currentMonth}.pdf`);
                  toast.success('PDF downloaded');
                } catch {
                  toast.error('Failed to generate PDF');
                }
              }}
              className="h-10 w-10 p-0"
              title="Download PDF"
            >
              <FileDown className="w-5 h-5" />
            </Button>

            <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(getNextMonthKey(currentMonth))} className="h-10 w-10 p-0 flex-shrink-0">
              <ChevronRight className="w-5 h-5" />
            </Button>
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="space-y-2 md:space-y-3 px-4 md:px-0">
        <div className="grid grid-cols-2 gap-2 md:gap-3">
          <div className="rounded-2xl bg-green-50 p-4 md:p-6 border border-green-200/60">
            <p className="text-xs md:text-sm text-muted-foreground font-medium">Income</p>
            <p className="text-xl md:text-2xl font-semibold text-green-600 mt-1">
              {totalIncome.toLocaleString()} <span className="text-sm">{currency}</span>
            </p>
          </div>
          <div className="rounded-2xl bg-red-50 p-4 md:p-6 border border-red-200/60">
            <p className="text-xs md:text-sm text-muted-foreground font-medium">Expenses</p>
            <p className="text-xl md:text-2xl font-semibold text-red-600 mt-1">
              {totalExpenses.toLocaleString()} <span className="text-sm">{currency}</span>
            </p>
          </div>
        </div>

        <div className={`rounded-2xl p-4 md:p-6 border ${closingBalance >= 0 ? 'bg-blue-50 border-blue-200/60' : 'bg-amber-50 border-amber-200/60'}`}>
          <p className="text-xs md:text-sm text-muted-foreground font-medium">Net Balance</p>
          <p className={`text-2xl md:text-3xl font-semibold mt-1 ${closingBalance >= 0 ? 'text-blue-600' : 'text-amber-600'}`}>
            {closingBalance >= 0 ? '+' : ''}{closingBalance.toLocaleString()} <span className="text-lg">{currency}</span>
          </p>
        </div>
      </div>

      {/* Transactions */}
      <div className="rounded-3xl border border-slate-200/60 bg-white overflow-hidden mx-4 md:mx-0">
        <div className="p-4 md:p-6 border-b border-slate-200/60 flex items-center justify-between">
          <div>
            <h3 className="font-semibold text-slate-900 text-sm md:text-base">Transactions</h3>
            <p className="text-xs text-muted-foreground mt-0.5">{expenses.length} this month</p>
          </div>

          <Dialog open={open} onOpenChange={(v) => { if (!v) resetForm(); setOpen(v); }}>
            <DialogTrigger asChild>
              <Button size="sm" onClick={resetForm} className="rounded-full h-10 w-10 p-0 md:h-auto md:w-auto md:px-4">
                <Plus className="w-5 h-5 md:w-4 md:h-4 md:mr-2" />
                <span className="hidden md:inline">Add</span>
              </Button>
            </DialogTrigger>
            <DialogContent className="rounded-2xl">
              <DialogHeader>
                <DialogTitle>{editingId ? 'Edit Transaction' : 'Add Transaction'}</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div className="space-y-2">
                  <label className="text-sm font-medium">Type</label>
                  <select
                    className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm"
                    value={formData.type}
                    onChange={(e: any) => setFormData({ ...formData, type: e.target.value, category: '' })}
                  >
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium">Category</label>
                  <select
                    className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm"
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                  >
                    <option value="">Select category</option>
                    {(formData.type === 'income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES).map(c => (
                      <option key={c} value={c}>{c}</option>
                    ))}
                  </select>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium">Amount ({currency})</label>
                  <Input type="number" placeholder="0.00" step="0.01" value={formData.amount} onChange={(e) => setFormData({ ...formData, amount: e.target.value })} className="rounded-xl" />
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium">Date</label>
                  <Input type="date" value={formData.date} onChange={(e) => setFormData({ ...formData, date: e.target.value })} className="rounded-xl" />
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium">Notes</label>
                  <Input placeholder="Optional…" value={formData.description} onChange={(e) => setFormData({ ...formData, description: e.target.value })} className="rounded-xl" />
                </div>
                <Button onClick={handleSave} className="w-full rounded-xl" disabled={saving}>
                  {saving ? <><Loader2 className="w-4 h-4 mr-2 animate-spin" />Saving…</> : `${editingId ? 'Save' : 'Add'} Transaction`}
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        {expenses.length === 0 ? (
          <div className="p-12 text-center">
            <p className="text-muted-foreground text-sm">No transactions for this month</p>
            <p className="text-xs text-muted-foreground mt-1">Tap + to add your first transaction</p>
          </div>
        ) : (
          <div className="divide-y divide-slate-100">
            {[...expenses]
              .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
              .map((trans) => (
                <div key={trans.id} className="p-3 md:p-4 flex items-center justify-between hover:bg-slate-50/50 transition-colors">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <p className="font-medium text-slate-900 text-sm truncate">{trans.category}</p>
                      <span className={`text-xs px-2 py-0.5 rounded-full whitespace-nowrap font-medium ${
                        trans.isDebtTransaction ? 'bg-purple-100 text-purple-700'
                          : trans.type === 'income' ? 'bg-green-100 text-green-700'
                          : 'bg-red-100 text-red-700'
                      }`}>
                        {trans.isDebtTransaction ? (trans.type === 'expense' ? 'Lent' : 'Repaid') : trans.type === 'income' ? 'In' : 'Out'}
                      </span>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {new Date(trans.date).toLocaleDateString('default', { month: 'short', day: 'numeric' })}
                      {trans.personName && <span> · {trans.personName}</span>}
                    </p>
                    {trans.description && <p className="text-xs text-muted-foreground mt-1 truncate">{trans.description}</p>}
                  </div>
                  <div className="flex items-center gap-2 md:gap-3 ml-3">
                    <p className={`font-semibold text-sm md:text-base min-w-fit ${trans.type === 'income' ? 'text-green-600' : 'text-red-600'}`}>
                      {trans.type === 'income' ? '+' : '-'}{trans.amount.toLocaleString()}
                    </p>
                    <div className="flex gap-1">
                      {!trans.isDebtTransaction && (
                        <Button variant="ghost" size="sm" onClick={() => handleEdit(trans)} className="h-8 w-8 p-0 hover:bg-slate-100">
                          <Edit2 className="w-3.5 h-3.5" />
                        </Button>
                      )}
                      <Button variant="ghost" size="sm" onClick={() => handleDelete(trans.id)} className="h-8 w-8 p-0 hover:bg-red-50">
                        <Trash2 className="w-3.5 h-3.5 text-red-500" />
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
          </div>
        )}
      </div>

      {/* Charts */}
      {expenses.length > 0 && (
        <div className="space-y-3 md:space-y-6 px-4 md:px-0">
          <Tabs defaultValue="category" className="w-full">
            <TabsList className="w-full rounded-full bg-slate-100 p-1 grid grid-cols-2">
              <TabsTrigger value="category" className="rounded-full text-xs md:text-sm">Categories</TabsTrigger>
              <TabsTrigger value="daily"    className="rounded-full text-xs md:text-sm">Daily Trend</TabsTrigger>
            </TabsList>

            <TabsContent value="category" className="mt-4 rounded-2xl border border-slate-200/60 bg-white p-4 md:p-6">
              {categoryBreakdown.length > 0 ? (
                <ResponsiveContainer width="100%" height={280}>
                  <PieChart>
                    <Pie data={categoryBreakdown} cx="50%" cy="50%" labelLine={false}
                      label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                      outerRadius={80} dataKey="value"
                    >
                      {categoryBreakdown.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                    </Pie>
                    <Tooltip formatter={(v: any) => `${Number(v).toLocaleString()} ${currency}`} />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-64 flex items-center justify-center text-muted-foreground text-sm">No expense data</div>
              )}
            </TabsContent>

            <TabsContent value="daily" className="mt-4 rounded-2xl border border-slate-200/60 bg-white p-4 md:p-6">
              {dailyData.length > 0 ? (
                <ResponsiveContainer width="100%" height={280}>
                  <LineChart data={dailyData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                    <XAxis dataKey="date" stroke="#94a3b8" style={{ fontSize: '11px' }} />
                    <YAxis stroke="#94a3b8" style={{ fontSize: '11px' }} />
                    <Tooltip formatter={(v: any) => `${Number(v).toLocaleString()} ${currency}`} />
                    <Line type="monotone" dataKey="income"  stroke="#22c55e" strokeWidth={2} dot={false} name="Income" />
                    <Line type="monotone" dataKey="expense" stroke="#ef4444" strokeWidth={2} dot={false} name="Expense" />
                  </LineChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-64 flex items-center justify-center text-muted-foreground text-sm">No trend data</div>
              )}
            </TabsContent>
          </Tabs>
        </div>
      )}
    </div>
  );
}
