'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSession } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { Trash2, Plus, Edit2, ChevronLeft, ChevronRight, FileDown, Loader2, TrendingUp, TrendingDown, Wallet, PiggyBank, ArrowUpRight, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { generateMonthlyPDF } from '@/lib/expense-utils';

interface Expense {
  id: string; category: string; amount: number;
  type: 'income'|'expense'; date: string; description: string;
  isDebtTransaction?: boolean; personName?: string;
}

interface BalanceSummary {
  currentBalance: number; savingsBalance: number; totalAssets: number;
  totalEarned: number; totalSpent: number; totalSaved: number; totalLoansOut: number;
  hasOpeningBalance: boolean;
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
  Gift:'🎁', Bonus:'⭐', 'Other Income':'💵', 'Lent Money':'🤝', 'Debt Repaid':'✅',
  'Savings Withdrawal':'🏦', 'Opening Balance':'🏁', 'Opening Savings':'🏦',
};

function mKey(d:Date){return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;}
function mLabel(k:string){const[y,m]=k.split('-').map(Number);return new Date(y,m-1,1).toLocaleString('default',{month:'long',year:'numeric'});}
function prev(k:string){const[y,m]=k.split('-').map(Number);return mKey(new Date(y,m-2,1));}
function next(k:string){const[y,m]=k.split('-').map(Number);return mKey(new Date(y,m,1));}

const EMPTY = {type:'expense' as 'income'|'expense',category:'',amount:'',date:new Date().toISOString().slice(0,10),description:''};

export default function ExpenseManager({ onUpdate }: { onUpdate: () => void }) {
  const { data: session } = useSession();
  const currency = (session?.user as any)?.currency || 'PKR';

  const [expenses, setExpenses]     = useState<Expense[]>([]);
  const [month, setMonth]           = useState(mKey(new Date()));
  const [loading, setLoading]       = useState(true);
  const [balanceData, setBalanceData] = useState<BalanceSummary|null>(null);
  const [saving, setSaving]         = useState(false);
  const [open, setOpen]             = useState(false);
  const [editId, setEditId]         = useState<string|null>(null);
  const [form, setForm]             = useState(EMPTY);
  const [showChart, setShowChart]     = useState(false);
  // Withdraw from savings
  const [withdrawOpen, setWithdrawOpen]   = useState(false);
  const [withdrawAmt, setWithdrawAmt]     = useState('');
  const [withdrawing, setWithdrawing]     = useState(false);
  // Opening balance setup
  const [setupOpen, setSetupOpen]         = useState(false);
  const [setupCurrent, setSetupCurrent]   = useState('');
  const [setupSavings, setSetupSavings]   = useState('');
  const [settingUp, setSettingUp]         = useState(false);

  const loadMonth = useCallback(async () => {
    setLoading(true);
    try { const r = await fetch(`/api/transactions?month=${month}`); setExpenses(await r.json()); }
    catch { toast.error('Failed to load'); }
    finally { setLoading(false); }
  }, [month]);

  const loadBalance = useCallback(async () => {
    try { const r = await fetch('/api/balance-summary'); setBalanceData(await r.json()); }
    catch {}
  }, []);

  useEffect(() => { loadMonth(); }, [loadMonth]);
  useEffect(() => { loadBalance(); }, [loadBalance]);

  // Monthly totals
  const monthIncome   = expenses.filter(e=>e.type==='income'&&!e.isDebtTransaction).reduce((s,e)=>s+e.amount,0);
  const monthSpent    = expenses.filter(e=>e.type==='expense'&&!e.isDebtTransaction&&e.category!=='Savings').reduce((s,e)=>s+e.amount,0);
  const monthSaved    = expenses.filter(e=>e.category==='Savings').reduce((s,e)=>s+e.amount,0);
  const monthBalance  = monthIncome - monthSpent - monthSaved;

  const handleSave = async () => {
    if (!form.category || !form.amount) { toast.error('Fill all fields'); return; }

    // Low balance alert for expenses from current account
    if (form.type === 'expense' && form.category !== 'Savings' && balanceData) {
      const amt = parseFloat(form.amount);
      if (amt > balanceData.currentBalance && balanceData.savingsBalance > 0) {
        const wantWithdraw = confirm(
          `⚠️ Low Balance Alert!\n\nCurrent Balance: ${balanceData.currentBalance.toLocaleString()} ${currency}\nTransaction: ${amt.toLocaleString()} ${currency}\n\nWould you like to withdraw ${(amt - balanceData.currentBalance).toLocaleString()} ${currency} from your Savings Account first?`
        );
        if (wantWithdraw) {
          const withdrawNeeded = Math.min(amt - balanceData.currentBalance, balanceData.savingsBalance);
          await fetch('/api/transactions', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({type:'income',category:'Savings Withdrawal',amount:withdrawNeeded,date:form.date,description:'[SAVINGS_WITHDRAWAL] Auto-withdrawal to cover expense'}),
          });
          toast.info(`Withdrew ${withdrawNeeded.toLocaleString()} ${currency} from Savings`);
        }
      }
    }

    setSaving(true);
    try {
      const url    = editId ? `/api/transactions/${editId}` : '/api/transactions';
      const method = editId ? 'PATCH' : 'POST';
      await fetch(url,{method,headers:{'Content-Type':'application/json'},body:JSON.stringify({...form,amount:parseFloat(form.amount)})});
      toast.success(editId?'Updated':'Added');
      resetForm(); await loadMonth(); await loadBalance(); onUpdate();
    } catch { toast.error('Failed'); }
    finally { setSaving(false); }
  };

  const handleDelete = async (id:string) => {
    try { await fetch(`/api/transactions/${id}`,{method:'DELETE'}); toast.success('Deleted'); await loadMonth(); await loadBalance(); onUpdate(); }
    catch { toast.error('Failed'); }
  };

  const resetForm = () => { setForm(EMPTY); setEditId(null); setOpen(false); };
  const openEdit  = (e:Expense) => { setForm({type:e.type,category:e.category,amount:e.amount.toString(),date:e.date,description:e.description||''}); setEditId(e.id); setOpen(true); };
  const fmt       = (n:number) => n.toLocaleString('en-US',{maximumFractionDigits:0});

  // ─── Withdraw from savings ─────────────────────────────
  const handleWithdraw = async () => {
    const amt = parseFloat(withdrawAmt);
    if (!amt || amt <= 0) { toast.error('Enter a valid amount'); return; }
    if (balanceData && amt > balanceData.savingsBalance) {
      toast.error(`Only ${fmt(balanceData.savingsBalance)} ${currency} available in savings`);
      return;
    }
    setWithdrawing(true);
    try {
      await fetch('/api/transactions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: 'income',
          category: 'Savings Withdrawal',
          amount: amt,
          date: new Date().toISOString().slice(0, 10),
          description: '[SAVINGS_WITHDRAWAL] Manual withdrawal from savings',
        }),
      });
      toast.success(`${fmt(amt)} ${currency} moved to current balance`);
      setWithdrawOpen(false); setWithdrawAmt('');
      await loadMonth(); await loadBalance(); onUpdate();
    } catch { toast.error('Failed to withdraw'); }
    finally { setWithdrawing(false); }
  };

  // ─── Opening balance setup ─────────────────────────────
  const handleSetupBalance = async () => {
    const curr = parseFloat(setupCurrent) || 0;
    const savs = parseFloat(setupSavings) || 0;
    if (curr <= 0 && savs <= 0) { toast.error('Enter at least one balance'); return; }
    setSettingUp(true);
    try {
      const today = new Date().toISOString().slice(0, 10);
      const reqs = [];
      if (curr > 0) reqs.push(fetch('/api/transactions', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'income', category: 'Opening Balance', amount: curr, date: today, description: '[INITIAL_BALANCE] Opening current account balance' }),
      }));
      if (savs > 0) reqs.push(fetch('/api/transactions', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'income', category: 'Opening Savings', amount: savs, date: today, description: '[INITIAL_SAVINGS] Opening savings account balance' }),
      }));
      await Promise.all(reqs);
      toast.success('Account balances set up successfully!');
      setSetupOpen(false); setSetupCurrent(''); setSetupSavings('');
      await loadMonth(); await loadBalance(); onUpdate();
    } catch { toast.error('Failed to set up balances'); }
    finally { setSettingUp(false); }
  };



  const dailyData = expenses.reduce((acc:any[],e) => {
    const day=new Date(e.date).toLocaleDateString('default',{day:'numeric',month:'short'});
    const ex=acc.find(d=>d.date===day);
    if(ex){ex[e.type]=(ex[e.type]||0)+e.amount;}
    else{acc.push({date:day,income:0,expense:0,[e.type]:e.amount});}
    return acc;
  },[]);

  const sorted = [...expenses].sort((a,b)=>new Date(b.date).getTime()-new Date(a.date).getTime());

  if (loading && !balanceData) return (
    <div className="animate-pulse space-y-4">
      <div className="grid grid-cols-3 gap-3">{[1,2,3].map(i=><div key={i} className="h-28 bg-muted rounded-2xl"/>)}</div>
      <div className="h-10 bg-muted rounded-2xl w-48 mx-auto"/>
      <div className="space-y-2">{[1,2,3].map(i=><div key={i} className="h-14 bg-muted rounded-2xl"/>)}</div>
    </div>
  );

  const cb = balanceData?.currentBalance ?? 0;
  const sb = balanceData?.savingsBalance ?? 0;

  return (
    <div className="space-y-5">

      {/* ── Opening Balance Setup Banner ──────────────────── */}
      {balanceData && !balanceData.hasOpeningBalance && expenses.length === 0 && (
        <div className="rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-4"
          style={{background:'linear-gradient(135deg, oklch(0.65 0.195 34 / 0.12) 0%, oklch(0.52 0.18 30 / 0.08) 100%)',border:'1px solid oklch(0.65 0.195 34 / 0.25)'}}>
          <div className="flex-1">
            <p className="font-bold text-white text-sm">Set up your starting balance</p>
            <p className="text-xs text-white/45 mt-1">You already have money? Tell FinWise your current balance and savings so your dashboard is accurate from day one.</p>
          </div>
          <button onClick={() => setSetupOpen(true)}
            className="flex-shrink-0 px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-all glow-orange-sm"
            style={{background:'oklch(0.65 0.195 34)'}}>
            Set Up Balances
          </button>
        </div>
      )}

      {/* Also show a subtle link if they already have transactions */}
      {balanceData && !balanceData.hasOpeningBalance && expenses.length > 0 && (
        <button onClick={() => setSetupOpen(true)}
          className="text-xs text-white/30 hover:text-primary transition-colors flex items-center gap-1.5">
          🏁 Set opening balance for accurate history
        </button>
      )}

      {/* ── Hero Stats Grid ────────────────────────────────── */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">

        {/* Current Balance — full row on mobile (col-span-2), 1/3 on desktop */}
        <div className="col-span-2 md:col-span-1 relative overflow-hidden rounded-2xl p-4 md:p-5"
          style={{background:'linear-gradient(135deg, oklch(0.65 0.195 34) 0%, oklch(0.52 0.18 30) 100%)',boxShadow:'0 8px 32px oklch(0.65 0.195 34 / 0.40)'}}>
          <div className="absolute top-0 right-0 w-24 h-24 rounded-full opacity-20"
            style={{background:'radial-gradient(circle,white,transparent)',filter:'blur(20px)'}}/>
          <div className="flex items-center gap-1.5 mb-3">
            <Wallet className="w-3.5 h-3.5 text-white/70"/>
            <p className="text-[10px] font-bold text-white/70 uppercase tracking-widest">Current Balance</p>
          </div>
          <p className="text-2xl md:text-3xl font-black text-white leading-none mb-1">{fmt(cb)}</p>
          <p className="text-xs text-white/60 font-medium">{currency}</p>
          {cb < 5000 && cb >= 0 && (
            <div className="mt-3 flex items-center gap-1 text-[10px] text-white/80 bg-white/15 rounded-full px-2 py-0.5 w-fit">
              <AlertTriangle className="w-3 h-3"/>Low balance
            </div>
          )}
        </div>

        {/* Savings Account — green */}
        <div className="col-span-1 relative overflow-hidden rounded-2xl p-4 md:p-5"
          style={{background:'oklch(0.72 0.18 150 / 0.12)',border:'1px solid oklch(0.72 0.18 150 / 0.22)',boxShadow:'0 4px 24px oklch(0 0 0 / 0.30)'}}>
          <div className="absolute top-0 right-0 w-20 h-20 rounded-full opacity-10"
            style={{background:'radial-gradient(circle,#4ade80,transparent)',filter:'blur(15px)'}}/>
          <div className="flex items-center gap-1.5 mb-3">
            <PiggyBank className="w-3.5 h-3.5 text-emerald-400/70"/>
            <p className="text-[10px] font-bold text-emerald-400/70 uppercase tracking-widest">Savings</p>
          </div>
          <p className="text-2xl md:text-3xl font-black text-emerald-400 leading-none mb-1">{fmt(sb)}</p>
          <p className="text-xs text-white/30 font-medium">{currency}</p>
          {balanceData && balanceData.totalSaved > 0 && (
            <p className="mt-2 text-[10px] text-emerald-400/60">+{fmt(monthSaved)} mo.</p>
          )}
          {/* Withdraw button */}
          {sb > 0 && (
            <button onClick={() => setWithdrawOpen(true)}
              className="mt-3 text-[10px] font-bold text-emerald-400/60 hover:text-emerald-400 flex items-center gap-1 transition-colors">
              ↓ Withdraw
            </button>
          )}
        </div>

        {/* This Month Summary */}
        <div className="col-span-1 rounded-2xl p-4 space-y-3"
          style={{background:'oklch(0.13 0.006 260)',border:'1px solid oklch(1 0 0 / 0.08)'}}>
          <p className="text-[10px] font-bold text-white/30 uppercase tracking-widest">This Month</p>
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-1.5">
                <TrendingUp className="w-3 h-3 text-emerald-400"/>
                <span className="text-[11px] text-white/50">Earned</span>
              </div>
              <span className="text-xs font-bold text-emerald-400">{fmt(monthIncome)}</span>
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-1.5">
                <TrendingDown className="w-3 h-3 text-red-400"/>
                <span className="text-[11px] text-white/50">Spent</span>
              </div>
              <span className="text-xs font-bold text-red-400">{fmt(monthSpent)}</span>
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-1.5">
                <PiggyBank className="w-3 h-3 text-emerald-400/70"/>
                <span className="text-[11px] text-white/50">Saved</span>
              </div>
              <span className="text-xs font-bold text-emerald-400/70">{fmt(monthSaved)}</span>
            </div>
            <div className="h-px bg-white/[0.06]"/>
            <div className="flex items-center justify-between">
              <span className="text-[11px] text-white/40 font-medium">Net</span>
              <span className={`text-xs font-black ${monthBalance>=0?'text-white':'text-red-400'}`}>
                {monthBalance>=0?'+':''}{fmt(monthBalance)}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* ── Month Nav ──────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <button onClick={()=>setMonth(prev(month))}
          className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
          <ChevronLeft className="w-4 h-4"/>
        </button>
        <div className="text-center">
          <h2 className="text-sm font-bold text-white">{mLabel(month)}</h2>
          <p className="text-xs text-white/30 mt-0.5">{expenses.length} transactions</p>
        </div>
        <div className="flex gap-1">
          <button
            onClick={async()=>{
              try{const pdf=await generateMonthlyPDF(mLabel(month),expenses,monthIncome,monthSpent+monthSaved,monthBalance,0);pdf.save(`FinWise-${month}.pdf`);toast.success('PDF ready');}
              catch{toast.error('PDF failed');}
            }}
            className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
            <FileDown className="w-4 h-4"/>
          </button>
          <button onClick={()=>setMonth(next(month))}
            className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] text-white/50 hover:text-white transition-all">
            <ChevronRight className="w-4 h-4"/>
          </button>
        </div>
      </div>

      {/* ── Trend Chart Toggle ─────────────────────────────── */}
      {expenses.length > 0 && (
        <div>
          <button onClick={()=>setShowChart(v=>!v)}
            className="text-xs text-white/30 hover:text-white/60 flex items-center gap-1.5 transition-colors mb-3">
            <ArrowUpRight className="w-3 h-3"/>
            {showChart?'Hide':'Show'} monthly trend
          </button>
          {showChart && (
            <div className="rounded-2xl p-4" style={{background:'oklch(0.13 0.006 260)',border:'1px solid oklch(1 0 0 / 0.07)'}}>
              <ResponsiveContainer width="100%" height={150}>
                <LineChart data={dailyData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.04)"/>
                  <XAxis dataKey="date" stroke="rgba(255,255,255,0.18)" style={{fontSize:'10px'}} tickLine={false} axisLine={false}/>
                  <YAxis stroke="rgba(255,255,255,0.18)" style={{fontSize:'10px'}} tickLine={false} axisLine={false}/>
                  <Tooltip formatter={(v:any)=>`${Number(v).toLocaleString()} ${currency}`} contentStyle={{background:'oklch(0.16 0.007 260)',border:'1px solid oklch(1 0 0 / 0.10)',borderRadius:'12px',color:'white',fontSize:'12px'}}/>
                  <Line type="monotone" dataKey="income"  stroke="#4ade80" strokeWidth={2} dot={false}/>
                  <Line type="monotone" dataKey="expense" stroke="#e8622a" strokeWidth={2} dot={false}/>
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
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="font-bold text-white text-sm">Statement</h3>
            <p className="text-xs text-white/30 mt-0.5">{mLabel(month)}</p>
          </div>
          <Dialog open={open} onOpenChange={v=>{if(!v)resetForm();setOpen(v);}}>
            <DialogTrigger asChild>
              <button onClick={resetForm}
                className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white glow-orange-sm"
                style={{background:'oklch(0.65 0.195 34)'}}>
                <Plus className="w-3.5 h-3.5"/> Add
              </button>
            </DialogTrigger>
            <DialogContent className="rounded-2xl max-w-sm">
              <DialogHeader><DialogTitle>{editId?'Edit Transaction':'Add Transaction'}</DialogTitle></DialogHeader>
              <div className="space-y-4 pt-1">
                {/* Type toggle */}
                <div className="grid grid-cols-2 gap-2">
                  {(['expense','income'] as const).map(t=>(
                    <button key={t} onClick={()=>setForm({...form,type:t,category:''})}
                      className={`py-2.5 rounded-xl text-sm font-semibold transition-all ${form.type===t?'bg-primary text-white':'bg-white/[0.05] text-white/50 hover:text-white'}`}>
                      {t==='expense'?'↑ Expense':'↓ Income'}
                    </button>
                  ))}
                </div>

                {/* Savings warning */}
                {form.type==='expense'&&form.category==='Savings'&&(
                  <div className="flex items-start gap-2 p-3 rounded-xl bg-emerald-400/5 border border-emerald-400/15">
                    <PiggyBank className="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5"/>
                    <p className="text-xs text-emerald-400/80">This will move money from your current balance into your Savings account. Savings balance: <strong>{fmt(sb)} {currency}</strong></p>
                  </div>
                )}

                {/* Low balance warning */}
                {form.type==='expense'&&form.amount&&parseFloat(form.amount)>cb&&form.category!=='Savings'&&(
                  <div className="flex items-start gap-2 p-3 rounded-xl bg-amber-400/5 border border-amber-400/15">
                    <AlertTriangle className="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"/>
                    <p className="text-xs text-amber-400/80">
                      Insufficient current balance ({fmt(cb)} {currency}). 
                      {sb > 0 ? ` You have ${fmt(sb)} ${currency} in savings — you'll be asked to withdraw on save.` : ' No savings available.'}
                    </p>
                  </div>
                )}

                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Category</label>
                  <select className="w-full rounded-xl border border-border bg-card text-foreground px-4 py-2.5 text-sm"
                    value={form.category} onChange={e=>setForm({...form,category:e.target.value})}>
                    <option value="">Select category</option>
                    {(form.type==='income'?INCOME_CATEGORIES:EXPENSE_CATEGORIES).map(c=><option key={c} value={c}>{CAT_ICONS[c]||''} {c}</option>)}
                  </select>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Amount ({currency})</label>
                  <Input type="number" placeholder="0.00" step="0.01" value={form.amount} onChange={e=>setForm({...form,amount:e.target.value})} className="rounded-xl"/>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Date</label>
                  <Input type="date" value={form.date} onChange={e=>setForm({...form,date:e.target.value})} className="rounded-xl"/>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-white/70">Notes</label>
                  <Input placeholder="Optional…" value={form.description} onChange={e=>setForm({...form,description:e.target.value})} className="rounded-xl"/>
                </div>
                <Button onClick={handleSave} className="w-full rounded-xl" disabled={saving}>
                  {saving?<><Loader2 className="w-4 h-4 mr-2 animate-spin"/>Saving…</>:editId?'Save Changes':'Add Transaction'}
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        {/* List */}
        {expenses.length===0?(
          <div className="py-16 text-center">
            <p className="text-4xl mb-3">💸</p>
            <p className="text-white/40 text-sm font-medium">No transactions yet</p>
            <p className="text-white/20 text-xs mt-1">Tap Add to log your first entry</p>
          </div>
        ):(
          <div className="space-y-1.5">
            {sorted.map(t=>{
              const isSavings = t.category==='Savings';
              return(
                <div key={t.id}
                  className="flex items-center gap-3 px-4 py-3 rounded-2xl group transition-all hover:bg-white/[0.04]"
                  style={{border:'1px solid transparent'}}
                  onMouseEnter={e=>(e.currentTarget.style.borderColor='oklch(1 0 0 / 0.06)')}
                  onMouseLeave={e=>(e.currentTarget.style.borderColor='transparent')}
                >
                  {/* Icon */}
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-lg flex-shrink-0 ${
                    isSavings?'bg-emerald-400/10':t.type==='income'?'bg-emerald-400/10':'bg-primary/10'
                  }`}>{CAT_ICONS[t.category]||'📌'}</div>

                  {/* Info */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-semibold text-white text-sm truncate">{t.category}</p>
                      <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-bold leading-none ${
                        t.isDebtTransaction?'bg-violet-500/15 text-violet-400':
                        isSavings?'bg-emerald-500/15 text-emerald-400/80':
                        t.type==='income'?'bg-emerald-500/15 text-emerald-400':
                        'bg-primary/15 text-primary'
                      }`}>
                        {t.isDebtTransaction?(t.type==='expense'?'LENT':'REPAID'):isSavings?'SAVED':t.type==='income'?'IN':'OUT'}
                      </span>
                    </div>
                    <p className="text-xs text-white/30 mt-0.5">
                      {new Date(t.date).toLocaleDateString('default',{month:'short',day:'numeric'})}
                      {t.description&&!t.description.startsWith('[')?` · ${t.description}`:''}
                    </p>
                  </div>

                  {/* Amount */}
                  <div className="flex items-center gap-2 flex-shrink-0">
                    <p className={`font-bold text-base tabular-nums ${
                      isSavings?'text-emerald-400/80':t.type==='income'?'text-emerald-400':'text-red-400'
                    }`}>
                      {t.type==='income'?'+':'-'}{fmt(t.amount)}
                    </p>
                    <div className="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                      {!t.isDebtTransaction&&(
                        <button onClick={()=>openEdit(t)}
                          className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/[0.08] text-white/30 hover:text-white transition-all">
                          <Edit2 className="w-3 h-3"/>
                        </button>
                      )}
                      <button onClick={()=>handleDelete(t.id)}
                        className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-500/10 text-white/30 hover:text-red-400 transition-all">
                        <Trash2 className="w-3 h-3"/>
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
      </div>

      {/* ── Withdraw from Savings Modal ──────────────────── */}
      {withdrawOpen && (
        <>
          <div className="fixed inset-0 bg-black/70 z-40" onClick={() => setWithdrawOpen(false)}/>
          <div className="fixed inset-x-4 bottom-0 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-[360px] z-50 rounded-t-3xl md:rounded-3xl p-6"
            style={{background:'oklch(0.13 0.006 260)',border:'1px solid oklch(1 0 0 / 0.10)',boxShadow:'0 24px 80px oklch(0 0 0 / 0.70)'}}>
            <div className="flex items-center justify-between mb-5">
              <div>
                <p className="font-bold text-white">Withdraw from Savings</p>
                <p className="text-xs text-white/35 mt-0.5">Available: <span className="text-emerald-400 font-bold">{fmt(sb)} {currency}</span></p>
              </div>
              <button onClick={() => setWithdrawOpen(false)} className="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-white/[0.08] text-white/40 transition-all">✕</button>
            </div>
            <div className="space-y-4">
              <div>
                <label className="text-xs font-medium text-white/50 block mb-1.5">Amount ({currency})</label>
                <Input type="number" placeholder="0" step="0.01" value={withdrawAmt}
                  onChange={e => setWithdrawAmt(e.target.value)}
                  onKeyDown={e => e.key==='Enter' && handleWithdraw()}
                  className="rounded-xl text-lg font-bold"/>
              </div>
              {withdrawAmt && parseFloat(withdrawAmt) > sb && (
                <div className="flex items-center gap-2 p-2.5 rounded-xl bg-red-400/5 border border-red-400/15">
                  <AlertTriangle className="w-3.5 h-3.5 text-red-400 flex-shrink-0"/>
                  <p className="text-xs text-red-400/80">Exceeds savings balance of {fmt(sb)} {currency}</p>
                </div>
              )}
              <div className="grid grid-cols-3 gap-2">
                {[25, 50, 75].map(pct => {
                  const suggestedAmt = Math.round(sb * (pct/100));
                  return (
                    <button key={pct} onClick={() => setWithdrawAmt(suggestedAmt.toString())}
                      className="py-2 rounded-xl text-xs font-semibold text-white/50 hover:text-white transition-all"
                      style={{background:'rgba(255,255,255,0.05)',border:'1px solid rgba(255,255,255,0.07)'}}>
                      {pct}% · {fmt(suggestedAmt)}
                    </button>
                  );
                })}
              </div>
              <Button onClick={handleWithdraw} disabled={!withdrawAmt || parseFloat(withdrawAmt) <= 0 || parseFloat(withdrawAmt) > sb || withdrawing} className="w-full rounded-xl">
                {withdrawing ? <><Loader2 className="w-4 h-4 mr-2 animate-spin"/>Moving funds…</> : `Move to Current Account`}
              </Button>
              <p className="text-center text-[11px] text-white/25">This will be recorded as a withdrawal in your statement</p>
            </div>
          </div>
        </>
      )}

      {/* ── Opening Balance Setup Modal ──────────────────── */}
      {setupOpen && (
        <>
          <div className="fixed inset-0 bg-black/70 z-40" onClick={() => setSetupOpen(false)}/>
          <div className="fixed inset-x-4 bottom-0 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-[400px] z-50 rounded-t-3xl md:rounded-3xl p-6"
            style={{background:'oklch(0.13 0.006 260)',border:'1px solid oklch(1 0 0 / 0.10)',boxShadow:'0 24px 80px oklch(0 0 0 / 0.70)'}}>
            <div className="flex items-center justify-between mb-2">
              <p className="font-bold text-white">Set Opening Balance</p>
              <button onClick={() => setSetupOpen(false)} className="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-white/[0.08] text-white/40 transition-all">✕</button>
            </div>
            <p className="text-xs text-white/40 mb-5">Enter how much money you currently have. This sets your starting point so FinWise shows accurate balances.</p>
            <div className="space-y-4">
              {/* Current account */}
              <div className="rounded-2xl p-4 space-y-3"
                style={{background:'oklch(0.65 0.195 34 / 0.08)',border:'1px solid oklch(0.65 0.195 34 / 0.18)'}}>
                <div className="flex items-center gap-2">
                  <Wallet className="w-4 h-4 text-primary/80"/>
                  <p className="text-sm font-semibold text-white">Current Account</p>
                </div>
                <Input type="number" placeholder={`Amount in ${currency}`} step="0.01" value={setupCurrent}
                  onChange={e => setSetupCurrent(e.target.value)} className="rounded-xl"/>
                <p className="text-[11px] text-white/30">Cash, bank account, wallet — money you spend from day to day</p>
              </div>
              {/* Savings account */}
              <div className="rounded-2xl p-4 space-y-3"
                style={{background:'oklch(0.72 0.18 150 / 0.08)',border:'1px solid oklch(0.72 0.18 150 / 0.18)'}}>
                <div className="flex items-center gap-2">
                  <PiggyBank className="w-4 h-4 text-emerald-400/80"/>
                  <p className="text-sm font-semibold text-white">Savings Account</p>
                </div>
                <Input type="number" placeholder={`Amount in ${currency}`} step="0.01" value={setupSavings}
                  onChange={e => setSetupSavings(e.target.value)} className="rounded-xl"/>
                <p className="text-[11px] text-white/30">Money set aside for savings — kept separate from daily spending</p>
              </div>
              <Button onClick={handleSetupBalance}
                disabled={(!setupCurrent || parseFloat(setupCurrent) <= 0) && (!setupSavings || parseFloat(setupSavings) <= 0) || settingUp}
                className="w-full rounded-xl">
                {settingUp ? <><Loader2 className="w-4 h-4 mr-2 animate-spin"/>Setting up…</> : 'Confirm Opening Balances'}
              </Button>
              <p className="text-center text-[11px] text-white/20">You can update this later by adding an "Opening Balance" transaction</p>
            </div>
          </div>
        </>
      )}

    </div>
  );
}
