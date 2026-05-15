'use client';

import { useState } from 'react';
import { useSession, signOut } from 'next-auth/react';
import ExpenseManager from '@/components/expense/expense-manager';
import DebtManager from '@/components/debt/debt-manager';
import BillSplit from '@/components/bill/bill-split';
import ClientsManager from '@/components/clients/clients-manager';
import {
  BarChart3, Users, CreditCard, Scissors,
  LogOut, Bell, Settings, Search, Menu, X,
} from 'lucide-react';

const NAV = [
  { id: 'expenses', label: 'Expenses',   icon: BarChart3 },
  { id: 'clients',  label: 'Clients',    icon: Users     },
  { id: 'debts',    label: 'Debts',      icon: CreditCard},
  { id: 'bills',    label: 'Bill Split', icon: Scissors  },
];

const GREETINGS = ['Good morning', 'Good afternoon', 'Good evening'];
function greeting() {
  const h = new Date().getHours();
  return h < 12 ? GREETINGS[0] : h < 17 ? GREETINGS[1] : GREETINGS[2];
}

export default function Home() {
  const { data: session }           = useSession();
  const [page, setPage]             = useState('expenses');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);

  const navigate = (id: string) => { setPage(id); setDrawerOpen(false); };
  const refresh  = () => setRefreshKey(k => k + 1);
  const userName = session?.user?.name?.split(' ')[0] || 'there';
  const userInitials = (session?.user?.name || session?.user?.email || 'U')
    .split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase();

  /* ── Sidebar Nav ───────────────────────────────────────── */
  const Sidebar = () => (
    <div className="flex flex-col h-full">
      {/* Logo */}
      <div className="px-5 pt-6 pb-5">
        <div className="flex items-center gap-2.5 mb-5">
          <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center glow-orange-sm">
            <span className="text-white text-base font-black">F</span>
          </div>
          <div>
            <span className="font-bold text-white text-base tracking-wide">FinWise</span>
            <span className="ml-1.5 text-[9px] font-bold tracking-[0.2em] text-primary uppercase">Pro</span>
          </div>
        </div>

        {/* Search */}
        <div className="search-box flex items-center gap-2.5 px-3 py-2.5">
          <Search className="w-3.5 h-3.5 text-white/30 flex-shrink-0" />
          <span className="text-sm text-white/25">search</span>
        </div>
      </div>

      {/* Main Nav */}
      <nav className="flex-1 px-3 space-y-0.5">
        {NAV.map(item => {
          const active = page === item.id;
          const Icon   = item.icon;
          return (
            <button
              key={item.id}
              onClick={() => navigate(item.id)}
              className={`w-full flex items-center gap-3 px-4 py-3 text-sm font-medium transition-all duration-150 ${
                active
                  ? 'nav-active text-white'
                  : 'text-white/45 hover:text-white/80 hover:bg-white/[0.04] rounded-2xl'
              }`}
            >
              <Icon className={`w-4 h-4 flex-shrink-0 ${active ? 'text-white' : 'text-white/35'}`} />
              {item.label}
            </button>
          );
        })}
      </nav>

      {/* Bottom section — Notifications + Settings */}
      <div className="px-3 pb-2 space-y-0.5">
        <div className="w-full flex items-center justify-between px-4 py-3 text-white/40 hover:text-white/70 hover:bg-white/[0.04] rounded-2xl cursor-pointer transition-all">
          <div className="flex items-center gap-3 text-sm font-medium">
            <Bell className="w-4 h-4" />
            Notifications
          </div>
          <span className="text-[10px] bg-primary text-white font-bold px-1.5 py-0.5 rounded-full leading-none">4</span>
        </div>
        <button className="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-white/40 hover:text-white/70 hover:bg-white/[0.04] rounded-2xl transition-all">
          <Settings className="w-4 h-4" /> Settings
        </button>
      </div>

      {/* Divider */}
      <div className="mx-4 my-2 h-px bg-white/[0.06]" />

      {/* Logout */}
      <div className="px-3 pb-5">
        <button
          onClick={() => signOut({ callbackUrl: '/login' })}
          className="w-full flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-white/30 hover:text-red-400 hover:bg-red-500/[0.08] rounded-2xl transition-all"
        >
          <LogOut className="w-4 h-4" /> logout
        </button>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-background flex">

      {/* ── Desktop Sidebar ─────────────────────────────── */}
      <aside className="hidden md:block w-52 flex-shrink-0 fixed top-0 left-0 h-full z-30"
        style={{ background: 'oklch(0.11 0.006 260)', borderRight: '1px solid oklch(1 0 0 / 0.06)' }}
      >
        <Sidebar />
      </aside>

      {/* ── Mobile Overlay ──────────────────────────────── */}
      {drawerOpen && (
        <div className="fixed inset-0 bg-black/75 z-40 md:hidden" onClick={() => setDrawerOpen(false)} />
      )}

      {/* ── Mobile Drawer ───────────────────────────────── */}
      <div className={`fixed top-0 left-0 h-full w-56 z-50 transform transition-transform duration-300 md:hidden ${drawerOpen ? 'translate-x-0' : '-translate-x-full'}`}
        style={{ background: 'oklch(0.11 0.006 260)', borderRight: '1px solid oklch(1 0 0 / 0.06)' }}
      >
        <button onClick={() => setDrawerOpen(false)} className="absolute top-4 right-4 p-2 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] transition-colors">
          <X className="w-4 h-4 text-white/50" />
        </button>
        <Sidebar />
      </div>

      {/* ── Main ────────────────────────────────────────── */}
      <main className="flex-1 md:ml-52 min-h-screen flex flex-col">

        {/* ── Top Bar ─────────────────────────────────── */}
        <header className="sticky top-0 z-20 flex items-center justify-between px-6 py-4"
          style={{ background: 'oklch(0.09 0.005 260)', borderBottom: '1px solid oklch(1 0 0 / 0.06)' }}
        >
          {/* Mobile hamburger */}
          <button onClick={() => setDrawerOpen(true)} className="md:hidden p-2 -ml-1 rounded-xl hover:bg-white/[0.06] transition-colors">
            <Menu className="w-5 h-5 text-white/60" />
          </button>

          {/* Page greeting — desktop */}
          <div className="hidden md:block">
            <p className="text-white/40 text-sm">{greeting()},</p>
            <p className="text-white font-bold text-lg leading-tight">{userName}</p>
          </div>

          {/* Right side: export + avatar */}
          <div className="flex items-center gap-3 ml-auto">
            <button
              onClick={() => {
                const now = new Date();
                window.location.href = `/api/export?format=csv&month=${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
              }}
              className="hidden sm:flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-medium text-white/50 hover:text-white hover:bg-white/[0.06] transition-all border border-white/[0.07]"
            >
              ↓ Export
            </button>

            {/* Avatar */}
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold glow-orange-sm cursor-pointer">
                {userInitials}
              </div>
              <div className="hidden sm:block">
                <p className="text-white text-sm font-semibold leading-none">{session?.user?.name || 'User'}</p>
                <p className="text-white/35 text-xs mt-0.5 truncate max-w-[140px]">{session?.user?.email}</p>
              </div>
            </div>
          </div>
        </header>

        {/* ── Page Content ────────────────────────────── */}
        <div className="flex-1 p-5 md:p-7 max-w-6xl w-full mx-auto">

          {/* Section title */}
          <div className="mb-6 flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-white">
                {NAV.find(n => n.id === page)?.label}
              </h1>
              <p className="text-white/35 text-sm mt-0.5">
                {page === 'expenses' && 'Track your income and spending'}
                {page === 'clients'  && 'Manage your business clients'}
                {page === 'debts'    && 'Track money owed between people'}
                {page === 'bills'    && 'Split bills among friends'}
              </p>
            </div>
            {/* Live dot */}
            <div className="flex items-center gap-2 text-xs text-white/25 font-medium">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              Live
            </div>
          </div>

          {/* Content card */}
          <div className="card-surface p-5 md:p-7">
            {page === 'expenses' && <ExpenseManager key={refreshKey} onUpdate={refresh} />}
            {page === 'clients'  && <ClientsManager key={refreshKey} onUpdate={refresh} />}
            {page === 'debts'    && <DebtManager    key={refreshKey} onUpdate={refresh} />}
            {page === 'bills'    && <BillSplit       key={refreshKey} onUpdate={refresh} />}
          </div>

        </div>
      </main>
    </div>
  );
}
