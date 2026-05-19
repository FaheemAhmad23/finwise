'use client';

import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Trash2, Plus, X, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { getAllData, saveData, deleteData, initDB } from '@/lib/storage';

interface DebtTransaction { id: string; type: 'lent'|'repaid'; amount: number; note: string|null; date: string; }
interface Person { id: string; name: string; balance: number; transactions: DebtTransaction[]; }

const currency = 'PKR';

export default function DebtManager({ onUpdate }: { onUpdate: () => void }) {
  const [people, setPeople] = useState<Person[]>([]);
  const [loading, setLoading] = useState(true);
  const [newName, setNewName] = useState('');
  const [addingPerson, setAddingPerson] = useState(false);
  const [selectedPerson, setSelectedPerson] = useState<Person|null>(null);
  const [txType, setTxType] = useState<'lent'|'repaid'>('lent');
  const [txAmount, setTxAmount] = useState('');
  const [txNote, setTxNote] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      await initDB();
      const data = await getAllData<Person>('people');
      setPeople(data);
    } catch (e) {
      console.error('Failed to load:', e);
      toast.error('Failed to load');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const addPerson = async () => {
    if (!newName.trim()) return;
    setAddingPerson(true);
    try {
      const person: Person = {
        id: `person_${Date.now()}`,
        name: newName.trim(),
        balance: 0,
        transactions: []
      };
      await saveData('people', person);
      setPeople([...people, person]);
      setNewName('');
      toast.success('Person added');
      onUpdate();
    } catch (e) {
      console.error('Failed to add:', e);
      toast.error('Failed to add person');
    } finally {
      setAddingPerson(false);
    }
  };

  const addTransaction = async () => {
    if (!selectedPerson || !txAmount) return;
    setSubmitting(true);
    try {
      const amount = parseFloat(txAmount);
      const tx: DebtTransaction = {
        id: `tx_${Date.now()}`,
        type: txType,
        amount,
        note: txNote || null,
        date: new Date().toISOString().split('T')[0]
      };

      const updated: Person = {
        ...selectedPerson,
        balance: txType === 'lent' ? selectedPerson.balance + amount : selectedPerson.balance - amount,
        transactions: [...selectedPerson.transactions, tx]
      };

      await saveData('people', updated);
      setPeople(people.map(p => p.id === updated.id ? updated : p));
      setSelectedPerson(updated);
      setTxAmount('');
      setTxNote('');
      toast.success('Transaction added');
      onUpdate();
    } catch (e) {
      console.error('Failed:', e);
      toast.error('Failed to add transaction');
    } finally {
      setSubmitting(false);
    }
  };

  const deletePerson = async (id: string) => {
    try {
      await deleteData('people', id);
      setPeople(people.filter(p => p.id !== id));
      if (selectedPerson?.id === id) setSelectedPerson(null);
      toast.success('Deleted');
      onUpdate();
    } catch (e) {
      console.error('Failed:', e);
      toast.error('Failed to delete');
    }
  };

  const fmt = (n: number) => n.toLocaleString('en-US', { maximumFractionDigits: 0 });

  if (loading) return <div className="animate-pulse space-y-2">{[1,2,3].map(i => <div key={i} className="h-10 bg-muted rounded" />)}</div>;

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <label className="text-sm font-medium text-white">Add Person</label>
        <div className="flex gap-2">
          <Input
            placeholder="Name"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && addPerson()}
          />
          <Button onClick={addPerson} disabled={addingPerson || !newName.trim()}>
            <Plus className="w-4 h-4" />
          </Button>
        </div>
      </div>

      <div className="space-y-2">
        <h3 className="text-sm font-bold text-white">People</h3>
        <div className="space-y-1">
          {people.length === 0 ? (
            <p className="text-white/30 text-sm">No people yet</p>
          ) : (
            people.map(p => (
              <button
                key={p.id}
                onClick={() => setSelectedPerson(p)}
                className={`w-full flex items-center justify-between p-3 rounded-lg transition-colors ${
                  selectedPerson?.id === p.id
                    ? 'bg-primary/90 text-white'
                    : 'bg-white/5 hover:bg-white/10 text-white'
                }`}
              >
                <div className="text-left">
                  <p className="font-medium">{p.name}</p>
                  <p className={`text-xs ${p.balance >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                    {p.balance >= 0 ? 'Lent' : 'Owes'} {fmt(Math.abs(p.balance))} {currency}
                  </p>
                </div>
                <button onClick={(e) => { e.stopPropagation(); deletePerson(p.id); }} className="p-1">
                  <Trash2 className="w-4 h-4" />
                </button>
              </button>
            ))
          )}
        </div>
      </div>

      {selectedPerson && (
        <div className="space-y-3 p-4 bg-white/5 rounded-lg">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-white">{selectedPerson.name}</h3>
            <button onClick={() => setSelectedPerson(null)} className="p-1"><X className="w-4 h-4" /></button>
          </div>

          <div className="space-y-2">
            <select value={txType} onChange={(e) => setTxType(e.target.value as 'lent'|'repaid')}
              className="w-full px-3 py-2 bg-muted rounded text-white">
              <option value="lent">You lent money</option>
              <option value="repaid">They repaid you</option>
            </select>
            <Input type="number" placeholder="Amount" value={txAmount} onChange={(e) => setTxAmount(e.target.value)} />
            <Input type="text" placeholder="Note (optional)" value={txNote} onChange={(e) => setTxNote(e.target.value)} />
            <Button onClick={addTransaction} disabled={submitting || !txAmount} className="w-full">
              {submitting && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
              Add
            </Button>
          </div>

          {selectedPerson.transactions.length > 0 && (
            <div className="space-y-1 border-t border-white/10 pt-3">
              {selectedPerson.transactions.map(tx => (
                <div key={tx.id} className="text-xs text-white/50">
                  <p>{tx.date} • {tx.type === 'lent' ? 'Lent' : 'Received'} {fmt(tx.amount)} {currency}</p>
                  {tx.note && <p className="text-white/30">{tx.note}</p>}
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
