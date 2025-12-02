@extends('layouts.frontend')

@section('content')
    <section class="relative overflow-hidden bg-slate-900">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600 via-slate-900 to-rose-500 opacity-70"></div>
        <div class="absolute -left-12 top-12 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute bottom-12 right-10 h-40 w-40 rounded-full bg-indigo-300/30 blur-3xl"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-14 lg:px-6">
            <div class="grid gap-8 md:grid-cols-3">
                <div class="md:col-span-2 space-y-3 text-white">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">✨ Curated spaces</span>
                    <h1 class="text-3xl font-black sm:text-4xl">Browse by category</h1>
                    <p class="text-sm text-white/80 sm:text-base">Discover experiences and offers for every mood and occasion.</p>
                    <div class="flex flex-wrap gap-2 pt-2 text-xs font-semibold text-white/80">
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">Food & Drink</span>
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">Entertainment</span>
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">Lifestyle</span>
                    </div>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/10 p-4 text-white shadow-2xl backdrop-blur">
                    <p class="text-sm font-semibold uppercase tracking-wide text-white/70">Highlights</p>
                    <div class="mt-3 flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
                        @foreach($categories->take(6) as $category)
                            <a href="{{ route('categories.show', $category) }}" class="flex w-36 flex-none flex-col gap-2 rounded-2xl bg-white/10 p-3 text-xs font-semibold transition hover:-translate-y-1 hover:bg-white/20">
                                @if($category->display_image)
                                    <img src="{{ $category->display_image }}" alt="{{ $category->name }}" class="h-20 w-full rounded-xl object-cover">
                                @else
                                    <div class="flex h-20 items-center justify-center rounded-xl bg-white/10 text-lg">🗂️</div>
                                @endif
                                <span class="line-clamp-2">{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="-mt-10 mx-auto max-w-7xl px-4 pb-12 lg:px-6">
        <div class="rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-lg ring-1 ring-white/60">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-lg font-bold text-slate-900">All categories</div>
                <div class="ml-auto flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-700">
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-indigo-700 ring-1 ring-indigo-100">Top picks</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 ring-1 ring-slate-200">New</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 ring-1 ring-slate-200">Popular</span>
                </div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($categories as $category)
                    <a class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                        href="{{ route('categories.show', $category) }}">
                        <div class="relative h-40 overflow-hidden bg-slate-100">
                            @if ($category->display_image)
                                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    src="{{ $category->display_image }}" alt="{{ $category->name }}">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-3xl">🗂️</div>
                            @endif
                            <span class="absolute left-3 top-3 inline-flex items-center gap-2 rounded-full bg-white/90 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-900 shadow">
                                {{ $category->icon ?? '🗂️' }} {{ $category->short_name ?? $category->name }}
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col justify-between space-y-2 p-4">
                            <div class="space-y-1">
                                <h3 class="text-lg font-semibold text-slate-900">{{ $category->name }}</h3>
                                <p class="text-sm text-slate-600">{{ $category->description ?? 'Experience the best picks from this curated section.' }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600">
                                Explore
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7"/></svg>
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">No categories available.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
