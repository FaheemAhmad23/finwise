'use client';

import { useState } from 'react';
import { useSession, signOut } from 'next-auth/react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import ExpenseManager from '@/components/expense/expense-manager';
import DebtManager from '@/components/debt/debt-manager';
import BillSplit from '@/components/bill/bill-split';
import ClientsManager from '@/components/clients/clients-manager';
import { LogOut, Download } from 'lucide-react';

export default function Home() {
  const { data: session } = useSession();
  const [refreshTrigger, setRefreshTrigger] = useState(0);
  const currency = (session?.user as any)?.currency || 'PKR';

  const handleRefresh = () => setRefreshTrigger(prev => prev + 1);

  const handleExportCSV = () => {
    const now = new Date();
    const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    window.location.href = `/api/export?format=csv&month=${month}`;
  };

  const TABS = [
    { value: 'expense', icon: '📊', label: 'Expenses' },
    { value: 'invoices', icon: '🧾', label: 'Invoices', badge: 'Soon' },
    { value: 'clients', icon: '👥', label: 'Clients' },
    { value: 'debt',    icon: '💳', label: 'Debts' },
    { value: 'bill',    icon: '💸', label: 'Bills' },
  ];

  return (
    <main className="min-h-screen bg-background">
      <div className="container mx-auto py-4 px-4 md:px-8 max-w-4xl">

        {/* Header */}
        <header className="mb-6 flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2.5 mb-1">
              <span className="text-3xl">💰</span>
              <div>
                <h1 className="text-2xl md:text-3xl font-bold text-foreground leading-none">FinWise</h1>
                <span className="text-[10px] font-bold tracking-widest text-primary uppercase">Pro</span>
              </div>
            </div>
            {session?.user && (
              <p className="text-sm text-muted-foreground">
                Welcome back,{' '}
                <span className="font-medium text-foreground">
                  {session.user.name || session.user.email}
                </span>
                <span className="mx-2 text-border">·</span>
                <span className="text-muted-foreground">{currency}</span>
              </p>
            )}
          </div>

          <div className="flex items-center gap-2 flex-shrink-0">
            <Button
              variant="ghost"
              size="sm"
              onClick={handleExportCSV}
              className="h-9 w-9 p-0 md:h-auto md:w-auto md:px-3 text-muted-foreground hover:text-foreground"
              title="Export this month as CSV"
            >
              <Download className="w-4 h-4 md:mr-1.5" />
              <span className="hidden md:inline text-sm">Export</span>
            </Button>

            <Button
              variant="ghost"
              size="sm"
              onClick={() => signOut({ callbackUrl: '/login' })}
              className="h-9 w-9 p-0 md:h-auto md:w-auto md:px-3 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
              title="Sign out"
            >
              <LogOut className="w-4 h-4 md:mr-1.5" />
              <span className="hidden md:inline text-sm">Sign out</span>
            </Button>
          </div>
        </header>

        {/* Tabs */}
        <div className="bg-card rounded-2xl border border-border shadow-sm overflow-hidden">
          <Tabs defaultValue="expense" className="w-full">
            <TabsList className={`grid w-full grid-cols-${TABS.length} bg-muted p-0 rounded-none border-b border-border h-auto`}>
              {TABS.map(tab => (
                <TabsTrigger
                  key={tab.value}
                  value={tab.value}
                  className="relative data-[state=active]:bg-card data-[state=active]:border-b-2 data-[state=active]:border-primary data-[state=active]:text-foreground rounded-none border-0 text-muted-foreground hover:text-foreground transition-colors text-sm font-medium py-3"
                >
                  <span className="hidden sm:inline">{tab.icon} {tab.label}</span>
                  <span className="sm:hidden">{tab.icon}</span>
                  {tab.badge && (
                    <span className="absolute -top-1 -right-1 text-[8px] bg-primary text-primary-foreground px-1 rounded-full font-bold hidden sm:inline">
                      {tab.badge}
                    </span>
                  )}
                </TabsTrigger>
              ))}
            </TabsList>

            <div className="p-4 md:p-6">
              <TabsContent value="expense" className="mt-0 animate-in fade-in">
                <ExpenseManager key={refreshTrigger} onUpdate={handleRefresh} />
              </TabsContent>

              <TabsContent value="invoices" className="mt-0 animate-in fade-in">
                <div className="text-center py-16 text-muted-foreground">
                  <span className="text-5xl">🧾</span>
                  <h3 className="text-xl font-bold text-foreground mt-4">Invoices — Coming in Sprint 2</h3>
                  <p className="text-sm mt-2 max-w-sm mx-auto">
                    Create professional invoices with your business logo, VAT calculation, and PDF export.
                    Clients module is live — add your clients now!
                  </p>
                  <div className="mt-6 flex flex-wrap justify-center gap-2 text-xs text-muted-foreground">
                    {['Line items', 'UAE 5% VAT', 'AED / USD / PKR', 'PDF export', 'WhatsApp share'].map(f => (
                      <span key={f} className="bg-muted px-3 py-1 rounded-full">{f}</span>
                    ))}
                  </div>
                </div>
              </TabsContent>

              <TabsContent value="clients" className="mt-0 animate-in fade-in">
                <ClientsManager key={refreshTrigger} onUpdate={handleRefresh} />
              </TabsContent>

              <TabsContent value="debt" className="mt-0 animate-in fade-in">
                <DebtManager key={refreshTrigger} onUpdate={handleRefresh} />
              </TabsContent>

              <TabsContent value="bill" className="mt-0 animate-in fade-in">
                <BillSplit key={refreshTrigger} onUpdate={handleRefresh} />
              </TabsContent>
            </div>
          </Tabs>
        </div>

        {/* Footer */}
        <footer className="mt-6 text-center text-xs text-muted-foreground">
          FinWise Pro · Your data is private and encrypted
        </footer>
      </div>
    </main>
  );
}
