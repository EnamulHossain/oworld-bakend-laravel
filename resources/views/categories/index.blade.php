@extends('layouts.frontend')

@section('content')
    <section class="bg-gradient-to-br from-indigo-50 via-white to-rose-50">
        <div class="mx-auto max-w-5xl px-4 py-16 lg:px-6">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-indigo-100">✨ curated spaces</span>
            <h1 class="mt-4 text-3xl font-black text-slate-900 sm:text-4xl">Browse by category</h1>
            <p class="mt-3 text-lg text-slate-600">Discover new places, experiences, and offers — categorized for every mood and occasion.</p>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-12 lg:px-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($categories as $category)
                <a class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg" href="{{ route('categories.show', $category) }}">
                    @if($category->display_image)
                        <img class="h-44 w-full object-cover transition duration-300 group-hover:scale-105" src="{{ $category->display_image }}" alt="{{ $category->name }}">
                    @endif
                    <div class="space-y-2 p-4">
                        <div class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $category->icon ?? '🗂️' }} {{ $category->short_name ?? $category->name }}</div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $category->name }}</h3>
                        <p class="text-sm text-slate-600">{{ $category->description ?? 'Experience the best picks from this curated section.' }}</p>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">No categories available.</div>
            @endforelse
        </div>
    </div>
@endsection
