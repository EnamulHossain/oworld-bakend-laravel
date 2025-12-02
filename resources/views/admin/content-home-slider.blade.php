@extends('layouts.admin')

@section('content')
    <style>
        .content-surface { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 15px 40px rgba(15,23,42,0.08); padding: 1.25rem; }
        .content-card { border: 1px solid #e5e7eb; border-radius: 14px; padding: 1rem; background: #fafafa; margin-bottom: 1rem; }
        .content-card h5 { margin: 0 0 .5rem; }
        .content-actions { display: flex; gap: .5rem; justify-content: flex-end; }
        .preview-box { background: linear-gradient(135deg, #f8fafc, #eef2ff); border-radius: 14px; border: 1px solid #e2e8f0; padding: 1rem; min-height: 220px; }
        .badge-soft { padding: .25rem .6rem; border-radius: 999px; background: #eef2ff; color: #312e81; font-weight: 700; font-size: .85rem; }
        .input-row { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); }
    </style>

    <form id="home-slider-form" method="post" action="{{ route('admin.settings.content.home-slider.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row mb-3">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Home Slider</h1>
                <small class="text-muted">Manage homepage hero slider.</small>
            </div>
            <div class="col-sm-6 text-right">
                <div class="content-actions">
                    <button type="button" class="btn btn-outline-secondary" id="add-slide-btn">Add Slide</button>
                    <button type="submit" class="btn btn-primary">Save Slider</button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="content-surface">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <p class="badge-soft mb-1">Homepage</p>
                            <h4 class="mb-0">Hero Slider</h4>
                        </div>
                    </div>

                    <div id="slides-container"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-surface preview-box">
                    <p class="badge-soft mb-1">Live preview</p>
                    <h5>Hero Cards</h5>
                    <div id="preview-area" class="mt-3"></div>
                </div>
            </div>
        </div>

        <input type="hidden" name="home_slider" id="home_slider_field">
    </form>

    <script>
        (function() {
            const initialSlides = @json($homeSlider ?: []);
            const slides = Array.isArray(initialSlides) ? initialSlides : [];
            const slidesContainer = document.getElementById('slides-container');
            const previewArea = document.getElementById('preview-area');
            const hiddenField = document.getElementById('home_slider_field');
            const addBtn = document.getElementById('add-slide-btn');

            const blankSlide = () => ({ title:'', subtitle:'', description:'', badge:'', ctaText:'', ctaLink:'', image:'' });

            function render() {
            slidesContainer.innerHTML = '';
            slides.forEach((slide, idx) => {
                const card = document.createElement('div');
                card.className = 'content-card';
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <p class="badge-soft mb-1">Slide ${idx+1}</p>
                                <h5>${slide.title || 'Untitled'}</h5>
                            </div>
                            <button type="button" class="btn btn-link text-danger p-0" data-remove="${idx}">Remove</button>
                        </div>
                        <div class="input-row">
                            <div class="form-group mb-2">
                                <label>Title</label>
                                <input class="form-control" data-field="title" data-idx="${idx}" value="${slide.title || ''}">
                            </div>
                            <div class="form-group mb-2">
                                <label>Subtitle</label>
                                <input class="form-control" data-field="subtitle" data-idx="${idx}" value="${slide.subtitle || ''}">
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label>Description</label>
                            <textarea class="form-control" rows="2" data-field="description" data-idx="${idx}">${slide.description || ''}</textarea>
                        </div>
                        <div class="input-row">
                            <div class="form-group mb-2">
                                <label>Badge</label>
                                <input class="form-control" data-field="badge" data-idx="${idx}" value="${slide.badge || ''}">
                            </div>
                            <div class="form-group mb-2">
                                <label>CTA Label</label>
                                <input class="form-control" data-field="ctaText" data-idx="${idx}" value="${slide.ctaText || ''}">
                            </div>
                            <div class="form-group mb-2">
                                <label>CTA Link</label>
                                <input class="form-control" data-field="ctaLink" data-idx="${idx}" value="${slide.ctaLink || ''}">
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label>Cover Image</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input upload-input" data-preview="slide_preview_${idx}" name="slide_image_${idx}" id="slide_image_${idx}" accept="image/*">
                                <label class="custom-file-label" for="slide_image_${idx}">Choose image</label>
                            </div>
                            <small class="form-text text-muted">Uploaded image overrides the URL below.</small>
                            <div class="mt-2" id="slide_preview_${idx}">
                                ${slide.image ? `<img src="${slide.image}" alt="preview" style="max-height:80px;border-radius:8px;">` : '<span class="text-muted small">No image selected</span>'}
                            </div>
                            <input class="form-control mt-2" data-field="image" data-idx="${idx}" value="${slide.image || ''}" placeholder="/storage/uploads/hero.jpg">
                        </div>
                    `;
                    slidesContainer.appendChild(card);
                });

                previewArea.innerHTML = '';
                slides.slice(0,3).forEach((slide, idx) => {
                    const wrap = document.createElement('div');
                    wrap.className = 'd-flex align-items-center mb-2';
                    wrap.innerHTML = `
                        <div style="width:48px;height:48px;border-radius:12px;background:#e5e7eb;margin-right:10px;overflow:hidden;">
                            ${slide.image ? `<img id="preview-img-${idx}" src="${slide.image}" style="width:48px;height:48px;object-fit:cover;">` : `<div id="preview-img-${idx}"></div>`}
                        </div>
                        <div>
                            <strong id="preview-title-${idx}">${slide.title || 'Slide title'}</strong><br>
                            <small class="text-muted" id="preview-sub-${idx}">${slide.subtitle || 'Subtitle text for the slide.'}</small>
                        </div>
                    `;
                    previewArea.appendChild(wrap);
                });

                hiddenField.value = JSON.stringify(slides);
            }

            slidesContainer.addEventListener('input', (e) => {
                const idx = e.target.getAttribute('data-idx');
                const field = e.target.getAttribute('data-field');
                if (idx !== null && field) {
                    slides[idx][field] = e.target.value;
                    hiddenField.value = JSON.stringify(slides);
                    const prevTitle = document.getElementById(`preview-title-${idx}`);
                    const prevSub = document.getElementById(`preview-sub-${idx}`);
                    if (field === 'title' && prevTitle) prevTitle.textContent = slides[idx][field] || 'Slide title';
                    if (field === 'subtitle' && prevSub) prevSub.textContent = slides[idx][field] || 'Subtitle text for the slide.';
                }
            });

            slidesContainer.addEventListener('click', (e) => {
                const removeIdx = e.target.getAttribute('data-remove');
                if (removeIdx !== null) {
                    slides.splice(Number(removeIdx), 1);
                    render();
                }
            });

            addBtn.addEventListener('click', () => {
                slides.push(blankSlide());
                render();
            });

            if (slides.length === 0) {
                slides.push(blankSlide());
            }
            render();
        })();
    </script>
    <script>
        document.addEventListener('change', (e) => {
            if (!e.target.classList.contains('upload-input')) return;
            const label = e.target.nextElementSibling;
            if (label) {
                label.textContent = e.target.files?.[0]?.name || 'Choose image';
            }
            const previewId = e.target.getAttribute('data-preview');
            const file = e.target.files?.[0];
            if (previewId && file) {
                const url = URL.createObjectURL(file);
                const preview = document.getElementById(previewId);
                if (preview) {
                    preview.innerHTML = `<img src="${url}" alt="preview" style="max-height:80px;border-radius:8px;">`;
                }
                const idx = previewId.replace('slide_preview_', '');
                const imgEl = document.getElementById(`preview-img-${idx}`);
                if (imgEl) {
                    imgEl.outerHTML = `<img id="preview-img-${idx}" src="${url}" style="width:48px;height:48px;object-fit:cover;">`;
                }
                hiddenField.value = hiddenField.value; // keep form data; file submission handled by form
            }
        });
    </script>
@endsection
