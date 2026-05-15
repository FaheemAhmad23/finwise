'use client';

import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Trash2, Plus, Edit2, Search, Building2, Mail, Phone, MapPin, Loader2, Users } from 'lucide-react';
import { toast } from 'sonner';

interface Client {
  id: string;
  name: string;
  email: string | null;
  whatsapp: string | null;
  company: string | null;
  address: string | null;
  city: string | null;
  country: string | null;
  notes: string | null;
  _count?: { invoices: number };
  createdAt: string;
}

const EMPTY_FORM = {
  name: '', email: '', whatsapp: '', company: '',
  address: '', city: '', country: '', notes: '',
};

export default function ClientsManager({ onUpdate }: { onUpdate: () => void }) {
  const [clients, setClients]       = useState<Client[]>([]);
  const [loading, setLoading]       = useState(true);
  const [search, setSearch]         = useState('');
  const [open, setOpen]             = useState(false);
  const [editingId, setEditingId]   = useState<string | null>(null);
  const [form, setForm]             = useState(EMPTY_FORM);
  const [saving, setSaving]         = useState(false);
  const [selected, setSelected]     = useState<Client | null>(null);

  const loadClients = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/clients');
      if (!res.ok) throw new Error();
      setClients(await res.json());
    } catch {
      toast.error('Failed to load clients');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadClients(); }, [loadClients]);

  const openAdd = () => {
    setForm(EMPTY_FORM);
    setEditingId(null);
    setOpen(true);
  };

  const openEdit = (c: Client, e: React.MouseEvent) => {
    e.stopPropagation();
    setForm({
      name: c.name, email: c.email || '', whatsapp: c.whatsapp || '',
      company: c.company || '', address: c.address || '',
      city: c.city || '', country: c.country || '', notes: c.notes || '',
    });
    setEditingId(c.id);
    setOpen(true);
  };

  const handleSave = async () => {
    if (!form.name.trim()) { toast.error('Client name is required'); return; }
    setSaving(true);
    try {
      const res = await fetch(editingId ? `/api/clients/${editingId}` : '/api/clients', {
        method: editingId ? 'PATCH' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      });
      if (!res.ok) throw new Error();
      toast.success(editingId ? 'Client updated' : 'Client added');
      setOpen(false);
      await loadClients();
      onUpdate();
    } catch {
      toast.error('Failed to save client');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: string, e: React.MouseEvent) => {
    e.stopPropagation();
    if (!confirm('Delete this client? Their invoice history will also be removed.')) return;
    try {
      await fetch(`/api/clients/${id}`, { method: 'DELETE' });
      toast.success('Client deleted');
      if (selected?.id === id) setSelected(null);
      await loadClients();
      onUpdate();
    } catch {
      toast.error('Failed to delete client');
    }
  };

  const filtered = clients.filter(c =>
    c.name.toLowerCase().includes(search.toLowerCase()) ||
    c.company?.toLowerCase().includes(search.toLowerCase()) ||
    c.email?.toLowerCase().includes(search.toLowerCase())
  );

  if (loading) return (
    <div className="space-y-3 animate-pulse">
      <div className="h-12 bg-muted rounded-2xl" />
      {[1,2,3].map(i => <div key={i} className="h-20 bg-muted rounded-2xl" />)}
    </div>
  );

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            placeholder="Search clients…"
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="pl-9 rounded-xl"
          />
        </div>
        <Button onClick={openAdd} className="rounded-xl gap-2">
          <Plus className="w-4 h-4" /> Add Client
        </Button>
      </div>

      {/* Stats Bar */}
      <div className="grid grid-cols-3 gap-3">
        <div className="card-surface p-4 text-center">
          <p className="text-2xl font-bold text-foreground">{clients.length}</p>
          <p className="text-xs text-muted-foreground mt-1">Total Clients</p>
        </div>
        <div className="card-surface p-4 text-center">
          <p className="text-2xl font-bold text-primary">
            {clients.reduce((s, c) => s + (c._count?.invoices || 0), 0)}
          </p>
          <p className="text-xs text-muted-foreground mt-1">Total Invoices</p>
        </div>
        <div className="card-surface p-4 text-center">
          <p className="text-2xl font-bold text-green-400">
            {clients.filter(c => (c._count?.invoices || 0) > 0).length}
          </p>
          <p className="text-xs text-muted-foreground mt-1">Active Clients</p>
        </div>
      </div>

      {/* Client List */}
      {filtered.length === 0 ? (
        <div className="text-center py-16 text-muted-foreground">
          <Users className="w-12 h-12 mx-auto mb-4 opacity-20" />
          <p className="font-medium">{search ? 'No clients found' : 'No clients yet'}</p>
          <p className="text-sm mt-1 opacity-60">
            {search ? 'Try a different search' : 'Add your first client to start creating invoices'}
          </p>
          {!search && (
            <Button onClick={openAdd} className="mt-4 rounded-xl" variant="outline">
              <Plus className="w-4 h-4 mr-2" /> Add Client
            </Button>
          )}
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map(client => (
            <div
              key={client.id}
              onClick={() => setSelected(selected?.id === client.id ? null : client)}
              className="card-surface p-4 cursor-pointer hover:border-primary/50 transition-all group"
            >
              <div className="flex items-start justify-between gap-3">
                {/* Avatar + Info */}
                <div className="flex items-center gap-3 flex-1 min-w-0">
                  <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <span className="text-primary font-bold text-sm">
                      {client.name.charAt(0).toUpperCase()}
                    </span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <p className="font-semibold text-foreground">{client.name}</p>
                      {client.company && (
                        <span className="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">
                          {client.company}
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-3 mt-1 flex-wrap">
                      {client.email && (
                        <span className="text-xs text-muted-foreground flex items-center gap-1">
                          <Mail className="w-3 h-3" /> {client.email}
                        </span>
                      )}
                      {client.whatsapp && (
                        <span className="text-xs text-muted-foreground flex items-center gap-1">
                          <Phone className="w-3 h-3" /> {client.whatsapp}
                        </span>
                      )}
                      {client.city && (
                        <span className="text-xs text-muted-foreground flex items-center gap-1">
                          <MapPin className="w-3 h-3" /> {client.city}
                        </span>
                      )}
                    </div>
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-2 flex-shrink-0">
                  {(client._count?.invoices || 0) > 0 && (
                    <span className="text-xs bg-primary/10 text-primary px-2 py-1 rounded-full font-medium">
                      {client._count?.invoices} invoice{(client._count?.invoices || 0) > 1 ? 's' : ''}
                    </span>
                  )}
                  <Button
                    variant="ghost" size="sm"
                    onClick={e => openEdit(client, e)}
                    className="h-8 w-8 p-0 hover:bg-muted opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <Edit2 className="w-3.5 h-3.5" />
                  </Button>
                  <Button
                    variant="ghost" size="sm"
                    onClick={e => handleDelete(client.id, e)}
                    className="h-8 w-8 p-0 hover:bg-red-500/10 opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <Trash2 className="w-3.5 h-3.5 text-red-400" />
                  </Button>
                </div>
              </div>

              {/* Expanded Notes */}
              {selected?.id === client.id && client.notes && (
                <div className="mt-3 pt-3 border-t border-border">
                  <p className="text-xs text-muted-foreground">{client.notes}</p>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Add/Edit Dialog */}
      <Dialog open={open} onOpenChange={v => { if (!v) setOpen(false); }}>
        <DialogContent className="rounded-2xl max-w-md">
          <DialogHeader>
            <DialogTitle>{editingId ? 'Edit Client' : 'Add New Client'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 pt-2">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5 col-span-2">
                <label className="text-sm font-medium">Full Name *</label>
                <Input placeholder="e.g. Ahmed Al-Rashid" value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="rounded-xl" />
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Company</label>
                <Input placeholder="Company name" value={form.company} onChange={e => setForm({...form, company: e.target.value})} className="rounded-xl" />
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Email</label>
                <Input type="email" placeholder="email@example.com" value={form.email} onChange={e => setForm({...form, email: e.target.value})} className="rounded-xl" />
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">WhatsApp</label>
                <Input placeholder="+971 50 123 4567" value={form.whatsapp} onChange={e => setForm({...form, whatsapp: e.target.value})} className="rounded-xl" />
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">City</label>
                <Input placeholder="Dubai" value={form.city} onChange={e => setForm({...form, city: e.target.value})} className="rounded-xl" />
              </div>
              <div className="space-y-1.5 col-span-2">
                <label className="text-sm font-medium">Address</label>
                <Input placeholder="Street address" value={form.address} onChange={e => setForm({...form, address: e.target.value})} className="rounded-xl" />
              </div>
              <div className="space-y-1.5 col-span-2">
                <label className="text-sm font-medium">Notes</label>
                <Input placeholder="Any notes about this client…" value={form.notes} onChange={e => setForm({...form, notes: e.target.value})} className="rounded-xl" />
              </div>
            </div>
            <Button onClick={handleSave} className="w-full rounded-xl mt-2" disabled={saving}>
              {saving ? <><Loader2 className="w-4 h-4 mr-2 animate-spin" />Saving…</> : editingId ? 'Save Changes' : 'Add Client'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
