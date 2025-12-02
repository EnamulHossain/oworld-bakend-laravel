@extends('layouts.frontend')

@section('content')

    <section class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Fresh drops</p>
                <h2 class="text-2xl font-bold text-slate-900">Offers</h2>
            </div>
            <a class="text-sm font-semibold text-indigo-600 hover:text-indigo-700" href="{{ route('offers.index') }}">Browse
                all</a>
        </div>
        <div class="mt-6">
            <div class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 scrollbar-hide" id="offers-reels"
                data-auto-track>
                @forelse($offers as $offer)
                    @php
                        $gallery = collect($offer->images ?? ($offer->gallery_images ?? ($offer->gallery ?? [])))
                            ->filter()
                            ->values()
                            ->all();
                        $coverImage = $offer->thumbnail ?? ($offer->cover ?? ($offer->display_image ?? ''));
                        if (empty($gallery) && !empty($coverImage)) {
                            $gallery = [$coverImage];
                        }
                        $thumb =
                            $offer->thumbnail ?? ($offer->cover ?? ($offer->display_image ?? ($gallery[0] ?? null)));
                    @endphp
                    <article
                        class="group relative w-52 flex-none snap-start overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg cursor-pointer"
                        data-reel data-title="{{ $offer->name }}"
                        data-desc="{{ \Illuminate\Support\Str::limit($offer->details ?? 'Exclusive experience crafted for our community.', 120) }}"
                        data-images="{{ htmlspecialchars(json_encode($gallery), ENT_QUOTES, 'UTF-8') }}"
                        data-cover="{{ $coverImage }}" data-tag="{{ $offer->offer_type ?? 'Offer' }}">
                        <div class="relative h-64 overflow-hidden bg-slate-900">
                            @if (!empty($thumb))
                                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    src="{{ $thumb }}" alt="{{ $offer->name }}">
                            @else
                                <div class="flex h-full items-center justify-center bg-slate-800 text-4xl text-white">🎁
                                </div>
                            @endif
                            <span
                                class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-1 text-[11px] font-semibold text-slate-900 shadow">{{ $offer->offer_type ?? 'Offer' }}</span>
                        </div>
                    </article>
                @empty
                    <div
                        class="w-full rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">
                        No offers yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    @if (!empty($homeSlider))
        <section class="mx-auto max-w-7xl px-4 pb-12 lg:px-6">
            <div class="mb-4 rounded-3xl  bg-white/90 shadow-sm">
                <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-slate-900/80">
                    <div id="home-hero-slider" class="relative h-[280px] w-full sm:h-[360px]">
                        @foreach ($homeSlider as $slide)
                            @php
                                $img = $slide['image'] ?? '';
                                $title = $slide['title'] ?? 'Featured';
                                $subtitle = $slide['subtitle'] ?? '';
                                $badge = $slide['badge'] ?? 'Featured';
                                $desc = $slide['description'] ?? '';
                                $ctaText = $slide['ctaText'] ?? '';
                                $ctaLink = $slide['ctaLink'] ?? '#';
                            @endphp
                            <article
                                class="home-slide absolute inset-0 flex h-full w-full items-center justify-center overflow-hidden transition-opacity duration-700 {{ $loop->first ? 'opacity-100 z-10' : 'opacity-0 z-0' }}"
                                data-home-slide>
                                @if (!empty($img))
                                    <img src="{{ $img }}" alt="{{ $title }}"
                                        class="absolute inset-0 h-full w-full object-cover">
                                @endif

                            </article>
                        @endforeach
                    </div>
                    @if (count($homeSlider) > 1)
                        <div class="pointer-events-none absolute inset-x-0 bottom-4 flex justify-center gap-2">
                            @foreach ($homeSlider as $dotIndex => $slide)
                                <button class="home-dot h-2 w-2 rounded-full bg-white/60 transition hover:bg-white"
                                    data-home-dot="{{ $dotIndex }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Browse by vibe</p>
                <h2 class="text-2xl font-bold text-slate-900">Categories</h2>
            </div>
            <a class="text-sm font-semibold text-indigo-600 hover:text-indigo-700"
                href="{{ route('categories.index') }}">View all</a>
        </div>
        <div class="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 scrollbar-hide" data-auto-track>
            @foreach ($categories as $category)
                <a class="group relative w-52 flex-none snap-start overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                    href="{{ route('categories.show', $category) }}">
                    @if ($category->display_image)
                        <img class="h-52 w-full object-cover transition duration-300 group-hover:scale-105"
                            src="{{ $category->display_image }}" alt="{{ $category->name }}">
                    @else
                        <div class="flex h-52 items-center justify-center bg-slate-100 text-3xl">🗂️</div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Happening soon</p>
                <h2 class="text-2xl font-bold text-slate-900">Events highlights</h2>
            </div>
            <a class="text-sm font-semibold text-indigo-600 hover:text-indigo-700" href="{{ route('events.index') }}">All
                events</a>
        </div>
        <div class="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 scrollbar-hide" data-auto-track>
            @forelse ($events as $event)
                <article
                    class="flex h-full w-52 flex-none snap-start flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    @if ($event->display_banner)
                        <img class="h-52 w-full object-cover" src="{{ $event->display_banner }}"
                            alt="{{ $event->name }}">
                    @else
                        <div class="flex h-52 w-full items-center justify-center bg-slate-100 text-3xl">🎉</div>
                    @endif

                </article>
            @empty
                <div
                    class="w-full rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">
                    No events published yet.</div>
            @endforelse
        </div>
    </section>

    @if (!empty($blockOne))
        <section class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
            <div class="mb-4">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Stories</p>
                <h2 class="text-2xl font-bold text-slate-900">Content Block 1</h2>
            </div>
            <div class="mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 scrollbar-hide" data-auto-track>
                @foreach ($blockOne as $item)
                    <article
                        class="flex h-full w-52 flex-none snap-start flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                        @if (!empty($item['image']))
                            <img class="h-52 w-full object-cover" src="{{ $item['image'] }}"
                                alt="{{ $item['title'] ?? 'Card' }}">
                        @else
                            <div class="flex h-52 w-full items-center justify-center bg-slate-100 text-2xl">📖</div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if (!empty($blockTwo))
        <section class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
            <div class="mb-4">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Highlights</p>
                <h2 class="text-2xl font-bold text-slate-900">Content Block 2</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (collect($blockTwo)->take(6) as $item)
                    <a href="#block-two-slider-{{ $loop->iteration }}"
                        class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @if (!empty($item['image']))
                            <img class="h-48 w-full object-cover transition duration-300 group-hover:scale-105"
                                src="{{ $item['image'] }}" alt="{{ $item['title'] ?? 'Card' }}">
                        @else
                            <div class="flex h-48 w-full items-center justify-center bg-slate-100 text-2xl">⭐</div>
                        @endif
                        {{-- <div class="p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600">{{ ucfirst($item['type'] ?? 'category') }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $item['title'] ?? 'Collection' }}</h3>
                        @if (!empty($item['subtitle']))
                            <p class="text-sm text-slate-600">{{ $item['subtitle'] }}</p>
                        @endif
                    </div> --}}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (!empty($blockTwoSliders))
        @foreach ($blockTwoSliders as $index => $slider)
            @php
                $blockItem = $slider['meta'] ?? [];
                $title = $blockItem['title'] ?? 'Featured Offers';
                $subtitle = $blockItem['subtitle'] ?? '';
            @endphp
            <section id="block-two-slider-{{ $index + 1 }}" class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        {{-- <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Content Block 2 – Slide {{ $index + 1 }}</p> --}}
                        {{-- <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2> --}}
                        {{-- @if (!empty($subtitle))
                            <p class="text-sm text-slate-600">{{ $subtitle }}</p>
                        @endif --}}
                    </div>0

                    {{-- <a class="text-sm font-semibold text-indigo-600 hover:text-indigo-700" href="#block-two-slider-{{ $index + 1 }}">View</a> --}}
                </div>
                <div class="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 scrollbar-hide" data-auto-track>
                    @forelse($slider['offers'] ?? [] as $offer)
                        @php
                            $gallery = collect($offer->images ?? [])
                                ->filter()
                                ->values()
                                ->all();
                            $coverImage = $offer->display_image ?? ($offer->thumbnail ?? ($offer->cover ?? ''));
                            if (empty($gallery) && !empty($coverImage)) {
                                $gallery = [$coverImage];
                            }
                            $thumb = $coverImage ?: $gallery[0] ?? null;
                        @endphp
                        <article
                            class="group relative w-52 flex-none snap-start overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg cursor-pointer"
                            data-reel data-title="{{ $offer->name }}"
                            data-desc="{{ \Illuminate\Support\Str::limit($offer->details ?? 'Exclusive experience crafted for our community.', 120) }}"
                            data-images="{{ htmlspecialchars(json_encode($gallery), ENT_QUOTES, 'UTF-8') }}"
                            data-cover="{{ $coverImage }}" data-tag="{{ $offer->offer_type ?? 'Offer' }}">
                            <div class="relative h-64 overflow-hidden bg-slate-900">
                                @if (!empty($thumb))
                                    <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                        src="{{ $thumb }}" alt="{{ $offer->name }}">
                                @else
                                    <div class="flex h-full items-center justify-center bg-slate-800 text-4xl text-white">🎁
                                    </div>
                                @endif
                                <span
                                    class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-1 text-[11px] font-semibold text-slate-900 shadow">{{ $offer->offer_type ?? 'Offer' }}</span>
                            </div>
                        </article>
                    @empty
                        <div
                            class="w-full rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-slate-600">
                            No offers for this selection yet.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    @endif

    <div id="reels-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 px-4 py-10">
        <div class="relative w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl">
            <button type="button" id="reels-close"
                class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-lg font-bold text-slate-600 shadow hover:bg-slate-200"
                aria-label="Close reel">×</button>
            <div class="p-6">
                <div class="flex items-center justify-between text-sm font-semibold text-slate-600">
                    <span id="reels-player-tag"></span>
                    <div class="flex-1 px-4">
                        <div id="reels-progress" class="flex gap-2"></div>
                    </div>
                </div>
                <h3 id="reels-player-title" class="mt-2 text-2xl font-bold text-slate-900"></h3>
                <p id="reels-player-desc" class="mt-1 text-sm text-slate-500"></p>
                <div class="relative mt-4 flex items-center justify-center">
                    <button id="reels-prev" type="button"
                        class="absolute left-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700 shadow hover:bg-slate-200">‹</button>
                    <div class="overflow-hidden rounded-2xl bg-slate-900 p-3">
                        <img id="reels-player-image" class="mx-auto h-[520px] w-[320px] rounded-xl object-cover"
                            alt="Offer reel">
                        <video id="reels-player-video" class="mx-auto h-[520px] w-[320px] rounded-xl object-cover hidden"
                            playsinline muted controls></video>
                    </div>
                    <button id="reels-next" type="button"
                        class="absolute right-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700 shadow hover:bg-slate-200">›</button>
                </div>
                <div class="mt-6 flex items-center justify-between text-sm font-semibold text-slate-700">
                    <button type="button" class="rounded-full bg-slate-100 px-4 py-2 shadow hover:bg-slate-200"
                        id="reels-prev-bottom">Previous</button>
                    <button type="button"
                        class="rounded-full bg-indigo-500 px-4 py-2 text-white shadow hover:bg-indigo-600"
                        id="reels-next-bottom">Next</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        (function() {
            const cards = Array.from(document.querySelectorAll('[data-reel]'));
            if (!cards.length) return;

            const modal = document.getElementById('reels-modal');
            const modalImg = document.getElementById('reels-player-image');
            const modalVideo = document.getElementById('reels-player-video');
            const modalTitle = document.getElementById('reels-player-title');
            const modalDesc = document.getElementById('reels-player-desc');
            const modalTag = document.getElementById('reels-player-tag');
            const btnNext = document.getElementById('reels-next');
            const btnPrev = document.getElementById('reels-prev');
            const btnNextBottom = document.getElementById('reels-next-bottom');
            const btnPrevBottom = document.getElementById('reels-prev-bottom');
            const btnClose = document.getElementById('reels-close');
            const progressBar = document.getElementById('reels-progress');
            const SLIDE_MS = 4000;
            let slideTimer;

            const reels = cards.map((card) => {
                let images = [];
                try {
                    images = JSON.parse(card.dataset.images || '[]');
                } catch (err) {
                    console.error('Failed parsing images', err);
                }
                const cover = card.dataset.cover || '';
                // include cover as first slide if present
                const normalized = [
                        ...(cover ? [cover] : []),
                        ...images,
                    ].filter((val) => typeof val === 'string' && val.trim().length)
                    .map((val) => val.trim());

                const slides = normalized.length ? normalized : [''];

                return {
                    images: slides,
                    title: card.dataset.title || 'Offer',
                    desc: card.dataset.desc || '',
                    tag: card.dataset.tag || 'Offer',
                };
            });

            let current = 0;
            let frame = 0;

            function updateModal(idx) {
                const reel = reels[idx];
                const placeholder =
                    'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width=\"900\" height=\"1600\"><rect width=\"900\" height=\"1600\" fill=\"%230f172a\"/><text x=\"50%\" y=\"50%\" fill=\"%23ffffff\" font-size=\"48\" font-family=\"Arial\" text-anchor=\"middle\">Offer</text></svg>';
                const media = reel.images[frame] || placeholder;
                const isVideo = typeof media === 'string' && (media.endsWith('.mp4') || media.endsWith('.webm') || media
                    .endsWith('.mov'));
                if (isVideo) {
                    modalVideo.classList.remove('hidden');
                    modalImg.classList.add('hidden');
                    modalVideo.src = media;
                    modalVideo.load();
                    modalVideo.play().catch(() => {});
                } else {
                    modalVideo.pause();
                    modalVideo.src = '';
                    modalVideo.classList.add('hidden');
                    modalImg.classList.remove('hidden');
                    modalImg.src = media || placeholder;
                    modalImg.alt = reel.title;
                }
                modalTitle.textContent = reel.title;
                modalDesc.textContent = reel.desc;
                modalTag.textContent = reel.tag;
                // progress
                progressBar.innerHTML = '';
                reel.images.forEach((_, i) => {
                    const bar = document.createElement('div');
                    bar.className = 'h-1 flex-1 rounded-full bg-slate-200 overflow-hidden';
                    const fill = document.createElement('div');
                    fill.className = 'h-full w-full rounded-full';
                    fill.style.background = i === frame ? 'linear-gradient(90deg,#6366f1,#ec4899)' : '#e2e8f0';
                    bar.appendChild(fill);
                    progressBar.appendChild(bar);
                });
            }

            function openModal(idx) {
                current = idx;
                frame = 0;
                updateModal(current);
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                startSlideTimer();
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
                stopSlideTimer();
                modalVideo.pause();
            }

            function go(step) {
                current = (current + step + reels.length) % reels.length;
                frame = 0;
                updateModal(current);
                startSlideTimer();
            }

            function goFrame(step) {
                const reel = reels[current];
                frame = (frame + step + reel.images.length) % reel.images.length;
                updateModal(current);
                startSlideTimer();
            }

            function startSlideTimer() {
                stopSlideTimer();
                slideTimer = setTimeout(() => {
                    const reel = reels[current];
                    if (reel.images.length <= 1) return startSlideTimer();
                    goFrame(1);
                }, SLIDE_MS);
            }

            function stopSlideTimer() {
                if (slideTimer) clearTimeout(slideTimer);
                slideTimer = null;
            }

            cards.forEach((card, idx) => {
                card.addEventListener('click', () => openModal(idx));
            });

            btnNext?.addEventListener('click', () => goFrame(1));
            btnPrev?.addEventListener('click', () => goFrame(-1));
            btnNextBottom?.addEventListener('click', () => go(1));
            btnPrevBottom?.addEventListener('click', () => go(-1));
            btnClose?.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            document.addEventListener('keydown', (e) => {
                if (modal.classList.contains('hidden')) return;
                if (e.key === 'Escape') closeModal();
                if (e.key === 'ArrowRight') goFrame(1);
                if (e.key === 'ArrowLeft') goFrame(-1);
            });

            // Auto-scroll sliders (offers, categories, events)
            const tracks = Array.from(document.querySelectorAll('[data-auto-track]'));
            tracks.forEach((track) => {
                const item = track.querySelector('[data-reel], a, article, div');
                const step = item ? item.getBoundingClientRect().width + 16 : 240;
                let timer;
                const advance = () => {
                    const atEnd = track.scrollLeft + track.clientWidth + 8 >= track.scrollWidth;
                    track.scrollTo({
                        left: atEnd ? 0 : track.scrollLeft + step,
                        behavior: 'smooth',
                    });
                };
                const start = () => {
                    timer = setInterval(advance, 2600);
                };
                const stop = () => timer && clearInterval(timer);
                track.addEventListener('mouseenter', stop);
                track.addEventListener('mouseleave', start);
                start();
            });

            // Home hero slider
            const heroSlides = Array.from(document.querySelectorAll('[data-home-slide]'));
            const heroDots = Array.from(document.querySelectorAll('[data-home-dot]'));
            const heroThumbs = Array.from(document.querySelectorAll('[data-home-thumb]'));
            if (heroSlides.length) {
                let heroIndex = 0;
                let heroTimer;
                const HERO_MS = 4500;
                const showHero = (next) => {
                    heroSlides.forEach((slide, i) => {
                        const active = i === next;
                        slide.style.opacity = active ? '1' : '0';
                        slide.style.zIndex = active ? '10' : '0';
                    });
                    heroDots.forEach((dot, i) => {
                        dot.style.opacity = i === next ? '1' : '0.4';
                        dot.style.transform = i === next ? 'scale(1.2)' : 'scale(1)';
                    });
                    heroThumbs.forEach((btn, i) => {
                        btn.classList.toggle('ring-2', i === next);
                        btn.classList.toggle('ring-indigo-500', i === next);
                    });
                    heroIndex = next;
                };
                const heroNext = () => showHero((heroIndex + 1) % heroSlides.length);
                const startHero = () => {
                    stopHero();
                    heroTimer = setInterval(heroNext, HERO_MS);
                };
                const stopHero = () => {
                    if (heroTimer) clearInterval(heroTimer);
                    heroTimer = null;
                };
                heroDots.forEach((dot, i) => dot.addEventListener('click', () => {
                    showHero(i);
                    startHero();
                }));
                heroThumbs.forEach((btn, i) => btn.addEventListener('click', () => {
                    showHero(i);
                    startHero();
                }));
                showHero(0);
                startHero();
                document.getElementById('home-hero-slider')?.addEventListener('mouseenter', stopHero);
                document.getElementById('home-hero-slider')?.addEventListener('mouseleave', startHero);
            }
        })();
    </script>
@endpush
