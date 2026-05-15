'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSession } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Trash2, Plus, X, Loader2, TrendingUp, TrendingDown, Users, Wallet, PiggyBank, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';

interface BalanceSummary { currentBalance: number; savingsBalance: number; }

interface DebtTransaction { id: string; type: 'lent'|'repaid'; amount: number; note: string|null; date: string; }
interface Person { id: string; name: string; balance: number; transactions: DebtTransaction[]; }

export default function DebtManager({ onUpdate }: { onUpdate: () => void }) {
  const { data: session } = useSession();
  const currency = (session?.user as any)?.currency || 'PKR';

  const [people, setPeople]               = useState<Person[]>([]);
  const [loading, setLoading]             = useState(true);
  const [newName, setNewName]             = useState('');
  const [addingPerson, setAddingPerson]   = useState(false);
  const [selectedPerson, setSelectedPerson] = useState<Person|null>(null);
  const [txType, setTxType]               = useState<'lent'|'repaid'>('lent');
  const [txAmount, setTxAmount]           = useState('');
  const [txNote, setTxNote]               = useState('');
  const [fundingSource, setFundingSource] = useState<'current'|'savings'>('current');
  const [repayToSavings, setRepayToSavings] = useState(false);
  const [submitting, setSubmitting]       = useState(false);
  const [balanceData, setBalanceData]     = useState<BalanceSummary|null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try { const r = await fetch('/api/people'); setPeople(await r.json()); }
    catch { toast.error('Failed to load'); }
    finally { setLoading(false); }
  }, []);

  const loadBalance = useCallback(async () => {
    try { const r = await fetch('/api/balance-summary'); setBalanceData(await r.json()); }
    catch {}
  }, []);

  useEffect(() => { load(); loadBalance(); }, [load, loadBalance]);

  const addPerson = async () => {
    if (!newName.trim()) return;
    setAddingPerson(true);
    try {
      const r = await fetch('/api/people', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:newName.trim()})});
      const p = await r.json();
      setPeople(prev => [...prev, p].sort((a,b) => a.name.localeCompare(b.name)));
      setNewName(''); toast.success(`${p.name} added`); onUpdate();
    } catch { toast.error('Failed'); }
    finally { setAddingPerson(false); }
  };

  const addTransaction = async () => {
    if (!selectedPerson || !txAmount || parseFloat(txAmount) <= 0) return;
    const amt = parseFloat(txAmount);

    // Low balance alert for loans
    if (txType === 'lent' && balanceData) {
      const available = fundingSource === 'savings' ? balanceData.savingsBalance : balanceData.currentBalance;
      if (amt > available) {
        const accountName = fundingSource === 'savings' ? 'Savings' : 'Current Balance';
        toast.error(`Insufficient ${accountName} (${available.toLocaleString()} available). Please reduce amount or switch funding source.`);
        return;
      }
    }

    setSubmitting(true);
    try {
      const r = await fetch(`/api/people/${selectedPerson.id}`, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          type: txType, amount: amt, note: txNote||null,
          fundingSource: txType==='lent' ? fundingSource : undefined,
          repayToSavings: txType==='repaid' ? repayToSavings : undefined,
        })
      });
      const updated = await r.json();
      setPeople(prev => prev.map(p => p.id === updated.id ? updated : p));
      setSelectedPerson(updated); setTxAmount(''); setTxNote('');
      await loadBalance();
      toast.success(txType==='repaid' ? `+${amt.toLocaleString()} returned to your ${repayToSavings?'savings':'current'} account` : 'Loan recorded');
      onUpdate();
    } catch { toast.error('Failed'); }
    finally { setSubmitting(false); }
  };

  const deletePerson = async (id: string) => {
    const p = people.find(p => p.id === id);
    if (!confirm(`Delete ${p?.name}?`)) return;
    try {
      await fetch(`/api/people/${id}`,{method:'DELETE'});
      setPeople(prev => prev.filter(p => p.id !== id));
      if (selectedPerson?.id === id) setSelectedPerson(null);
      toast.success('Deleted'); onUpdate();
    } catch { toast.error('Failed'); }
  };

  const netBalance  = people.reduce((s,p) => s+p.balance, 0);
  const totalOwed   = people.filter(p => p.balance > 0).reduce((s,p) => s+p.balance, 0);
  const totalOwe    = people.filter(p => p.balance < 0).reduce((s,p) => s+Math.abs(p.balance), 0);
  const fmt         = (n: number) => Math.abs(n).toLocaleString();

  if (loading) return (
    <div className="animate-pulse space-y-4">
      <div className="grid grid-cols-3 gap-3">{[1,2,3].map(i=><div key={i} className="h-24 bg-muted rounded-2xl"/>)}</div>
      <div className="h-10 bg-muted rounded-2xl"/><div className="space-y-2">{[1,2,3].map(i=><div key={i} className="h-16 bg-muted rounded-2xl"/>)}</div>
    </div>
  );

  return (
    <div className="space-y-5">

      {/* ── Hero + Side Stats ──────────────────────────────── */}
      <div className="grid grid-cols-3 gap-3">
        {/* Net hero */}
        <div className="col-span-2 relative overflow-hidden rounded-2xl p-5"
          style={{background:'linear-gradient(135deg, oklch(0.17 0.008 265) 0%, oklch(0.13 0.006 265) 100%)',border:'1px solid oklch(1 0 0 / 0.08)',boxShadow:'0 4px 24px oklch(0 0 0 / 0.35)'}}>
          <div className="absolute top-0 right-0 w-32 h-32 rounded-full opacity-20"
            style={{background: netBalance>0?'radial-gradient(circle,#4ade80,transparent)':'radial-gradient(circle,#f87171,transparent)',filter:'blur(30px)'}}/>
          <p className="text-xs font-semibold text-white/40 uppercase tracking-widest mb-3">Net Position</p>
          <p className={`text-3xl md:text-4xl font-black leading-none mb-1 ${netBalance>0?'text-emerald-400':netBalance<0?'text-red-400':'text-white/60'}`}>
            {netBalance>0?'+':''}{fmt(netBalance)}
          </p>
          <p className="text-sm text-white/30 font-medium">{currency}</p>
          <div className="mt-4">
            <span className={`text-xs font-bold px-2.5 py-1 rounded-full ${netBalance>0?'bg-emerald-400/10 text-emerald-400':netBalance<0?'bg-red-400/10 text-red-400':'bg-white/5 text-white/40'}`}>
              {netBalance>0?'You are owed overall':netBalance<0?'You owe overall':'All balanced'}
            </span>
          </div>
        </div>

        {/* Side stats */}
        <div className="flex flex-col gap-3">
          <div className="flex-1 rounded-2xl p-4 relative overflow-hidden"
            style={{background:'oklch(0.72 0.18 150 / 0.10)',border:'1px solid oklch(0.72 0.18 150 / 0.18)'}}>
            <p className="text-[10px] font-bold text-white/35 uppercase tracking-widest mb-2">Owed to You</p>
            <p className="text-lg font-black text-emerald-400 leading-none">{fmt(totalOwed)}</p>
            <p className="text-[10px] text-white/25 mt-0.5">{currency}</p>
            <TrendingUp className="absolute bottom-3 right-3 w-5 h-5 text-emerald-400/20"/>
          </div>
          <div className="flex-1 rounded-2xl p-4 relative overflow-hidden"
            style={{background:'oklch(0.62 0.22 27 / 0.10)',border:'1px solid oklch(0.62 0.22 27 / 0.18)'}}>
            <p className="text-[10px] font-bold text-white/35 uppercase tracking-widest mb-2">You Owe</p>
            <p className="text-lg font-black text-red-400 leading-none">{fmt(totalOwe)}</p>
            <p className="text-[10px] text-white/25 mt-0.5">{currency}</p>
            <TrendingDown className="absolute bottom-3 right-3 w-5 h-5 text-red-400/20"/>
          </div>
        </div>
      </div>

      {/* ── Add Person ─────────────────────────────────────── */}
      <div className="flex gap-2">
        <Input placeholder="Add person by name…" value={newName}
          onChange={e => setNewName(e.target.value)}
          onKeyDown={e => e.key==='Enter' && addPerson()}
          className="rounded-xl flex-1"
        />
        <button onClick={addPerson} disabled={addingPerson}
          className="px-4 rounded-xl text-white font-semibold text-sm flex items-center gap-2 glow-orange-sm transition-all"
          style={{background:'oklch(0.65 0.195 34)'}}>
          {addingPerson ? <Loader2 className="w-4 h-4 animate-spin"/> : <><Plus className="w-4 h-4"/>Add</>}
        </button>
      </div>

      {/* ── People List ────────────────────────────────────── */}
      {people.length === 0 ? (
        <div className="py-16 text-center">
          <Users className="w-12 h-12 mx-auto mb-4 text-white/10"/>
          <p className="text-white/40 text-sm font-medium">No people yet</p>
          <p className="text-white/20 text-xs mt-1">Add someone to start tracking debts</p>
        </div>
      ) : (
        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-bold text-white text-sm">People <span className="text-white/30 font-normal">({people.length})</span></h3>
          </div>
          <div className="space-y-1.5">
            {people.map(person => (
              <div key={person.id}
                className="flex items-center gap-3 px-4 py-3 rounded-2xl group transition-all cursor-pointer hover:bg-white/[0.04]"
                style={{border:'1px solid transparent'}}
                onMouseEnter={e=>(e.currentTarget.style.borderColor='oklch(1 0 0 / 0.06)')}
                onMouseLeave={e=>(e.currentTarget.style.borderColor='transparent')}
                onClick={() => { setSelectedPerson(person); setTxType('lent'); setTxAmount(''); setTxNote(''); }}
              >
                {/* Avatar */}
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold flex-shrink-0 ${person.balance>0?'bg-emerald-400/10 text-emerald-400':person.balance<0?'bg-red-400/10 text-red-400':'bg-white/5 text-white/40'}`}>
                  {person.name.charAt(0).toUpperCase()}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-white text-sm">{person.name}</p>
                  <p className="text-xs text-white/30 mt-0.5">
                    {person.transactions.length} transaction{person.transactions.length !== 1 ? 's' : ''}
                    {person.balance === 0 && ' · Settled'}
                  </p>
                </div>

                {/* Balance */}
                <div className="flex items-center gap-2 flex-shrink-0">
                  <p className={`font-bold text-base tabular-nums ${person.balance>0?'text-emerald-400':person.balance<0?'text-red-400':'text-white/30'}`}>
                    {person.balance>0?'+':''}{person.balance !== 0 ? fmt(person.balance) : '0'}
                  </p>
                  <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-bold leading-none ${person.balance>0?'bg-emerald-400/10 text-emerald-400':person.balance<0?'bg-red-400/10 text-red-400':'bg-white/5 text-white/40'}`}>
                    {person.balance>0?'OWES':'OWED'}
                  </span>

                  {/* Delete on hover */}
                  <button
                    onClick={e => { e.stopPropagation(); deletePerson(person.id); }}
                    className="w-7 h-7 flex items-center justify-center rounded-lg opacity-0 group-hover:opacity-100 hover:bg-red-500/10 text-white/30 hover:text-red-400 transition-all">
                    <Trash2 className="w-3 h-3"/>
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ── Person Detail Modal ────────────────────────────── */}
      {selectedPerson && (
        <>
          <div className="fixed inset-0 bg-black/70 z-40" onClick={() => setSelectedPerson(null)}/>
          <div className="fixed inset-x-4 bottom-0 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-[420px] z-50 rounded-t-3xl md:rounded-3xl overflow-hidden max-h-[90vh] flex flex-col"
            style={{background:'oklch(0.13 0.006 260)',border:'1px solid oklch(1 0 0 / 0.10)',boxShadow:'0 24px 80px oklch(0 0 0 / 0.70)'}}>

            {/* Modal header */}
            <div className="flex items-center justify-between p-5 border-b" style={{borderColor:'oklch(1 0 0 / 0.07)'}}>
              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold ${selectedPerson.balance>0?'bg-emerald-400/10 text-emerald-400':selectedPerson.balance<0?'bg-red-400/10 text-red-400':'bg-white/5 text-white/40'}`}>
                  {selectedPerson.name.charAt(0).toUpperCase()}
                </div>
                <div>
                  <p className="font-bold text-white">{selectedPerson.name}</p>
                  <p className={`text-xs font-semibold ${selectedPerson.balance>0?'text-emerald-400':selectedPerson.balance<0?'text-red-400':'text-white/30'}`}>
                    {selectedPerson.balance>0?`Owes you ${fmt(selectedPerson.balance)}`:selectedPerson.balance<0?`You owe ${fmt(selectedPerson.balance)}`:'Settled up'} {currency}
                  </p>
                </div>
              </div>
              <button onClick={() => setSelectedPerson(null)} className="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-white/[0.08] text-white/40 hover:text-white transition-all">
                <X className="w-4 h-4"/>
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-5">
              {/* Record transaction */}
              <div className="space-y-3">
                <p className="text-xs font-bold text-white/40 uppercase tracking-widest">Record Transaction</p>
                <div className="grid grid-cols-2 gap-2">
                  {(['lent','repaid'] as const).map(t => (
                    <button key={t} onClick={() => setTxType(t)}
                      className={`py-2.5 rounded-xl text-sm font-semibold transition-all ${txType===t?'bg-primary text-white':'bg-white/[0.05] text-white/50 hover:text-white'}`}>
                      {t==='lent'?'I Lent':'They Repaid'}
                    </button>
                  ))}
                </div>

                <Input type="number" placeholder={`Amount (${currency})`} step="0.01" value={txAmount}
                  onChange={e => setTxAmount(e.target.value)} className="rounded-xl"/>

                {/* Funding Source — only when lending */}
                {txType === 'lent' && (
                  <div className="space-y-2">
                    <p className="text-xs font-medium text-white/40">Fund this loan from:</p>
                    <div className="grid grid-cols-2 gap-2">
                      <button onClick={() => setFundingSource('current')}
                        className={`flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all border ${fundingSource==='current'?'border-primary bg-primary/15 text-white':'border-white/[0.07] bg-white/[0.03] text-white/40 hover:text-white'}`}>
                        <Wallet className="w-3.5 h-3.5"/>
                        <div className="text-left">
                          <p>Current</p>
                          <p className="text-[10px] font-normal opacity-60">{balanceData ? fmt(balanceData.currentBalance) : '—'} {currency}</p>
                        </div>
                      </button>
                      <button onClick={() => setFundingSource('savings')}
                        className={`flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all border ${fundingSource==='savings'?'border-emerald-500 bg-emerald-500/10 text-emerald-400':'border-white/[0.07] bg-white/[0.03] text-white/40 hover:text-white'}`}>
                        <PiggyBank className="w-3.5 h-3.5"/>
                        <div className="text-left">
                          <p>Savings</p>
                          <p className="text-[10px] font-normal opacity-60">{balanceData ? fmt(balanceData.savingsBalance) : '—'} {currency}</p>
                        </div>
                      </button>
                    </div>

                    {/* Balance warning */}
                    {txAmount && balanceData && parseFloat(txAmount) > 0 && (() => {
                      const available = fundingSource==='savings' ? balanceData.savingsBalance : balanceData.currentBalance;
                      return parseFloat(txAmount) > available ? (
                        <div className="flex items-start gap-2 p-2.5 rounded-xl bg-amber-400/5 border border-amber-400/15">
                          <AlertTriangle className="w-3.5 h-3.5 text-amber-400 flex-shrink-0 mt-0.5"/>
                          <p className="text-[11px] text-amber-400/80">
                            Insufficient {fundingSource==='savings'?'savings':'current balance'}.
                            Available: <strong>{fmt(available)} {currency}</strong>
                          </p>
                        </div>
                      ) : null;
                    })()}
                  </div>
                )}

                {/* Repay to savings toggle — only when repaid */}
                {txType === 'repaid' && balanceData && balanceData.savingsBalance > 0 && (
                  <div className="flex items-center justify-between p-3 rounded-xl bg-white/[0.03] border border-white/[0.07]">
                    <div className="flex items-center gap-2">
                      <PiggyBank className="w-4 h-4 text-emerald-400/70"/>
                      <div>
                        <p className="text-xs font-medium text-white">Return to Savings?</p>
                        <p className="text-[10px] text-white/35">Adds repayment to savings account</p>
                      </div>
                    </div>
                    <button onClick={() => setRepayToSavings(v => !v)}
                      className={`w-10 h-5 rounded-full transition-all ${repayToSavings?'bg-emerald-500':'bg-white/10'}`}>
                      <div className={`w-4 h-4 rounded-full bg-white shadow-sm transform transition-transform ${repayToSavings?'translate-x-5':'translate-x-0.5'}`}/>
                    </button>
                  </div>
                )}

                <Input placeholder="Note (optional)" value={txNote}
                  onChange={e => setTxNote(e.target.value)} className="rounded-xl"/>
                <Button onClick={addTransaction} disabled={!txAmount||parseFloat(txAmount)<=0||submitting} className="w-full rounded-xl">
                  {submitting?<><Loader2 className="w-4 h-4 mr-2 animate-spin"/>Recording…</>:txType==='lent'?`Lend from ${fundingSource==='savings'?'Savings':'Current'}`:'Record Repayment'}
                </Button>
              </div>


              {/* History */}
              {selectedPerson.transactions.length > 0 && (
                <div className="space-y-2">
                  <p className="text-xs font-bold text-white/40 uppercase tracking-widest">History</p>
                  {[...selectedPerson.transactions].reverse().map(t => (
                    <div key={t.id} className="flex items-center gap-3 px-3 py-3 rounded-xl" style={{background:'oklch(1 0 0 / 0.04)'}}>
                      <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${t.type==='lent'?'bg-red-400/10':'bg-emerald-400/10'}`}>
                        {t.type==='lent'?<TrendingUp className="w-3.5 h-3.5 text-red-400"/>:<TrendingDown className="w-3.5 h-3.5 text-emerald-400"/>}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-white">{t.type==='lent'?'You lent':'They repaid'}</p>
                        {t.note && <p className="text-xs text-white/35 truncate">{t.note}</p>}
                      </div>
                      <div className="text-right flex-shrink-0">
                        <p className={`text-sm font-bold ${t.type==='lent'?'text-red-400':'text-emerald-400'}`}>
                          {t.type==='lent'?'-':'+'}{fmt(t.amount)}
                        </p>
                        <p className="text-[10px] text-white/25">
                          {new Date(t.date).toLocaleDateString('default',{month:'short',day:'numeric'})}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
