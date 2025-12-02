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
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-4 lg:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-black text-slate-900">
                    <span class="text-xl">🌍</span>
                    <span class="tracking-tight">{{ strtoupper($site['siteTitle']) }}</span>
                </a>
                <div
                    class="hidden items-center gap-2 rounded-full border border-slate-200 bg-slate-50/80 px-2 py-1 text-sm font-semibold text-slate-600 shadow-sm md:flex">
                    <a href="{{ route('home') }}"
                        class="rounded-full px-3 py-2 transition {{ request()->routeIs('home') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Home</a>
                    <a href="{{ route('categories.index') }}"
                        class="rounded-full px-3 py-2 transition {{ request()->routeIs('categories.*') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Categories</a>
                    <a href="{{ route('events.index') }}"
                        class="rounded-full px-3 py-2 transition {{ request()->routeIs('events.*') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Events</a>
                    <a href="{{ route('offers.index') }}"
                        class="rounded-full px-3 py-2 transition {{ request()->routeIs('offers.*') ? 'bg-indigo-50 text-indigo-600' : 'hover:bg-white' }}">Offers</a>
                </div>
                <div class="relative ml-auto hidden lg:block">
                    <div
                        class="flex w-72 items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm">
                        <span aria-hidden="true">🔍</span>
                        <input id="global-search-input"
                            class="w-full border-none bg-transparent outline-none placeholder:text-slate-400"
                            type="search" name="q" placeholder="Search categories, events, or offers...">
                    </div>
                    <div id="global-search-results"
                        class="absolute right-0 mt-2 hidden w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
                        <div class="flex flex-col divide-y divide-slate-100 text-sm text-slate-800" id="global-search-results-body"></div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @auth
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button
                                class="rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700"
                                type="submit">Logout</button>
                        </form>
                    @else
                        <a class="rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700"
                            href="{{ route('login') }}">Log in</a>
                        <a class="rounded-full bg-gradient-to-r from-indigo-500 to-pink-500 px-3 py-2 text-sm font-semibold text-white shadow-md transition hover:shadow-lg"
                            href="{{ route('register') }}">Sign up</a>
                    @endauth
                </div>
            </div>
        </nav>

        <main class="flex-1">
            @yield('content')
        </main>

    <footer class="border-t border-slate-200 bg-slate-900 text-slate-100">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-10 lg:px-6 lg:flex-row lg:items-center lg:justify-between">
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
<script>
    (() => {
        const input = document.getElementById('global-search-input');
        const dropdown = document.getElementById('global-search-results');
        const body = document.getElementById('global-search-results-body');
        if (!input || !dropdown || !body) return;

        let timer;
        let controller;
        const debounce = (fn, delay = 250) => (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };

        function renderSection(title, items) {
            if (!items.length) return '';
            const cards = items.map(item => `
                <button data-href="${item.url}" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-slate-50">
                    <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                        ${item.image ? `<img src="${item.image}" alt="${item.label}" class="h-full w-full object-cover">` : '🔍'}
                    </span>
                    <span class="flex-1">
                        <span class="block text-sm font-semibold text-slate-900">${item.label}</span>
                        ${item.meta ? `<span class="text-xs text-slate-500">${item.meta}</span>` : ''}
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-wide text-indigo-600">${title}</span>
                </button>
            `).join('');
            return `<div class="py-1"><div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">${title}</div>${cards}</div>`;
        }

        function renderResults(payload) {
            const sections = [
                renderSection('Categories', payload.categories || []),
                renderSection('Events', payload.events || []),
                renderSection('Offers', payload.offers || []),
            ].filter(Boolean).join('');
            dropdown.classList.remove('hidden');
            body.innerHTML = sections || '<div class="p-3 text-sm text-slate-500">No matches found.</div>';
        }

        const setLoading = () => {
            dropdown.classList.remove('hidden');
            body.innerHTML = '<div class="flex items-center gap-2 p-3 text-sm text-slate-500"><span class="h-2 w-2 animate-ping rounded-full bg-indigo-500"></span>Searching…</div>';
        };

        const search = debounce(async (term) => {
            if (!term.trim()) {
                dropdown.classList.add('hidden');
                return;
            }
            try {
                controller?.abort();
                controller = new AbortController();
                setLoading();
                const res = await fetch(`{{ route('search') }}?q=${encodeURIComponent(term)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal,
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                renderResults(data);
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.error(err);
                body.innerHTML = '<div class="p-3 text-sm text-rose-600">Search unavailable. Try again.</div>';
                dropdown.classList.remove('hidden');
            }
        });

        input.addEventListener('input', (e) => search(e.target.value));
        body.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-href]');
            if (!btn) return;
            window.location.href = btn.getAttribute('data-href');
        });
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== input) {
                dropdown.classList.add('hidden');
            }
        });
        input.addEventListener('focus', () => {
            if (body.innerHTML.trim()) dropdown.classList.remove('hidden');
        });
    })();
</script>
    @stack('scripts')
</body>

</html>
