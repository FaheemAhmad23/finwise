'use client';

import { useState } from 'react';
import { useSession } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Trash2, Plus } from 'lucide-react';
import { toast } from 'sonner';

interface Participant { id: string; name: string; }
interface BillSplitProps { onUpdate: () => void; }

export default function BillSplit({ onUpdate }: BillSplitProps) {
  const { data: session } = useSession();
  const currency = (session?.user as any)?.currency || 'PKR';

  const [billAmount, setBillAmount]               = useState('');
  const [billLabel, setBillLabel]                 = useState('');
  const [participants, setParticipants]           = useState<Participant[]>([]);
  const [newParticipantName, setNewParticipantName] = useState('');
  const [results, setResults]                     = useState<{ name: string; share: number }[]>([]);
  const [saving, setSaving]                       = useState(false);

  const addParticipant = () => {
    if (!newParticipantName.trim()) { toast.error('Enter a name'); return; }
    if (participants.some(p => p.name.toLowerCase() === newParticipantName.toLowerCase())) {
      toast.error('Already added'); return;
    }
    setParticipants(prev => [...prev, { id: Date.now().toString(), name: newParticipantName.trim() }]);
    setNewParticipantName('');
  };

  const calculateSplit = () => {
    if (!billAmount || parseFloat(billAmount) <= 0) { toast.error('Enter a valid bill amount'); return; }
    if (participants.length === 0) { toast.error('Add at least one participant'); return; }
    const share = parseFloat(billAmount) / participants.length;
    setResults(participants.map(p => ({ name: p.name, share })));
    toast.success('Split calculated');
  };

  const addToDebts = async () => {
    if (results.length === 0) return;
    setSaving(true);
    try {
      const peopleRes = await fetch('/api/people');
      const people: any[] = await peopleRes.json();

      for (const result of results) {
        const existing = people.find((p: any) => p.name.toLowerCase() === result.name.toLowerCase());
        if (existing) {
          await fetch(`/api/people/${existing.id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              type: 'lent',
              amount: result.share,
              note: `Bill split${billLabel ? ` — ${billLabel}` : ''} (${billAmount} ${currency} total)`,
            }),
          });
        } else {
          const newPersonRes = await fetch('/api/people', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: result.name }),
          });
          const newPerson = await newPersonRes.json();
          await fetch(`/api/people/${newPerson.id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              type: 'lent',
              amount: result.share,
              note: `Bill split${billLabel ? ` — ${billLabel}` : ''} (${billAmount} ${currency} total)`,
            }),
          });
        }
      }

      toast.success('All splits added to People Debts');
      setBillAmount('');
      setBillLabel('');
      setParticipants([]);
      setResults([]);
      onUpdate();
    } catch {
      toast.error('Failed to add splits to People Debts');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Bill Info */}
      <div className="rounded-2xl border border-border bg-card p-6 space-y-4">
        <h3 className="font-semibold text-foreground">Bill Split Calculator</h3>
        <p className="text-sm text-muted-foreground">Split a bill equally and add shares to People Debts automatically.</p>

        <div className="space-y-2">
          <label className="text-sm font-medium">Bill Label (optional)</label>
          <Input placeholder="e.g., Dinner at Cafe, Trip to Lahore" value={billLabel} onChange={e => setBillLabel(e.target.value)} className="rounded-xl" />
        </div>

        <div className="space-y-2">
          <label className="text-sm font-medium">Total Bill Amount ({currency})</label>
          <Input id="billAmount" type="number" placeholder="0.00" step="0.01" value={billAmount} onChange={e => setBillAmount(e.target.value)} className="rounded-xl" />
        </div>
      </div>

      {/* Participants */}
      <div className="rounded-2xl border border-border bg-card p-6 space-y-4">
        <h3 className="font-semibold text-foreground">Participants</h3>
        <div className="flex gap-2">
          <Input
            placeholder="Add participant name…"
            value={newParticipantName}
            onChange={e => setNewParticipantName(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && addParticipant()}
            className="rounded-xl"
          />
          <Button onClick={addParticipant} size="sm" className="rounded-xl">
            <Plus className="w-4 h-4 mr-1" /> Add
          </Button>
        </div>

        {participants.length === 0 ? (
          <p className="text-center py-4 text-sm text-muted-foreground">Add people who are splitting this bill</p>
        ) : (
          <div className="space-y-2">
            {participants.map(p => (
              <div key={p.id} className="flex items-center justify-between p-3 border border-border rounded-xl bg-muted/40">
                <span className="font-medium text-sm text-foreground">{p.name}</span>
                <Button variant="ghost" size="sm" onClick={() => setParticipants(prev => prev.filter(x => x.id !== p.id))}>
                  <Trash2 className="w-4 h-4 text-red-400" />
                </Button>
              </div>
            ))}
            <p className="text-xs text-muted-foreground text-center pt-1">
              {participants.length} people · {billAmount ? `${(parseFloat(billAmount) / participants.length).toFixed(2)} ${currency} each` : 'Enter amount above'}
            </p>
          </div>
        )}

        <Button onClick={calculateSplit} className="w-full rounded-xl" disabled={participants.length === 0 || !billAmount}>
          Calculate Split
        </Button>
      </div>

      {/* Results */}
      {results.length > 0 && (
        <div className="rounded-2xl border border-green-500/30 bg-green-500/10 p-6 space-y-4">
          <div>
            <h3 className="font-semibold text-green-400">Split Results</h3>
            <p className="text-sm text-green-400/70 mt-1">
              Each person pays: <strong>{(parseFloat(billAmount) / results.length).toFixed(2)} {currency}</strong>
            </p>
          </div>

          <div className="space-y-2">
            {results.map((r, i) => (
              <div key={i} className="flex items-center justify-between p-3 bg-card rounded-xl border border-green-500/20">
                <span className="font-medium text-foreground">{r.name}</span>
                <span className="text-lg font-bold text-green-400">{r.share.toFixed(2)} {currency}</span>
              </div>
            ))}
          </div>

          <div className="flex justify-between font-bold text-lg border-t border-green-500/20 pt-3 text-foreground">
            <span>Total:</span>
            <span className="text-green-400">{parseFloat(billAmount).toFixed(2)} {currency}</span>
          </div>

          <Button onClick={addToDebts} disabled={saving} className="w-full rounded-xl bg-green-600 hover:bg-green-700 text-white">
            {saving ? 'Adding to Debts…' : '➕ Add All to People Debts'}
          </Button>

          <Button
            variant="outline"
            onClick={() => { setBillAmount(''); setBillLabel(''); setParticipants([]); setResults([]); }}
            className="w-full rounded-xl"
          >
            Reset
          </Button>
        </div>
      )}
    </div>
  );
}
