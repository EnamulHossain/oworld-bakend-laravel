@extends('layouts.frontend')

@section('content')
    <div class="bg-gradient-to-b from-slate-900 via-slate-900 to-white">
        <div class="mx-auto max-w-6xl px-4 pb-16 pt-10 lg:px-6">
            <div class="overflow-hidden rounded-3xl border border-slate-800/40 bg-slate-900/70 shadow-2xl ring-1 ring-white/5">
                <div class="relative h-72 w-full overflow-hidden sm:h-96">
                    @php
                        $banner = $event->display_banner ?? $event->banner ?? null;
                        $org = $organization?->organization_name ?? 'Organizer';
                        $initials = strtoupper(\Illuminate\Support\Str::substr($org, 0, 2));
                    @endphp
                    @if($banner)
                        <img src="{{ $banner }}" alt="{{ $event->name }}" class="absolute inset-0 h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-slate-800 text-4xl text-white">🎉</div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-900/20 via-slate-900/70 to-slate-950"></div>
                    <div class="absolute inset-x-0 bottom-0 flex flex-col gap-3 p-6 text-white sm:p-10">
                        <div class="inline-flex items-center gap-2 self-start rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white">{{ $initials }}</span>
                            <span class="rounded-full bg-indigo-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide">Event</span>
                        </div>
                        <h1 class="text-3xl font-black sm:text-4xl">{{ $event->name }}</h1>
                        <p class="max-w-3xl text-sm text-slate-200 sm:text-base">{{ $event->description }}</p>
                        <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-amber-100">
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 ring-1 ring-white/20">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                {{ optional($event->starting_date)->format('M d, Y') }} — {{ optional($event->end_date)->format('M d, Y') }}
                            </span>
                            @if($event->location)
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 ring-1 ring-white/20">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21c4-3 6-5.686 6-8.571A6 6 0 1 0 6 12.429C6 15.314 8 18 12 21z"/><circle cx="12" cy="12" r="2.5" /></svg>
                                    {{ $event->location }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 border-t border-slate-800/50 bg-white/90 p-6 sm:grid-cols-3 sm:p-10">
                    <div class="sm:col-span-2 space-y-4">
                        <div class="space-y-2">
                            <h2 class="text-lg font-semibold text-slate-900">About this event</h2>
                            <p class="text-sm leading-6 text-slate-700">{{ $event->description }}</p>
                        </div>
                        @if($event->location)
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <h3 class="text-sm font-bold text-slate-900">Location</h3>
                                <p class="mt-2 text-sm text-slate-700">{{ $event->location }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-bold text-slate-900">Organizer</h3>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-600 text-base font-bold text-white">{{ $initials }}</div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $org }}</div>
                                    <div class="text-xs text-slate-500">{{ $organization?->email ?? '' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-bold text-slate-900">Event details</h3>
                            <dl class="mt-3 space-y-2 text-sm text-slate-700">
                                <div class="flex items-center justify-between">
                                    <dt>Starts</dt>
                                    <dd>{{ optional($event->starting_date)->format('M d, Y') ?? 'TBA' }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Ends</dt>
                                    <dd>{{ optional($event->end_date)->format('M d, Y') ?? 'TBA' }}</dd>
                                </div>
                                @if($event->location)
                                <div class="flex items-center justify-between">
                                    <dt>Location</dt>
                                    <dd class="text-right">{{ $event->location }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                        <a href="{{ route('events.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow hover:bg-indigo-700">
                            Back to events
                        </a>
                    </div>
                </div>
            </div>

            @if(!empty($offers) && count($offers))
            <div class="mt-8 rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Offers</p>
                        <h2 class="text-xl font-bold text-slate-900">Exclusive for this event</h2>
                    </div>
                    <a href="{{ route('offers.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">View all offers</a>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($offers as $offer)
                        @php
                            $org = $offer->organization_name ?? 'Partner';
                            $initials = strtoupper(\Illuminate\Support\Str::substr($org, 0, 2));
                            $discountType = $offer->discount_type ? strtoupper($offer->discount_type) : 'OFFER';
                            $discountValue = $offer->discount_value ?? '';
                            $discountText = $discountValue !== '' ? $discountType . ' • ' . $discountValue : $discountType;
                            $expires = optional($offer->end_date)->format('M d, Y') ?: 'Limited';
                        @endphp
                        <a href="{{ route('offers.show', $offer) }}" class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            <div class="relative">
                                @if($offer->display_image)
                                    <img class="h-44 w-full object-cover transition duration-300 group-hover:scale-105" src="{{ $offer->display_image }}" alt="{{ $offer->name }}">
                                @else
                                    <div class="flex h-44 w-full items-center justify-center bg-slate-100 text-3xl">🎁</div>
                                @endif
                                <div class="absolute left-3 top-3 inline-flex items-center gap-2 rounded-full bg-white/90 px-2 py-1 text-xs font-semibold text-slate-800 shadow">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white">{{ $initials }}</span>
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col justify-between gap-3 p-4">
                                <div class="space-y-1">
                                    <h3 class="text-base font-semibold text-slate-900">{{ $offer->name }}</h3>
                                    <p class="text-sm text-slate-600">{{ $offer->details }}</p>
                                </div>
                                <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-amber-800 ring-1 ring-amber-100">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                        {{ $expires }}
                                    </span>
                                    <span class="text-indigo-700">{{ $discountText }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 7a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0-1.414 1.414M7.05 16.95 5.636 18.364"/></svg>
                                        {{ $offer->organization_name ?? 'Partner' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-indigo-700">
                                        View
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7"/></svg>
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection
