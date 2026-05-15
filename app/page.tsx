'use client';

import { useState } from 'react';
import { useSession, signOut } from 'next-auth/react';
import ExpenseManager from '@/components/expense/expense-manager';
import DebtManager from '@/components/debt/debt-manager';
import BillSplit from '@/components/bill/bill-split';
import ClientsManager from '@/components/clients/clients-manager';
import {
  BarChart3, Users, CreditCard, Scissors,
  LogOut, Download, Menu, X, ChevronRight,
} from 'lucide-react';

const NAV = [
  { id: 'expenses', label: 'Expenses',   icon: BarChart3,   emoji: '📊' },
  { id: 'clients',  label: 'Clients',    icon: Users,       emoji: '👥' },
  { id: 'debts',    label: 'Debts',      icon: CreditCard,  emoji: '💳' },
  { id: 'bills',    label: 'Bill Split', icon: Scissors,    emoji: '💸' },
];

const PAGE_DESC: Record<string, string> = {
  expenses: 'Track your income and spending',
  clients:  'Manage your client directory',
  debts:    'Track money owed between people',
  bills:    'Split bills equally among friends',
};

export default function Home() {
  const { data: session }           = useSession();
  const [page, setPage]             = useState('expenses');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);
  const currency = (session?.user as any)?.currency || 'PKR';

  const navigate = (id: string) => { setPage(id); setDrawerOpen(false); };
  const refresh  = () => setRefreshKey(k => k + 1);

  const handleExport = () => {
    const now = new Date();
    window.location.href = `/api/export?format=csv&month=${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  };

  const current = NAV.find(n => n.id === page)!;

  /* ── Sidebar content (shared desktop + mobile) ── */
  const SidebarContent = ({ mobile = false }: { mobile?: boolean }) => (
    <>
      {/* Logo */}
      <div className={`${mobile ? 'p-5' : 'p-6'} border-b border-white/[0.06]`}>
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center glow-indigo-sm">
            <span className="text-lg">💰</span>
          </div>
          <div>
            <p className="font-bold text-white leading-none tracking-wide">FinWise</p>
            <p className="text-[9px] font-bold tracking-[0.2em] text-indigo-400 uppercase mt-0.5">Pro</p>
          </div>
        </div>
        {session?.user && (
          <div className="mt-4">
            <p className="text-xs text-white/40">Welcome back</p>
            <p className="text-sm font-semibold text-white/90 truncate mt-0.5">
              {session.user.name || session.user.email}
            </p>
            <div className="inline-flex items-center gap-1.5 mt-2 px-2 py-0.5 rounded-full bg-white/5 border border-white/8">
              <div className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
              <span className="text-[10px] text-white/50 font-medium">{currency}</span>
            </div>
          </div>
        )}
      </div>

      {/* Nav */}
      <nav className="flex-1 p-3 space-y-1">
        <p className="text-[10px] font-bold tracking-[0.15em] text-white/25 uppercase px-3 mb-3">Main Menu</p>
        {NAV.map(item => {
          const active = page === item.id;
          const Icon   = item.icon;
          return (
            <button
              key={item.id}
              onClick={() => navigate(item.id)}
              className={`w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-medium transition-all duration-200 group ${
                active
                  ? 'nav-active'
                  : 'text-white/40 hover:text-white/80 hover:bg-white/[0.04]'
              }`}
            >
              <Icon className={`w-4 h-4 flex-shrink-0 transition-colors ${active ? 'text-indigo-300' : 'text-white/30 group-hover:text-white/60'}`} />
              <span>{item.label}</span>
              {active && <ChevronRight className="w-3.5 h-3.5 ml-auto text-indigo-400/60" />}
            </button>
          );
        })}
      </nav>

      {/* Bottom */}
      <div className="p-3 border-t border-white/[0.05] space-y-1">
        <button
          onClick={handleExport}
          className="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm text-white/35 hover:text-white/70 hover:bg-white/[0.04] transition-all"
        >
          <Download className="w-4 h-4" /> Export CSV
        </button>
        <button
          onClick={() => signOut({ callbackUrl: '/login' })}
          className="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm text-white/35 hover:text-red-400 hover:bg-red-500/[0.08] transition-all"
        >
          <LogOut className="w-4 h-4" /> Sign Out
        </button>
      </div>
    </>
  );

  return (
    <div className="min-h-screen bg-background flex relative overflow-hidden">

      {/* ── Background Glow Orbs ───────────────────────────── */}
      <div className="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        {/* Top-left indigo glow */}
        <div className="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full"
          style={{ background: 'radial-gradient(circle, rgba(99,102,241,0.18) 0%, transparent 70%)' }} />
        {/* Bottom-right purple glow */}
        <div className="absolute -bottom-48 -right-32 w-[600px] h-[600px] rounded-full"
          style={{ background: 'radial-gradient(circle, rgba(139,92,246,0.13) 0%, transparent 70%)' }} />
        {/* Center-right blue glow */}
        <div className="absolute top-1/2 right-0 -translate-y-1/2 w-[300px] h-[300px] rounded-full"
          style={{ background: 'radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%)' }} />
      </div>

      {/* ── Desktop Sidebar ────────────────────────────────── */}
      <aside className="hidden md:flex flex-col w-60 fixed top-0 left-0 h-full z-30"
        style={{
          background: 'linear-gradient(180deg, rgba(8,8,28,0.92) 0%, rgba(5,5,18,0.95) 100%)',
          backdropFilter: 'blur(24px)',
          borderRight: '1px solid rgba(255,255,255,0.06)',
        }}
      >
        <SidebarContent />
      </aside>

      {/* ── Mobile Overlay ─────────────────────────────────── */}
      {drawerOpen && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 md:hidden"
          onClick={() => setDrawerOpen(false)} />
      )}

      {/* ── Mobile Drawer ──────────────────────────────────── */}
      <div className={`fixed top-0 left-0 h-full w-64 z-50 flex flex-col transform transition-transform duration-300 ease-out md:hidden ${drawerOpen ? 'translate-x-0' : '-translate-x-full'}`}
        style={{
          background: 'linear-gradient(180deg, rgba(8,8,28,0.98) 0%, rgba(5,5,18,0.99) 100%)',
          backdropFilter: 'blur(32px)',
          borderRight: '1px solid rgba(255,255,255,0.06)',
        }}
      >
        <div className="flex items-center justify-between px-4 pt-4">
          <div /> {/* spacer */}
          <button onClick={() => setDrawerOpen(false)}
            className="p-2 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] transition-colors ml-auto">
            <X className="w-4 h-4 text-white/50" />
          </button>
        </div>
        <SidebarContent mobile />
      </div>

      {/* ── Main Content ───────────────────────────────────── */}
      <main className="flex-1 md:ml-60 min-h-screen flex flex-col relative z-10">

        {/* Mobile top bar */}
        <header className="md:hidden sticky top-0 z-20 flex items-center justify-between px-4 py-3"
          style={{
            background: 'rgba(3,3,15,0.85)',
            backdropFilter: 'blur(20px)',
            borderBottom: '1px solid rgba(255,255,255,0.05)',
          }}
        >
          <button onClick={() => setDrawerOpen(true)}
            className="p-2 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] transition-colors">
            <Menu className="w-5 h-5 text-white/70" />
          </button>
          <div className="flex items-center gap-2.5">
            <div className="w-7 h-7 rounded-lg bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center">
              <span className="text-sm">💰</span>
            </div>
            <span className="font-bold text-white text-sm tracking-wide">FinWise</span>
            <span className="text-[9px] font-bold tracking-[0.15em] text-indigo-400 uppercase">Pro</span>
          </div>
          <div className="w-9" />
        </header>

        {/* Page body */}
        <div className="flex-1 p-4 md:p-8 max-w-5xl w-full mx-auto">

          {/* Desktop page header */}
          <div className="hidden md:flex items-end justify-between mb-8">
            <div>
              <p className="text-white/30 text-xs font-medium uppercase tracking-widest mb-1">
                {current.emoji} {current.label}
              </p>
              <h1 className="text-3xl font-bold text-white leading-none">
                {PAGE_DESC[page]}
              </h1>
            </div>
            <div className="flex items-center gap-2 text-xs text-white/30">
              <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
              Live
            </div>
          </div>

          {/* Glass content card */}
          <div className="rounded-2xl overflow-hidden"
            style={{
              background: 'rgba(255,255,255,0.03)',
              backdropFilter: 'blur(24px)',
              border: '1px solid rgba(255,255,255,0.07)',
              boxShadow: '0 8px 32px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05)',
            }}
          >
            <div className="p-4 md:p-6">
              {page === 'expenses' && <ExpenseManager key={refreshKey} onUpdate={refresh} />}
              {page === 'clients'  && <ClientsManager key={refreshKey} onUpdate={refresh} />}
              {page === 'debts'    && <DebtManager    key={refreshKey} onUpdate={refresh} />}
              {page === 'bills'    && <BillSplit       key={refreshKey} onUpdate={refresh} />}
            </div>
          </div>

        </div>
      </main>
    </div>
  );
}
