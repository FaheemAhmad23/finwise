'use client';

import { useState } from 'react';
import { useSession, signOut } from 'next-auth/react';
import ExpenseManager from '@/components/expense/expense-manager';
import DebtManager from '@/components/debt/debt-manager';
import BillSplit from '@/components/bill/bill-split';
import {
  BarChart3, CreditCard, Scissors, LogOut, Download, Menu, X,
} from 'lucide-react';

const NAV = [
  { id: 'expenses', label: 'Expenses',   icon: BarChart3,  emoji: '📊' },
  { id: 'debts',    label: 'Debts',      icon: CreditCard, emoji: '💳' },
  { id: 'bills',    label: 'Bill Split', icon: Scissors,   emoji: '💸' },
];

const PAGE_DESC: Record<string, string> = {
  expenses: 'Track your income and spending',
  debts:    'Track money owed between people',
  bills:    'Split bills among friends',
};

export default function Home() {
  const { data: session }           = useSession();
  const [page, setPage]             = useState('expenses');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);

  const navigate = (id: string) => { setPage(id); setDrawerOpen(false); };
  const refresh  = () => setRefreshKey(k => k + 1);

  const handleExport = () => {
    const now = new Date();
    window.location.href = `/api/export?format=csv&month=${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  };

  const userName     = session?.user?.name?.split(' ')[0] || 'there';
  const userInitials = (session?.user?.name || session?.user?.email || 'U')
    .split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase();

  // ── Shared sidebar nav content ────────────────────────
  const NavLinks = () => (
    <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
      {NAV.map(item => {
        const active = page === item.id;
        const Icon   = item.icon;
        return (
          <button
            key={item.id}
            onClick={() => navigate(item.id)}
            className={`w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl text-sm font-medium transition-all duration-150 ${
              active
                ? 'bg-primary/90 text-white shadow-lg'
                : 'text-white/40 hover:text-white/80 hover:bg-white/[0.06]'
            }`}
            style={active ? { boxShadow: '0 4px 14px oklch(0.65 0.195 34 / 0.35)' } : {}}
          >
            <Icon className={`w-4 h-4 flex-shrink-0 ${active ? 'text-white' : 'text-white/30'}`} />
            {item.label}
          </button>
        );
      })}
    </nav>
  );

  const BottomLinks = () => (
    <div className="p-3 border-t border-white/[0.05] space-y-1">
      <button
        onClick={handleExport}
        className="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-sm text-white/35 hover:text-white/70 hover:bg-white/[0.05] transition-all"
      >
        <Download className="w-4 h-4" /> Export CSV
      </button>
      <button
        onClick={() => signOut({ callbackUrl: '/login' })}
        className="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-sm text-white/35 hover:text-red-400 hover:bg-red-500/[0.08] transition-all"
      >
        <LogOut className="w-4 h-4" /> Sign out
      </button>
    </div>
  );

  // ── Sidebar glass style ───────────────────────────────
  const sidebarStyle = {
    background: 'rgba(9, 9, 20, 0.82)',
    backdropFilter: 'blur(24px)',
    WebkitBackdropFilter: 'blur(24px)',
    borderRight: '1px solid rgba(255,255,255,0.06)',
  } as React.CSSProperties;

  return (
    <div className="min-h-screen bg-background flex">

      {/* ── Desktop Sidebar ─────────────────────────────── */}
      <aside className="hidden md:flex flex-col w-52 fixed top-0 left-0 h-full z-30" style={sidebarStyle}>
        {/* Logo */}
        <div className="px-5 pt-6 pb-4 border-b border-white/[0.05]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-primary/90 flex items-center justify-center text-white font-black text-sm"
              style={{boxShadow:'0 4px 12px oklch(0.65 0.195 34 / 0.40)'}}>F</div>
            <div>
              <span className="font-bold text-white text-sm tracking-wide">FinWise</span>
              <span className="ml-1.5 text-[9px] font-bold tracking-[0.2em] text-primary uppercase">Pro</span>
            </div>
          </div>
          {session?.user && (
            <p className="text-xs text-white/35 mt-3 truncate">{session.user.name || session.user.email}</p>
          )}
        </div>
        <NavLinks />
        <BottomLinks />
      </aside>

      {/* ── Mobile Drawer Overlay ────────────────────────── */}
      {drawerOpen && (
        <div
          className="fixed inset-0 z-40 md:hidden"
          style={{ background: 'rgba(0,0,0,0.65)', backdropFilter: 'blur(4px)' }}
          onClick={() => setDrawerOpen(false)}
        />
      )}

      {/* ── Mobile Drawer ────────────────────────────────── */}
      <div
        className={`fixed top-0 left-0 h-full w-60 z-50 flex flex-col transform transition-transform duration-300 ease-out md:hidden ${drawerOpen ? 'translate-x-0' : '-translate-x-full'}`}
        style={{
          background: 'rgba(7, 7, 18, 0.95)',
          backdropFilter: 'blur(32px)',
          WebkitBackdropFilter: 'blur(32px)',
          borderRight: '1px solid rgba(255,255,255,0.07)',
        }}
      >
        {/* Drawer header */}
        <div className="flex items-center justify-between px-5 pt-6 pb-4 border-b border-white/[0.05]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-primary/90 flex items-center justify-center text-white font-black text-sm">F</div>
            <div>
              <span className="font-bold text-white text-sm">FinWise</span>
              <span className="ml-1.5 text-[9px] font-bold tracking-widest text-primary uppercase">Pro</span>
            </div>
          </div>
          <button onClick={() => setDrawerOpen(false)}
            className="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-white/[0.08] text-white/40 hover:text-white transition-all">
            <X className="w-4 h-4" />
          </button>
        </div>

        {session?.user && (
          <div className="px-5 py-3 border-b border-white/[0.05]">
            <p className="text-xs text-white/35">Signed in as</p>
            <p className="text-sm font-semibold text-white mt-0.5 truncate">{session.user.name || session.user.email}</p>
          </div>
        )}

        <NavLinks />
        <BottomLinks />
      </div>

      {/* ── Main ─────────────────────────────────────────── */}
      <main className="flex-1 md:ml-52 min-h-screen flex flex-col">

        {/* ── Mobile Top Bar ───────────────────────────── */}
        <header
          className="md:hidden sticky top-0 z-20 flex items-center justify-between px-4 py-3"
          style={{
            background: 'rgba(5, 5, 15, 0.90)',
            backdropFilter: 'blur(20px)',
            WebkitBackdropFilter: 'blur(20px)',
            borderBottom: '1px solid rgba(255,255,255,0.05)',
          }}
        >
          <button onClick={() => setDrawerOpen(true)}
            className="w-9 h-9 flex items-center justify-center rounded-xl bg-white/[0.05] hover:bg-white/[0.09] transition-colors">
            <Menu className="w-5 h-5 text-white/70" />
          </button>

          <div className="flex items-center gap-2">
            <div className="w-6 h-6 rounded-md bg-primary/90 flex items-center justify-center text-white font-black text-[10px]">F</div>
            <span className="font-bold text-white text-sm">FinWise</span>
            <span className="text-[9px] font-bold tracking-widest text-primary uppercase">Pro</span>
          </div>

          {/* Avatar */}
          <div className="w-9 h-9 rounded-full bg-primary/80 flex items-center justify-center text-white text-xs font-bold">
            {userInitials}
          </div>
        </header>

        {/* ── Desktop Top Bar ──────────────────────────── */}
        <header className="hidden md:flex items-center justify-between px-7 py-4 border-b border-white/[0.04]">
          <div>
            <p className="text-white/30 text-xs font-medium">
              {new Date().toLocaleString('default', {weekday:'long', month:'long', day:'numeric'})}
            </p>
            <h1 className="text-lg font-bold text-white leading-tight">
              Good {new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 17 ? 'afternoon' : 'evening'}, {userName}
            </h1>
          </div>
          <div className="flex items-center gap-3">
            <button onClick={handleExport}
              className="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium text-white/40 hover:text-white hover:bg-white/[0.06] transition-all border border-white/[0.07]">
              <Download className="w-3.5 h-3.5" /> Export CSV
            </button>
            <div className="flex items-center gap-2.5 pl-3 border-l border-white/[0.07]">
              <div className="w-8 h-8 rounded-full bg-primary/80 flex items-center justify-center text-white text-xs font-bold">
                {userInitials}
              </div>
              <div className="hidden lg:block">
                <p className="text-white text-xs font-semibold leading-none">{session?.user?.name || 'User'}</p>
                <p className="text-white/30 text-[11px] mt-0.5 truncate max-w-[130px]">{session?.user?.email}</p>
              </div>
            </div>
          </div>
        </header>

        {/* ── Page Content ─────────────────────────────── */}
        <div className="flex-1 p-4 md:p-6 lg:p-8 max-w-5xl w-full mx-auto">

          {/* Section header */}
          <div className="mb-5">
            <h2 className="text-xl font-bold text-white">
              {NAV.find(n => n.id === page)?.emoji} {NAV.find(n => n.id === page)?.label}
            </h2>
            <p className="text-white/30 text-xs mt-0.5">{PAGE_DESC[page]}</p>
          </div>

          {/* Content */}
          <div className="rounded-2xl overflow-hidden"
            style={{
              background: 'rgba(255,255,255,0.025)',
              backdropFilter: 'blur(12px)',
              border: '1px solid rgba(255,255,255,0.06)',
              boxShadow: '0 8px 40px rgba(0,0,0,0.40)',
            }}
          >
            <div className="p-4 md:p-6">
              {page === 'expenses' && <ExpenseManager key={refreshKey} onUpdate={refresh} />}
              {page === 'debts'    && <DebtManager    key={refreshKey} onUpdate={refresh} />}
              {page === 'bills'    && <BillSplit       key={refreshKey} onUpdate={refresh} />}
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
