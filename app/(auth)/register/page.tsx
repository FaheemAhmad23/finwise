'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { signIn } from 'next-auth/react';
import Link from 'next/link';

const CURRENCIES = [
  { code: 'PKR', label: '🇵🇰 Pakistani Rupee (PKR)' },
  { code: 'USD', label: '🇺🇸 US Dollar (USD)' },
  { code: 'EUR', label: '🇪🇺 Euro (EUR)' },
  { code: 'GBP', label: '🇬🇧 British Pound (GBP)' },
  { code: 'AED', label: '🇦🇪 UAE Dirham (AED)' },
  { code: 'SAR', label: '🇸🇦 Saudi Riyal (SAR)' },
  { code: 'INR', label: '🇮🇳 Indian Rupee (INR)' },
  { code: 'CAD', label: '🇨🇦 Canadian Dollar (CAD)' },
  { code: 'AUD', label: '🇦🇺 Australian Dollar (AUD)' },
];

export default function RegisterPage() {
  const router = useRouter();
  const [name, setName]         = useState('');
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [currency, setCurrency] = useState('PKR');
  const [error, setError]       = useState('');
  const [loading, setLoading]   = useState(false);
  const [showPw, setShowPw]     = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }

    setLoading(true);

    try {
      const res = await fetch('/api/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password, currency }),
      });

      const data = await res.json();

      if (!res.ok) {
        setError(data.error || 'Registration failed. Please try again.');
        setLoading(false);
        return;
      }

      // Auto-login after successful registration
      const loginResult = await signIn('credentials', {
        email,
        password,
        redirect: false,
      });

      if (loginResult?.ok) {
        router.push('/');
        router.refresh();
      } else {
        router.push('/login');
      }
    } catch {
      setError('Something went wrong. Please try again.');
      setLoading(false);
    }
  };

  const strength = password.length === 0 ? 0
    : password.length < 6 ? 1
    : password.length < 10 ? 2
    : 3;

  const strengthColor  = ['', 'bg-red-500', 'bg-amber-500', 'bg-emerald-500'][strength];
  const strengthLabel  = ['', 'Weak', 'Fair', 'Strong'][strength];

  return (
    <div className="min-h-screen bg-[#0D0F14] flex items-center justify-center px-4 py-12">
      {/* Ambient glows */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[-20%] right-[-10%] w-[600px] h-[600px] rounded-full bg-violet-600/10 blur-[120px]" />
        <div className="absolute bottom-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-indigo-600/10 blur-[120px]" />
      </div>

      <div className="relative w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 shadow-[0_0_40px_rgba(99,102,241,0.4)] mb-4">
            <span className="text-3xl">💰</span>
          </div>
          <h1 className="text-3xl font-bold text-white tracking-tight">FinWise</h1>
          <p className="text-slate-400 mt-1 text-sm">Start tracking your money today — free</p>
        </div>

        {/* Card */}
        <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-8 shadow-2xl">
          <h2 className="text-xl font-semibold text-white mb-6">Create your account</h2>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Name */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-300">Full name</label>
              <input
                type="text"
                id="register-name"
                placeholder="Ali Khan"
                autoComplete="name"
                value={name}
                onChange={e => setName(e.target.value)}
                className="w-full px-4 py-3 rounded-xl bg-white/8 border border-white/10 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm"
              />
            </div>

            {/* Email */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-300">Email</label>
              <input
                type="email"
                id="register-email"
                placeholder="you@example.com"
                autoComplete="email"
                required
                value={email}
                onChange={e => setEmail(e.target.value)}
                className="w-full px-4 py-3 rounded-xl bg-white/8 border border-white/10 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm"
              />
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-300">Password</label>
              <div className="relative">
                <input
                  type={showPw ? 'text' : 'password'}
                  id="register-password"
                  placeholder="Min. 6 characters"
                  autoComplete="new-password"
                  required
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  className="w-full px-4 py-3 pr-12 rounded-xl bg-white/8 border border-white/10 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm"
                />
                <button
                  type="button"
                  onClick={() => setShowPw(v => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition text-xs select-none"
                >
                  {showPw ? 'Hide' : 'Show'}
                </button>
              </div>

              {/* Password strength indicator */}
              {password.length > 0 && (
                <div className="space-y-1 pt-1">
                  <div className="flex gap-1.5">
                    {[1, 2, 3].map(i => (
                      <div
                        key={i}
                        className={`h-1 flex-1 rounded-full transition-all duration-300 ${
                          i <= strength ? strengthColor : 'bg-white/10'
                        }`}
                      />
                    ))}
                  </div>
                  <p className={`text-xs font-medium ${
                    strength === 1 ? 'text-red-400' : strength === 2 ? 'text-amber-400' : 'text-emerald-400'
                  }`}>
                    {strengthLabel} password
                  </p>
                </div>
              )}
            </div>

            {/* Currency */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-300">Default currency</label>
              <select
                id="register-currency"
                value={currency}
                onChange={e => setCurrency(e.target.value)}
                className="w-full px-4 py-3 rounded-xl bg-white/8 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm appearance-none"
              >
                {CURRENCIES.map(c => (
                  <option key={c.code} value={c.code} className="bg-[#1C2030] text-white">
                    {c.label}
                  </option>
                ))}
              </select>
              <p className="text-xs text-slate-500">You can change this later in Settings</p>
            </div>

            {/* Error */}
            {error && (
              <div className="flex items-center gap-2 text-red-400 text-sm bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
                <span>⚠️</span>
                <span>{error}</span>
              </div>
            )}

            {/* Submit */}
            <button
              type="submit"
              id="register-submit"
              disabled={loading}
              className="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold text-sm transition-all duration-200 shadow-[0_0_20px_rgba(99,102,241,0.3)] hover:shadow-[0_0_30px_rgba(99,102,241,0.5)] mt-2"
            >
              {loading ? (
                <span className="flex items-center justify-center gap-2">
                  <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  Creating account…
                </span>
              ) : (
                'Create Free Account'
              )}
            </button>
          </form>

          {/* Features preview */}
          <div className="mt-6 pt-5 border-t border-white/10 grid grid-cols-3 gap-3 text-center">
            {['✅ Unlimited tracking', '🔒 Encrypted data', '📊 Smart insights'].map(f => (
              <p key={f} className="text-xs text-slate-500">{f}</p>
            ))}
          </div>

          {/* Login link */}
          <p className="text-center text-sm text-slate-500 mt-5">
            Already have an account?{' '}
            <Link href="/login" className="text-indigo-400 hover:text-indigo-300 font-medium transition">
              Sign in
            </Link>
          </p>
        </div>

        <p className="text-center text-xs text-slate-600 mt-6">
          Free forever • No credit card required
        </p>
      </div>
    </div>
  );
}
