@extends('layouts.frontend')

@section('content')
    @php
        $heroSlides = ($specialOffers ?? collect())->take(6);
        $heroFallback = optional($offers->first())->display_image;
    @endphp

    <section class="relative overflow-hidden bg-slate-900">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900/70 via-slate-900/40 to-white"></div>
        <div class="relative mx-auto max-w-7xl px-0 pb-14 pt-10 sm:px-4 lg:px-6">
            <div
                class="flex flex-col gap-6 overflow-hidden rounded-3xl border border-slate-800/30 bg-slate-900/60 shadow-2xl ring-1 ring-white/5">
                <div class="flex items-center justify-between px-4 pt-4 sm:px-8">
                    <div
                        class="flex items-center gap-3 rounded-full bg-white/90 px-3 py-1 text-sm font-semibold text-slate-900 shadow ring-1 ring-white/60">
                        <span
                            class="h-9 w-9 rounded-full bg-indigo-600 text-white inline-flex items-center justify-center font-bold">OW</span>
                        <span>Offers</span>
                    </div>
                    <div
                        class="flex items-center gap-2 rounded-full bg-white/85 px-3 py-1 text-slate-700 shadow ring-1 ring-white/70">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z" />
                        </svg>
                        <span class="text-sm font-semibold">Search offers</span>
                    </div>
                </div>

                <div class="relative">
                    <div id="hero-slider" class="relative h-[360px] w-full overflow-hidden sm:h-[420px]">
                        @forelse($heroSlides as $idx => $slide)
                            @php
                                $slideImg =
                                    $slide->display_image ?? ($slide->cover ?? ($slide->images[0] ?? $heroFallback));
                                $org = $slide->organization_name ?? 'Partner';
                                $initials = strtoupper(\Illuminate\Support\Str::substr($org, 0, 2));
                            @endphp
                            <div class="hero-slide absolute inset-0 flex h-full w-full items-end transition-opacity duration-700 {{ $idx === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0' }}"
                                data-hero-slide>
                                @if ($slideImg)
                                    <img src="{{ $slideImg }}" alt="{{ $slide->name }}"
                                        class="absolute inset-0 h-full w-full object-cover">
                                @endif
                                <div
                                    class="absolute inset-0 bg-gradient-to-b from-slate-900/40 via-slate-900/60 to-slate-950">
                                </div>
                                <div class="relative flex w-full flex-col gap-3 p-6 text-white sm:p-10">
                                    <div
                                        class="inline-flex items-center gap-2 self-start rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                                        <span
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white">{{ $initials }}</span>
                                        <span
                                            class="rounded-full bg-indigo-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide">Exclusive</span>
                                    </div>
                                    <h2 class="text-2xl font-black sm:text-3xl">{{ $slide->name }}</h2>
                                    <p class="max-w-2xl text-sm text-slate-200 sm:text-base">
                                        {{ \Illuminate\Support\Str::limit($slide->details, 140) }}</p>
                                    <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-amber-100">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 ring-1 ring-white/20">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                                    d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                            </svg>
                                            {{ optional($slide->end_date)->format('M d, Y') ?: 'Limited' }}
                                        </span>
                                        <span class="rounded-full bg-indigo-500/80 px-3 py-1 ring-1 ring-indigo-300/50">
                                            {{ strtoupper($slide->discount_type ?? 'Offer') }}
                                            {{ $slide->discount_value ? '• ' . $slide->discount_value : '' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div
                                class="hero-slide absolute inset-0 flex h-full w-full items-center justify-center bg-slate-800 text-white">
                                <div class="text-center">
                                    <h2 class="text-2xl font-bold">Active offers</h2>
                                    <p class="text-slate-200">Claim member-only deals, bundle offers, and limited time
                                        discounts.</p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    @if ($heroSlides->count() > 1)
                        <div class="pointer-events-none absolute inset-x-0 bottom-4 flex justify-center gap-2">
                            @foreach ($heroSlides as $dotIndex => $slide)
                                <button class="hero-dot h-2 w-2 rounded-full bg-white/50 transition hover:bg-white/80"
                                    data-hero-dot="{{ $dotIndex }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="-mt-10 mx-auto max-w-7xl px-4 pb-12 lg:px-6">
        <div class="rounded-3xl bg-white/90 p-4 shadow-lg ring-1 ring-slate-200 backdrop-blur">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-lg font-bold text-slate-900">Offers</div>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <button
                        class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-100 hover:bg-indigo-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 7h16M4 12h10M4 17h6" />
                        </svg>
                        Filter
                    </button>
                    <button
                        class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 5h18M6 12h12M10 19h4" />
                        </svg>
                        Sort by
                    </button>
                </div>
                <div class="flex w-full flex-wrap gap-2">
                    <span
                        class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800 ring-1 ring-indigo-200">All</span>
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">Nearby</span>
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">Food</span>
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">Entertainment</span>
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">Retail</span>
                </div>
            </div>
        </div>

        <div class="mt-6 text-sm font-bold uppercase tracking-wide text-indigo-700">O'World Exclusive</div>

        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($offers as $offer)
                @php
                    $org = $offer->organization_name ?? 'Partner';
                    $initials = strtoupper(\Illuminate\Support\Str::substr($org, 0, 2));
                    $discountType = $offer->discount_type ? strtoupper($offer->discount_type) : 'OFFER';
                    $discountValue = $offer->discount_value ?? '';
                    $discountText = $discountValue !== '' ? $discountType . ' • ' . $discountValue : $discountType;
                    $expires = optional($offer->end_date)->format('M d, Y') ?: 'Limited';
                @endphp
                <a href="{{ route('offers.show', $offer) }}"
                    class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="relative">
                        @if ($offer->display_image)
                            <img class="h-44 w-full object-cover transition duration-300 group-hover:scale-105"
                                src="{{ $offer->display_image }}" alt="{{ $offer->name }}">
                        @else
                            <div class="flex h-44 w-full items-center justify-center bg-slate-100 text-3xl">🎁</div>
                        @endif
                        <div
                            class="absolute left-3 top-3 inline-flex items-center gap-2 rounded-full bg-white/90 px-2 py-1 text-xs font-semibold text-slate-800 shadow">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white">{{ $initials }}</span>
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        </div>
                        <div
                            class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                    d="M12 21s-6-4.35-9-9a5.25 5.25 0 0 1 9-4.5A5.25 5.25 0 0 1 21 12c-3 4.65-9 9-9 9z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col justify-between gap-3 p-4">
                        <div class="space-y-1">
                            <h3 class="text-base font-semibold text-slate-900">{{ $offer->name }}</h3>
                            <p class="text-sm text-slate-600">{{ $offer->details }}</p>
                        </div>
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-amber-800 ring-1 ring-amber-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                        d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                                {{ $expires }}
                            </span>
                            <span class="text-indigo-700">{{ $discountText }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                        d="M12 7a4 4 0 1 1 0 8 4 4 0 0 1 0-8z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                        d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0-1.414 1.414M7.05 16.95 5.636 18.364" />
                                </svg>
                                {{ $offer->organization_name ?? 'Partner' }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-indigo-700">
                                View
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="m9 5 7 7-7 7" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">No
                    active offers at the moment.</div>
            @endforelse
        </div>

        <div class="mt-8 flex items-center justify-center">
            {{ $offers->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const slides = Array.from(document.querySelectorAll('[data-hero-slide]'));
            const dots = Array.from(document.querySelectorAll('[data-hero-dot]'));
            if (!slides.length) return;

            let idx = 0;
            let timer;
            const interval = 5000;

            function show(next) {
                slides.forEach((slide, i) => {
                    const active = i === next;
                    slide.style.opacity = active ? '1' : '0';
                    slide.style.zIndex = active ? '10' : '0';
                });
                dots.forEach((dot, i) => {
                    dot.style.opacity = i === next ? '1' : '0.5';
                    dot.style.transform = i === next ? 'scale(1.2)' : 'scale(1)';
                });
                idx = next;
            }

            function tick() {
                show((idx + 1) % slides.length);
            }

            function start() {
                stop();
                timer = setInterval(tick, interval);
            }

            function stop() {
                if (timer) clearInterval(timer);
                timer = null;
            }

            dots.forEach((dot, i) => {
                dot.addEventListener('click', () => {
                    show(i);
                    start();
                });
            });

            show(0);
            start();
            document.getElementById('hero-slider')?.addEventListener('mouseenter', stop);
            document.getElementById('hero-slider')?.addEventListener('mouseleave', start);
        })();
    </script>
@endpush
