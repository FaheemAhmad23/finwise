'use client';

import { useState } from 'react';
import { useSession, signOut } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import ExpenseManager from '@/components/expense/expense-manager';
import DebtManager from '@/components/debt/debt-manager';
import BillSplit from '@/components/bill/bill-split';
import ClientsManager from '@/components/clients/clients-manager';
import {
  LayoutDashboard, Users, CreditCard, SplitSquareVertical,
  LogOut, Download, Menu, X, ChevronRight,
} from 'lucide-react';

const NAV = [
  { id: 'expenses', label: 'Expenses',   emoji: '📊' },
  { id: 'clients',  label: 'Clients',    emoji: '👥' },
  { id: 'debts',    label: 'Debts',      emoji: '💳' },
  { id: 'bills',    label: 'Bill Split', emoji: '💸' },
];

export default function Home() {
  const { data: session } = useSession();
  const [page, setPage]             = useState('expenses');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [refreshTrigger, setRefreshTrigger] = useState(0);
  const currency = (session?.user as any)?.currency || 'PKR';

  const handleRefresh = () => setRefreshTrigger(p => p + 1);

  const navigate = (id: string) => {
    setPage(id);
    setDrawerOpen(false);
  };

  const handleExportCSV = () => {
    const now   = new Date();
    const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    window.location.href = `/api/export?format=csv&month=${month}`;
  };

  const current = NAV.find(n => n.id === page)!;

  const PAGE_DESC: Record<string, string> = {
    expenses: 'Track your income and expenses',
    clients:  'Manage your client directory',
    debts:    'Track money owed between people',
    bills:    'Split bills equally among friends',
  };

  return (
    <div className="min-h-screen bg-background flex">

      {/* ── Desktop Sidebar ─────────────────────────────────── */}
      <aside className="hidden md:flex flex-col w-56 bg-card border-r border-border fixed top-0 left-0 h-full z-30">
        <div className="p-5 border-b border-border">
          <div className="flex items-center gap-2.5">
            <span className="text-2xl">💰</span>
            <div>
              <p className="text-lg font-bold text-foreground leading-none">FinWise</p>
              <p className="text-[10px] font-bold tracking-widest text-primary uppercase">Pro</p>
            </div>
          </div>
          {session?.user && (
            <p className="text-xs text-muted-foreground mt-3 truncate">
              {session.user.name || session.user.email}
            </p>
          )}
        </div>

        <nav className="flex-1 p-3 space-y-1">
          {NAV.map(item => {
            const active = page === item.id;
            return (
              <button
                key={item.id}
                onClick={() => navigate(item.id)}
                className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                  active
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
                }`}
              >
                <span className="text-base">{item.emoji}</span>
                {item.label}
                {active && <ChevronRight className="w-4 h-4 ml-auto opacity-70" />}
              </button>
            );
          })}
        </nav>

        <div className="p-3 border-t border-border space-y-1">
          <button
            onClick={handleExportCSV}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-all"
          >
            <Download className="w-4 h-4" /> Export CSV
          </button>
          <button
            onClick={() => signOut({ callbackUrl: '/login' })}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-all"
          >
            <LogOut className="w-4 h-4" /> Sign Out
          </button>
        </div>
      </aside>

      {/* ── Mobile Drawer Overlay ───────────────────────────── */}
      {drawerOpen && (
        <div className="fixed inset-0 bg-black/60 z-40 md:hidden" onClick={() => setDrawerOpen(false)} />
      )}

      {/* ── Mobile Drawer ───────────────────────────────────── */}
      <div className={`fixed top-0 left-0 h-full w-64 bg-card border-r border-border z-50 transform transition-transform duration-300 md:hidden ${drawerOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="p-5 border-b border-border flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <span className="text-2xl">💰</span>
            <div>
              <p className="text-lg font-bold text-foreground leading-none">FinWise</p>
              <p className="text-[10px] font-bold tracking-widest text-primary uppercase">Pro</p>
            </div>
          </div>
          <button onClick={() => setDrawerOpen(false)} className="p-1 rounded-lg hover:bg-muted transition-colors">
            <X className="w-5 h-5 text-muted-foreground" />
          </button>
        </div>

        {session?.user && (
          <div className="px-5 py-3 border-b border-border">
            <p className="text-xs text-muted-foreground">Signed in as</p>
            <p className="text-sm font-medium text-foreground truncate">{session.user.name || session.user.email}</p>
            <p className="text-xs text-muted-foreground">{currency}</p>
          </div>
        )}

        <nav className="p-3 space-y-1">
          {NAV.map(item => {
            const active = page === item.id;
            return (
              <button
                key={item.id}
                onClick={() => navigate(item.id)}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all ${
                  active
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
                }`}
              >
                <span className="text-lg">{item.emoji}</span>
                {item.label}
              </button>
            );
          })}
        </nav>

        <div className="absolute bottom-0 left-0 right-0 p-3 border-t border-border space-y-1">
          <button
            onClick={handleExportCSV}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-all"
          >
            <Download className="w-4 h-4" /> Export CSV
          </button>
          <button
            onClick={() => signOut({ callbackUrl: '/login' })}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-all"
          >
            <LogOut className="w-4 h-4" /> Sign Out
          </button>
        </div>
      </div>

      {/* ── Main Content ────────────────────────────────────── */}
      <main className="flex-1 md:ml-56 min-h-screen flex flex-col">
        {/* Mobile Top Bar */}
        <header className="md:hidden sticky top-0 z-20 bg-card/95 backdrop-blur border-b border-border px-4 py-3 flex items-center justify-between">
          <button onClick={() => setDrawerOpen(true)} className="p-2 rounded-xl hover:bg-muted transition-colors">
            <Menu className="w-5 h-5 text-foreground" />
          </button>
          <div className="flex items-center gap-2">
            <span className="text-lg">💰</span>
            <span className="font-bold text-foreground text-sm">FinWise</span>
            <span className="text-[9px] font-bold tracking-widest text-primary uppercase">Pro</span>
          </div>
          <div className="w-9" />
        </header>

        {/* Page */}
        <div className="flex-1 p-4 md:p-8 max-w-4xl w-full mx-auto">
          <div className="mb-6 hidden md:block">
            <h1 className="text-2xl font-bold text-foreground">{current.emoji} {current.label}</h1>
            <p className="text-sm text-muted-foreground mt-0.5">{PAGE_DESC[page]}</p>
          </div>

          <div className="bg-card rounded-2xl border border-border p-4 md:p-6">
            {page === 'expenses' && <ExpenseManager key={refreshTrigger} onUpdate={handleRefresh} />}
            {page === 'clients'  && <ClientsManager key={refreshTrigger} onUpdate={handleRefresh} />}
            {page === 'debts'    && <DebtManager    key={refreshTrigger} onUpdate={handleRefresh} />}
            {page === 'bills'    && <BillSplit       key={refreshTrigger} onUpdate={handleRefresh} />}
          </div>
        </div>
      </main>
    </div>
  );
}
