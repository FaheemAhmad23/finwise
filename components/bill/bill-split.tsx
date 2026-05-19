'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Trash2, Plus } from 'lucide-react';
import { toast } from 'sonner';

interface Participant { id: string; name: string; }

const currency = 'PKR';

export default function BillSplit({ onUpdate }: { onUpdate: () => void }) {
  const [billAmount, setBillAmount] = useState('');
  const [billLabel, setBillLabel] = useState('');
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [newParticipantName, setNewParticipantName] = useState('');
  const [results, setResults] = useState<{ name: string; share: number }[]>([]);

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

  const reset = () => {
    setBillAmount('');
    setBillLabel('');
    setParticipants([]);
    setResults([]);
  };

  const fmt = (n: number) => n.toLocaleString('en-US', { maximumFractionDigits: 2 });

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <label className="text-sm font-medium text-white">Bill Description</label>
        <Input
          placeholder="e.g., Restaurant, Trip, Groceries"
          value={billLabel}
          onChange={(e) => setBillLabel(e.target.value)}
        />
      </div>

      <div className="space-y-2">
        <label className="text-sm font-medium text-white">Total Amount</label>
        <Input
          type="number"
          placeholder="Amount"
          value={billAmount}
          onChange={(e) => setBillAmount(e.target.value)}
        />
      </div>

      <div className="space-y-2">
        <label className="text-sm font-medium text-white">Add Participants</label>
        <div className="flex gap-2">
          <Input
            placeholder="Person's name"
            value={newParticipantName}
            onChange={(e) => setNewParticipantName(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && addParticipant()}
          />
          <Button onClick={addParticipant} size="sm">
            <Plus className="w-4 h-4" />
          </Button>
        </div>
      </div>

      {participants.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-sm font-bold text-white">Participants ({participants.length})</h3>
          <div className="space-y-1 p-3 bg-white/5 rounded-lg">
            {participants.map(p => (
              <div key={p.id} className="flex items-center justify-between text-sm">
                <span className="text-white">{p.name}</span>
                <button
                  onClick={() => setParticipants(participants.filter(x => x.id !== p.id))}
                  className="p-1 hover:bg-white/10 rounded transition-colors"
                >
                  <Trash2 className="w-3 h-3 text-white/50 hover:text-red-400" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {participants.length > 0 && (
        <Button onClick={calculateSplit} className="w-full">
          Calculate Split
        </Button>
      )}

      {results.length > 0 && (
        <div className="space-y-3 p-4 bg-white/5 rounded-lg">
          <div className="text-sm">
            <p className="text-white/50 mb-2">{billLabel ? `${billLabel} • ` : ''}Total: {fmt(parseFloat(billAmount))} {currency}</p>
            <h3 className="font-bold text-white mb-2">Each person pays:</h3>
            <div className="space-y-2">
              {results.map((result, idx) => (
                <div key={idx} className="flex items-center justify-between">
                  <span className="text-white">{result.name}</span>
                  <span className="font-bold text-emerald-400">{fmt(result.share)} {currency}</span>
                </div>
              ))}
            </div>
          </div>
          <Button onClick={reset} variant="outline" className="w-full mt-2">
            Reset
          </Button>
        </div>
      )}

      {participants.length === 0 && results.length === 0 && (
        <div className="text-center py-8">
          <p className="text-white/30 text-sm">Add participants to split a bill</p>
        </div>
      )}
    </div>
  );
}
