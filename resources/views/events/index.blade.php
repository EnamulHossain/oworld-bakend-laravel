@extends('layouts.frontend')

@section('content')
    @php
        $heroImage = optional($events->first())->display_banner;
    @endphp
    <section class="relative overflow-hidden bg-slate-900">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/60 to-slate-900/20"></div>
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="Events hero" class="absolute inset-0 h-full w-full object-cover opacity-50">
        @endif
        <div class="relative mx-auto max-w-7xl px-4 py-14 lg:px-6">
            <div class="grid gap-6 md:grid-cols-3">
                <div class="md:col-span-2 space-y-3 text-white">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">🎉 Events</span>
                    <h1 class="text-3xl font-black sm:text-4xl">Upcoming events</h1>
                    <p class="text-sm text-white/80 sm:text-base">Concerts, pop-ups, launches, and curated happenings across the city.</p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/10 p-4 text-white shadow-2xl backdrop-blur">
                    <p class="text-sm font-semibold uppercase tracking-wide text-white/70">Filters</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-white/15 px-3 py-1 ring-1 ring-white/20">Upcoming</span>
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">Popular</span>
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">Near you</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="-mt-10 mx-auto max-w-7xl px-4 pb-12 lg:px-6">
        <div class="rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-lg ring-1 ring-white/60">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Events</p>
                    <h2 class="text-xl font-bold text-slate-900">City highlights</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-700">
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-indigo-700 ring-1 ring-indigo-100">This week</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 ring-1 ring-slate-200">This month</span>
                </div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($events as $event)
                    @php
                        $start = optional($event->starting_date)->format('M d');
                        $end = optional($event->end_date)->format('M d');
                    @endphp
                    <a href="{{ route('events.show', $event) }}"
                        class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="relative h-44 overflow-hidden bg-slate-100">
                            @if ($event->display_banner)
                                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    src="{{ $event->display_banner }}" alt="{{ $event->name }}">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-3xl">🎉</div>
                            @endif
                            <div class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-900 shadow">
                                {{ $event->organization_name ?? 'Partner' }}
                            </div>
                            <div class="absolute bottom-3 left-3 inline-flex items-center gap-2 rounded-full bg-indigo-600/90 px-3 py-1 text-xs font-semibold text-white shadow">
                                <span>📍 {{ $event->location ?? 'TBA' }}</span>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col justify-between space-y-3 p-4">
                            <div class="space-y-1">
                                <h3 class="text-lg font-semibold text-slate-900">{{ $event->name }}</h3>
                                <p class="text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($event->description, 100) }}</p>
                            </div>
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-amber-800 ring-1 ring-amber-100">
                                    🗓️ {{ $start }}{{ $end ? ' - '.$end : '' }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-indigo-700">
                                    View
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">No
                        events published yet.</div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $events->links() }}
            </div>
        </div>
    </div>
@endsection
