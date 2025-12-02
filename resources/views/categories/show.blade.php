@extends('layouts.frontend')

@section('content')
    <section class="bg-gradient-to-br from-indigo-50 via-white to-rose-50">
        <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-8 px-4 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:px-6">
            <div class="space-y-4">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-indigo-100">{{ $category->icon ?? '🗂️' }} Category</span>
                <h1 class="text-3xl font-black text-slate-900 sm:text-4xl">{{ $category->name }}</h1>
                <p class="text-lg text-slate-600">{{ $category->description ?? 'Experience the best picks from this curated section.' }}</p>
                <div class="text-sm text-slate-500">Updated {{ optional($category->updated_at)->diffForHumans() }}</div>
            </div>
            @if($category->display_image)
                <div class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-100">
                    <img class="h-full w-full object-cover" src="{{ $category->display_image }}" alt="{{ $category->name }}">
                </div>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-12 lg:px-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-slate-900">Events in {{ $category->short_name ?? $category->name }}</h2>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($events as $event)
                <div class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex items-center gap-2 px-4 pt-4 text-xs font-semibold text-indigo-700">
                        <span class="rounded-full bg-indigo-50 px-3 py-1">🎉 Event</span>
                    </div>
                    <div class="space-y-2 px-4 py-3">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $event->name }}</h3>
                        <p class="text-sm text-slate-600">{{ $event->description }}</p>
                        <div class="text-sm text-slate-500 space-y-1">
                            <div>📍 {{ $event->location ?? 'TBA' }}</div>
                            <div>🗓️ {{ optional($event->starting_date)->format('M d, Y') }}</div>
                        </div>
                    </div>
                    @if($event->display_banner)
                        <img class="h-40 w-full object-cover" src="{{ $event->display_banner }}" alt="{{ $event->name }}">
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">No events have been published for this category yet.</div>
            @endforelse
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-12 lg:px-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-slate-900">Offers in {{ $category->short_name ?? $category->name }}</h2>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($offers as $offer)
                <div class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex items-center gap-2 px-4 pt-4 text-xs font-semibold text-indigo-700">
                        <span class="rounded-full bg-indigo-50 px-3 py-1">🎁 Offer</span>
                    </div>
                    <div class="space-y-2 px-4 py-3">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $offer->name }}</h3>
                        <p class="text-sm text-slate-600">{{ $offer->details }}</p>
                        <div class="text-sm text-slate-500 space-y-1">
                            <div>{{ strtoupper($offer->discount_type) }} • {{ $offer->discount_value }}</div>
                            <div>Valid from {{ optional($offer->start_date)->format('M d') }} to {{ optional($offer->end_date)->format('M d, Y') }}</div>
                        </div>
                    </div>
                    @if($offer->display_image)
                        <img class="h-40 w-full object-cover" src="{{ $offer->display_image }}" alt="{{ $offer->name }}">
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">No offers listed for this category right now.</div>
            @endforelse
        </div>
    </div>
@endsection
