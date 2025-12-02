@extends('layouts.frontend')

@section('content')
    <div class="relative overflow-hidden bg-gradient-to-br from-indigo-50 via-white to-rose-50">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-20 top-10 h-52 w-52 rounded-full bg-indigo-200/50 blur-3xl"></div>
            <div class="absolute bottom-0 right-10 h-64 w-64 rounded-full bg-rose-200/50 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col items-center justify-center px-4 py-12 lg:px-6">
            <div class="mb-10 text-center animate-fade-in">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100 shadow">Welcome back</p>
                <h1 class="mt-3 text-3xl font-black text-slate-900 sm:text-4xl">Log in to oWorld</h1>
                <p class="mt-2 text-sm text-slate-600">Access your dashboard and manage your experiences.</p>
            </div>

            <div class="grid w-full max-w-5xl gap-6 md:grid-cols-5 animate-rise">
                <div class="md:col-span-3 rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-xl ring-1 ring-white/70 backdrop-blur">
                    @if ($errors->any())
                        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <form method="post" action="{{ route('login.store') }}" class="space-y-4">
                        @csrf
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-800">Email</label>
                            <input value="{{ old('email') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-800">Password</label>
                            <input class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="password" name="password" required>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Remember me
                        </label>
                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-700 hover:shadow-xl" type="submit">
                            <span>Log in</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7"/></svg>
                        </button>
                    </form>
                    <p class="mt-4 text-center text-sm text-slate-600">No account yet? <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-700">Create one</a></p>
                </div>
                <div class="md:col-span-2 flex flex-col justify-between gap-4 rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-500 to-rose-500 p-6 text-white shadow-xl">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-wide text-white/80">Secure access</p>
                        <h3 class="text-2xl font-black">Stay connected to your world</h3>
                        <p class="text-sm text-white/80">Track offers, events, and categories you manage. Your session stays encrypted.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-3 py-2 text-sm font-semibold backdrop-blur">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/20">🔒</span>
                            2-step verification ready
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-3 py-2 text-sm font-semibold backdrop-blur">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/20">⚡</span>
                            Instant dashboard access
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-3 py-2 text-sm font-semibold backdrop-blur">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/20">🎟️</span>
                            Manage events & offers easily
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
