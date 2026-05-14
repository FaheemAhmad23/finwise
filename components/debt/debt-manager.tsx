'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSession } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Trash2, Plus, X, ChevronLeft, Loader2 } from 'lucide-react';
import { toast } from 'sonner';

interface DebtTransaction {
  id: string;
  type: 'lent' | 'repaid';
  amount: number;
  note: string | null;
  date: string;
}

interface Person {
  id: string;
  name: string;
  balance: number;
  transactions: DebtTransaction[];
}

export default function DebtManager({ onUpdate }: { onUpdate: () => void }) {
  const { data: session } = useSession();
  const currency = (session?.user as any)?.currency || 'PKR';

  const [people, setPeople]               = useState<Person[]>([]);
  const [loading, setLoading]             = useState(true);
  const [searchTerm, setSearchTerm]       = useState('');
  const [newPersonName, setNewPersonName] = useState('');
  const [addingPerson, setAddingPerson]   = useState(false);
  const [selectedPerson, setSelectedPerson] = useState<Person | null>(null);
  const [transactionType, setTransactionType] = useState<'lent' | 'repaid'>('lent');
  const [transactionAmount, setTransactionAmount] = useState('');
  const [transactionNote, setTransactionNote]     = useState('');
  const [submitting, setSubmitting]       = useState(false);

  const loadPeople = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/people');
      if (!res.ok) throw new Error();
      setPeople(await res.json());
    } catch {
      toast.error('Failed to load people');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadPeople(); }, [loadPeople]);

  const addPerson = async () => {
    if (!newPersonName.trim()) { toast.error('Enter a name'); return; }
    setAddingPerson(true);
    try {
      const res = await fetch('/api/people', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: newPersonName.trim() }),
      });
      if (!res.ok) throw new Error();
      const p = await res.json();
      setPeople(prev => [...prev, p].sort((a, b) => a.name.localeCompare(b.name)));
      setNewPersonName('');
      toast.success(`${p.name} added`);
      onUpdate();
    } catch { toast.error('Failed to add person'); }
    finally { setAddingPerson(false); }
  };

  const addTransaction = async () => {
    if (!selectedPerson || !transactionAmount || parseFloat(transactionAmount) <= 0) return;
    setSubmitting(true);
    try {
      const res = await fetch(`/api/people/${selectedPerson.id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: transactionType, amount: parseFloat(transactionAmount), note: transactionNote || null }),
      });
      if (!res.ok) throw new Error();
      const updated = await res.json();
      setPeople(prev => prev.map(p => p.id === updated.id ? updated : p));
      setSelectedPerson(updated);
      setTransactionAmount('');
      setTransactionNote('');
      toast.success(`Recorded for ${selectedPerson.name}`);
      onUpdate();
    } catch { toast.error('Failed to record transaction'); }
    finally { setSubmitting(false); }
  };

  const deletePerson = async (id: string) => {
    const person = people.find(p => p.id === id);
    if (!confirm(`Delete ${person?.name} and all their transactions?`)) return;
    try {
      await fetch(`/api/people/${id}`, { method: 'DELETE' });
      setPeople(prev => prev.filter(p => p.id !== id));
      if (selectedPerson?.id === id) setSelectedPerson(null);
      toast.success('Person deleted');
      onUpdate();
    } catch { toast.error('Failed to delete'); }
  };

  const filteredPeople = people.filter(p => p.name.toLowerCase().includes(searchTerm.toLowerCase()));
  const netBalance = people.reduce((s, p) => s + p.balance, 0);

  if (loading) return (
    <div className="space-y-4 animate-pulse">
      <div className="h-24 bg-slate-100 rounded-2xl" />
      <div className="h-12 bg-slate-100 rounded-2xl" />
      <div className="grid grid-cols-2 gap-3">
        {[1,2,3,4].map(i => <div key={i} className="h-28 bg-slate-100 rounded-2xl" />)}
      </div>
    </div>
  );

  return (
    <div className="space-y-4 md:space-y-6">
      {/* Net Balance */}
      <div className={`rounded-2xl p-4 md:p-6 border ${netBalance > 0 ? 'bg-green-50 border-green-200/60' : netBalance < 0 ? 'bg-red-50 border-red-200/60' : 'bg-slate-50 border-slate-200/60'}`}>
        <p className="text-xs md:text-sm text-muted-foreground font-medium">Net Balance</p>
        <p className={`text-2xl md:text-3xl font-semibold mt-1 ${netBalance > 0 ? 'text-green-600' : netBalance < 0 ? 'text-red-600' : 'text-slate-900'}`}>
          {netBalance > 0 ? '+' : ''}{netBalance.toFixed(2)} {currency}
        </p>
        <p className="text-xs text-muted-foreground mt-2">{netBalance > 0 ? 'People owe you' : netBalance < 0 ? 'You owe people' : 'All balanced'}</p>
      </div>

      {/* Add Person */}
      <div className="rounded-2xl border border-slate-200/60 bg-white p-4">
        <div className="flex gap-2">
          <Input placeholder="Add person by name…" value={newPersonName} onChange={e => setNewPersonName(e.target.value)} onKeyDown={e => e.key === 'Enter' && addPerson()} className="rounded-lg" />
          <Button onClick={addPerson} className="rounded-lg px-3" size="sm" disabled={addingPerson}>
            {addingPerson ? <Loader2 className="w-4 h-4 animate-spin" /> : <Plus className="w-4 h-4" />}
          </Button>
        </div>
      </div>

      {people.length > 3 && (
        <Input placeholder="Search person…" value={searchTerm} onChange={e => setSearchTerm(e.target.value)} className="rounded-lg" />
      )}

      {/* People Grid */}
      <div className="rounded-2xl border border-slate-200/60 bg-white overflow-hidden">
        {filteredPeople.length === 0 ? (
          <div className="text-center py-12 px-4 text-muted-foreground">
            <p className="text-2xl mb-2">👥</p>
            <p className="text-sm">{searchTerm ? 'No person found' : 'Add someone to track debts with'}</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 divide-x divide-y divide-slate-200">
            {filteredPeople.map(person => (
              <div key={person.id} className="relative group">
                <button
                  onClick={() => { setSelectedPerson(person); setTransactionType('lent'); setTransactionAmount(''); setTransactionNote(''); }}
                  className={`w-full p-3 md:p-4 text-left transition-colors hover:bg-slate-50 ${person.balance > 0 ? 'hover:bg-green-50/80' : person.balance < 0 ? 'hover:bg-red-50/80' : ''}`}
                >
                  <p className="font-semibold text-sm md:text-base truncate text-slate-900">{person.name}</p>
                  <p className={`text-lg md:text-2xl font-bold mt-2 ${person.balance > 0 ? 'text-green-600' : person.balance < 0 ? 'text-red-600' : 'text-slate-600'}`}>
                    {person.balance > 0 ? '+' : ''}{Math.abs(person.balance).toFixed(0)}
                  </p>
                  <p className="text-xs md:text-sm text-muted-foreground mt-1">{person.balance > 0 ? 'Owes you' : person.balance < 0 ? 'You owe' : 'Settled'}</p>
                </button>
                <button onClick={e => { e.stopPropagation(); deletePerson(person.id); }} className="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow-md">
                  <Trash2 className="w-3.5 h-3.5 text-red-600" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Person Modal */}
      {selectedPerson && (
        <>
          <div className="fixed inset-0 bg-black/50 z-40 md:hidden" onClick={() => setSelectedPerson(null)} />
          <div className="md:hidden fixed inset-0 z-50 bg-white overflow-y-auto flex flex-col">
            <div className="sticky top-0 bg-white p-4 border-b flex items-center justify-between shadow-sm">
              <button onClick={() => setSelectedPerson(null)}><ChevronLeft className="w-6 h-6" /></button>
              <h3 className="text-lg font-bold">{selectedPerson.name}</h3>
              <div className="w-6" />
            </div>
            <PersonDetail person={selectedPerson} currency={currency} transactionType={transactionType} setTransactionType={setTransactionType} transactionAmount={transactionAmount} setTransactionAmount={setTransactionAmount} transactionNote={transactionNote} setTransactionNote={setTransactionNote} onAdd={addTransaction} submitting={submitting} />
          </div>

          <div className="hidden md:flex fixed inset-0 bg-black/50 z-50 items-center justify-center p-4" onClick={() => setSelectedPerson(null)}>
            <div className="bg-white rounded-3xl w-full max-w-md max-h-[90vh] flex flex-col shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
              <div className="p-6 border-b flex items-center justify-between">
                <h3 className="text-2xl font-bold">{selectedPerson.name}</h3>
                <Button variant="ghost" size="sm" onClick={() => setSelectedPerson(null)} className="h-8 w-8 p-0"><X className="w-5 h-5" /></Button>
              </div>
              <div className="flex-1 overflow-y-auto">
                <PersonDetail person={selectedPerson} currency={currency} transactionType={transactionType} setTransactionType={setTransactionType} transactionAmount={transactionAmount} setTransactionAmount={setTransactionAmount} transactionNote={transactionNote} setTransactionNote={setTransactionNote} onAdd={addTransaction} submitting={submitting} />
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

function PersonDetail({ person, currency, transactionType, setTransactionType, transactionAmount, setTransactionAmount, transactionNote, setTransactionNote, onAdd, submitting }: {
  person: Person; currency: string;
  transactionType: 'lent' | 'repaid'; setTransactionType: (v: 'lent' | 'repaid') => void;
  transactionAmount: string; setTransactionAmount: (v: string) => void;
  transactionNote: string; setTransactionNote: (v: string) => void;
  onAdd: () => void; submitting: boolean;
}) {
  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className={`rounded-2xl p-4 border ${person.balance > 0 ? 'bg-green-50 border-green-200/60' : person.balance < 0 ? 'bg-red-50 border-red-200/60' : 'bg-slate-50 border-slate-200/60'}`}>
        <p className="text-xs text-muted-foreground font-medium">Current Balance</p>
        <p className={`text-3xl font-bold mt-2 ${person.balance > 0 ? 'text-green-600' : person.balance < 0 ? 'text-red-600' : 'text-slate-900'}`}>
          {person.balance > 0 ? '+' : ''}{person.balance.toFixed(2)} {currency}
        </p>
        <p className="text-xs mt-2 text-muted-foreground">{person.balance > 0 ? 'They owe you' : person.balance < 0 ? 'You owe them' : 'Settled up'}</p>
      </div>

      <div className="space-y-4">
        <h4 className="font-semibold text-slate-900">Add Transaction</h4>
        <div>
          <label className="text-sm font-medium text-slate-700 block mb-2">Type</label>
          <select className="w-full px-3 py-3 rounded-lg border border-slate-200 bg-white text-sm" value={transactionType} onChange={e => setTransactionType(e.target.value as 'lent' | 'repaid')}>
            <option value="lent">I Lent Them</option>
            <option value="repaid">They Repaid Me</option>
          </select>
        </div>
        <div>
          <label className="text-sm font-medium text-slate-700 block mb-2">Amount ({currency})</label>
          <Input type="number" placeholder="0.00" step="0.01" className="rounded-lg" value={transactionAmount} onChange={e => setTransactionAmount(e.target.value)} />
        </div>
        <div>
          <label className="text-sm font-medium text-slate-700 block mb-2">Note (Optional)</label>
          <Input placeholder="e.g., Lunch, Movie" className="rounded-lg" value={transactionNote} onChange={e => setTransactionNote(e.target.value)} />
        </div>
        <Button className="w-full rounded-lg py-6 text-base" onClick={onAdd} disabled={!transactionAmount || parseFloat(transactionAmount) <= 0 || submitting}>
          {submitting ? <><Loader2 className="w-4 h-4 mr-2 animate-spin" />Recording…</> : 'Add Transaction'}
        </Button>
      </div>

      {person.transactions.length > 0 && (
        <div className="border-t pt-6">
          <h4 className="font-semibold text-slate-900 text-lg mb-4">History</h4>
          <div className="space-y-3">
            {person.transactions.map(t => (
              <div key={t.id} className="p-4 bg-slate-50 rounded-xl">
                <p className={`font-semibold text-base ${t.type === 'lent' ? 'text-red-600' : 'text-green-600'}`}>
                  {t.type === 'lent' ? 'You Lent' : 'They Repaid'} {t.amount.toFixed(0)} {currency}
                </p>
                {t.note && <p className="text-sm text-muted-foreground mt-1">{t.note}</p>}
                <p className="text-xs text-muted-foreground mt-2">{new Date(t.date).toLocaleDateString('default', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
