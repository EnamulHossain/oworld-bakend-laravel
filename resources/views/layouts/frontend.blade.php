<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $site = $settings ?? [
            'siteTitle' => 'oWorld',
            'tagline' => 'Local experiences, curated offers, and cultural insights.',
            'logo' => null,
            'contactEmail' => 'hello@oworld.test',
        ];
    @endphp
    <title>{{ $site['siteTitle'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
    <div class="flex min-h-screen flex-col">
        <nav class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-4 lg:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-black text-slate-900">
                    <span class="text-xl">🌍</span>
                    <span class="tracking-tight">{{ strtoupper($site['siteTitle']) }}</span>
                </a>
                <div class="hidden items-center gap-2 rounded-full border border-slate-200 bg-slate-50/80 px-2 py-1 text-sm font-semibold text-slate-600 shadow-sm md:flex">
                    <a href="{{ route('home') }}" class="rounded-full px-3 py-2 transition {{ request()->routeIs('home') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Home</a>
                    <a href="{{ route('categories.index') }}" class="rounded-full px-3 py-2 transition {{ request()->routeIs('categories.*') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Categories</a>
                    <a href="{{ route('events.index') }}" class="rounded-full px-3 py-2 transition {{ request()->routeIs('events.*') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Events</a>
                    <a href="{{ route('offers.index') }}" class="rounded-full px-3 py-2 transition {{ request()->routeIs('offers.*') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Offers</a>
                </div>
                <form action="{{ route('categories.index') }}" method="get" class="ml-auto hidden w-64 items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm lg:flex">
                    <span aria-hidden="true">🔍</span>
                    <input class="w-full border-none bg-transparent outline-none placeholder:text-slate-400" type="search" name="q" placeholder="Search categories..." value="{{ request('q') }}">
                </form>
                <div class="flex items-center gap-2">
                    @auth
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button class="rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700" type="submit">Logout</button>
                        </form>
                    @else
                        <a class="rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700" href="{{ route('login') }}">Log in</a>
                        <a class="rounded-full bg-gradient-to-r from-indigo-500 to-pink-500 px-3 py-2 text-sm font-semibold text-white shadow-md transition hover:shadow-lg" href="{{ route('register') }}">Sign up</a>
                    @endauth
                </div>
            </div>
        </nav>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-slate-900 text-slate-100">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-10 lg:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-xl font-bold">{{ $site['siteTitle'] }}</h3>
                    <p class="text-slate-400">{{ $site['tagline'] }}</p>
                </div>
                <div class="text-sm text-slate-400 space-y-1">
                    <div>Contact: {{ $site['contactEmail'] }}</div>
                    <div>© {{ now()->year }} {{ $site['siteTitle'] }}. All rights reserved.</div>
                </div>
            </div>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
