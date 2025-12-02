@extends('layouts.frontend')

@section('content')
    <section class="bg-gradient-to-br from-indigo-50 via-white to-rose-50">
        <div class="mx-auto max-w-5xl px-4 py-16 lg:px-6">
            <span
                class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-indigo-100">🎉
                events</span>
            <h1 class="mt-4 text-3xl font-black text-slate-900 sm:text-4xl">Upcoming events</h1>
            <p class="mt-3 text-lg text-slate-600">Find concerts, pop-ups, launches, and curated happenings across the city.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($events as $event)
                <a href="{{ route('events.show', $event) }}"
                    class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex items-center gap-2 px-4 pt-4 text-xs font-semibold text-indigo-700">
                        <span class="rounded-full bg-indigo-50 px-3 py-1">{{ $event->organization_name ?? 'Partner' }}</span>
                    </div>
                    <div class="space-y-2 px-4 py-3">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $event->name }}</h3>
                        <p class="text-sm text-slate-600">{{ $event->description }}</p>
                        <div class="text-sm text-slate-500 space-y-1">
                            <div>📍 {{ $event->location ?? 'TBA' }}</div>
                            <div>🗓️ {{ optional($event->starting_date)->format('M d, Y') }}</div>
                        </div>
                    </div>
                    @if ($event->display_banner)
                        <img class="h-40 w-full object-cover" src="{{ $event->display_banner }}" alt="{{ $event->name }}">
                    @endif
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
@endsection
