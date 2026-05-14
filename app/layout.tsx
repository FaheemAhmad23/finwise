import type { Metadata } from 'next';
import { Manrope } from 'next/font/google';
import { Analytics } from '@vercel/analytics/next';
import { Toaster } from 'sonner';
import { SessionProviderWrapper } from '@/components/session-provider';
import './globals.css';

const manrope = Manrope({
  subsets: ['latin'],
  variable: '--font-manrope',
  weight: ['400', '500', '600', '700', '800'],
});

export const metadata: Metadata = {
  title: 'FinWise — Smart Money Manager',
  description:
    'Track expenses, manage debts, split bills, and hit your savings goals — all in one beautifully simple app. Free forever.',
  keywords: 'expense tracker, budget planner, debt tracker, bill splitter, money manager, personal finance',
  metadataBase: new URL(process.env.NEXTAUTH_URL || 'http://localhost:3000'),
  openGraph: {
    title: 'FinWise — Smart Money Manager',
    description: 'Know where every rupee goes. Plan where every dollar lands.',
    type: 'website',
  },
  icons: {
    icon: [
      { url: '/icon-light-32x32.png', media: '(prefers-color-scheme: light)' },
      { url: '/icon-dark-32x32.png',  media: '(prefers-color-scheme: dark)' },
      { url: '/icon.svg', type: 'image/svg+xml' },
    ],
    apple: '/apple-icon.png',
  },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    // Force dark class — app is always dark themed
    <html lang="en" className={`${manrope.variable} dark`}>
      <body className="font-sans antialiased bg-background text-foreground">
        <SessionProviderWrapper>
          {children}
          <Toaster richColors position="top-right" theme="dark" />
          {process.env.NODE_ENV === 'production' && <Analytics />}
        </SessionProviderWrapper>
      </body>
    </html>
  );
}
