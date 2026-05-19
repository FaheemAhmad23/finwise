import type { Metadata } from 'next';
import { Inter, Plus_Jakarta_Sans } from 'next/font/google';
import { Analytics } from '@vercel/analytics/next';
import { Toaster } from 'sonner';
import './globals.css';

const inter = Inter({
  subsets: ['latin'],
  variable: '--font-inter',
  weight: ['400', '500', '600', '700'],
  display: 'swap',
});

const jakarta = Plus_Jakarta_Sans({
  subsets: ['latin'],
  variable: '--font-jakarta',
  weight: ['500', '600', '700', '800'],
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'FinWise — Offline Money Manager',
  description:
    'Track expenses, manage debts, split bills offline. All data saved locally on your device. No login required.',
  keywords: 'expense tracker, budget planner, debt tracker, bill splitter, money manager, offline, personal finance',
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
    <html lang="en" className={`${inter.variable} ${jakarta.variable} dark`}>
      <body className="font-sans antialiased bg-background text-foreground">
        {children}
        <Toaster richColors position="top-right" theme="dark" />
        {process.env.NODE_ENV === 'production' && <Analytics />}
      </body>
    </html>
  );
}
