@extends('layouts.frontend')

@section('content')
    <div class="bg-gradient-to-b from-slate-900 via-slate-900 to-white">
        <div class="mx-auto max-w-7xl px-4 pb-16 pt-10 lg:px-6">
            <div
                class="overflow-hidden rounded-3xl border border-slate-800/40 bg-slate-900/70 shadow-2xl ring-1 ring-white/5">
                <div class="relative h-72 w-full overflow-hidden sm:h-96">
                    @php
                        $gallery = collect($offer->images ?? [])
                            ->filter()
                            ->all();
                        $hero = $offer->display_image ?? ($offer->cover ?? ($gallery[0] ?? null));
                        $org = $organization?->organization_name ?? 'Partner';
                        $initials = strtoupper(\Illuminate\Support\Str::substr($org, 0, 2));
                    @endphp
                    @if ($hero)
                        <img src="{{ $hero }}" alt="{{ $offer->name }}"
                            class="absolute inset-0 h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-slate-800 text-4xl text-white">🎁</div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-900/20 via-slate-900/70 to-slate-950"></div>
                    <div class="absolute inset-x-0 bottom-0 flex flex-col gap-3 p-6 text-white sm:p-10">
                        <div
                            class="inline-flex items-center gap-2 self-start rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white">{{ $initials }}</span>
                        </div>
                        <h1 class="text-3xl font-black sm:text-4xl">{{ $offer->name }}</h1>
                        <p class="max-w-3xl text-sm text-slate-200 sm:text-base">{{ $offer->details }}</p>
                        <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-amber-100">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 ring-1 ring-white/20">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                        d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                                Valid {{ optional($offer->start_date)->format('M d, Y') ?? 'Now' }} —
                                {{ optional($offer->end_date)->format('M d, Y') ?? 'Limited' }}
                            </span>
                            <span class="rounded-full bg-indigo-500/80 px-3 py-1 ring-1 ring-indigo-300/50">
                                {{ strtoupper($offer->discount_type ?? 'OFFER') }}
                                {{ $offer->discount_value ? '• ' . $offer->discount_value : '' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 border-t border-slate-800/50 bg-white/90 p-6 sm:grid-cols-3 sm:p-10">
                    <div class="sm:col-span-2 space-y-4">
                        @if (count($gallery) > 1)
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($gallery as $image)
                                    <img src="{{ $image }}" alt="{{ $offer->name }}"
                                        class="h-28 w-full rounded-xl object-cover sm:h-32">
                                @endforeach
                            </div>
                        @endif
                        <div class="space-y-2">
                            <h2 class="text-lg font-semibold text-slate-900">About this offer</h2>
                            <p class="text-sm leading-6 text-slate-700">{{ $offer->details }}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-bold text-slate-900">Organization</h3>
                            <div class="mt-3 flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-600 text-base font-bold text-white">
                                    {{ $initials }}</div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $org }}</div>
                                    <div class="text-xs text-slate-500">{{ $organization?->email ?? '' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-bold text-slate-900">Offer summary</h3>
                            <dl class="mt-3 space-y-2 text-sm text-slate-700">
                                <div class="flex items-center justify-between">
                                    <dt>Type</dt>
                                    <dd class="font-semibold text-indigo-700">
                                        {{ strtoupper($offer->discount_type ?? 'OFFER') }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Value</dt>
                                    <dd class="font-semibold">{{ $offer->discount_value ?? 'N/A' }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Valid from</dt>
                                    <dd>{{ optional($offer->start_date)->format('M d, Y') ?? 'Now' }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Valid until</dt>
                                    <dd>{{ optional($offer->end_date)->format('M d, Y') ?? 'Limited' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <a href="{{ route('offers.index') }}"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow hover:bg-indigo-700">
                            Back to offers
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
