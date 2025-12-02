@extends('layouts.admin')

@section('content')
    <style>
        .content-surface { background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 15px 40px rgba(15,23,42,.08); padding:1.25rem; }
        .content-card { border:1px solid #e5e7eb; border-radius:14px; padding:1rem; background:#fafafa; margin-bottom:1rem; }
        .content-card h5 { margin:0 0 .5rem; }
        .content-actions { display:flex; gap:.5rem; justify-content:flex-end; }
        .badge-soft { padding:.25rem .6rem; border-radius:999px; background:#eef2ff; color:#312e81; font-weight:700; font-size:.85rem; }
        .input-row { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
    </style>

    <form id="block-one-form" method="post" action="{{ route('admin.settings.content.block-one.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row mb-3">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Content Block 1</h1>
                <small class="text-muted">Manage the hero slider style cards for homepage.</small>
            </div>
            <div class="col-sm-6 text-right">
                <div class="content-actions">
                    <button type="button" class="btn btn-outline-secondary" id="add-card-btn">Add Slide</button>
                    <button type="submit" class="btn btn-primary">Save Block</button>
                </div>
            </div>
        </div>

        <div class="content-surface">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <p class="badge-soft mb-1">Block 1</p>
                    <h4 class="mb-0">Slides</h4>
                    <small class="text-muted">Max 10 items recommended.</small>
                </div>
            </div>
            <div id="cards-container" class="row"></div>
        </div>

        <input type="hidden" name="block_one" id="block_one_field">
    </form>

    <script>
        (function(){
            const initial = @json($blockOne ?: []);
            const cards = Array.isArray(initial) ? initial : [];
            const categories = @json($categories);
            const events = @json($events);
            const offers = @json($offers);
            const container = document.getElementById('cards-container');
            const hidden = document.getElementById('block_one_field');
            const addBtn = document.getElementById('add-card-btn');
            const blank = () => ({ type:'category', category:'', title:'', subtitle:'', image:'', link:'' });

            const optionsByType = {
                category: categories,
                event: events,
                offer: offers,
            };

            function buildOptions(type, current) {
                const list = optionsByType[type] || [];
                const opts = ['<option value="">Select</option>'];
                list.forEach(item => {
                    opts.push(`<option value="${item.id}" ${String(current) === String(item.id) ? 'selected' : ''}>${item.name}</option>`);
                });
                return opts.join('');
            }

            function render() {
                container.innerHTML = '';
                cards.forEach((card, idx) => {
                    // Ensure type exists
                    if (!['category','event','offer'].includes(card.type)) card.type = 'category';
                    const col = document.createElement('div');
                    col.className = 'col-xl-3 col-lg-4 col-md-6';
                    col.innerHTML = `
                        <div class="content-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge-soft">Category</span>
                                <button type="button" class="btn btn-link text-danger p-0" data-remove="${idx}"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="form-group mb-2">
                                <label>Type</label>
                                <select class="form-control type-select" data-field="type" data-idx="${idx}">
                                    <option value="category" ${card.type==='category'?'selected':''}>Category</option>
                                    <option value="event" ${card.type==='event'?'selected':''}>Event</option>
                                    <option value="offer" ${card.type==='offer'?'selected':''}>Offer</option>
                                </select>
                            </div>
                            <div class="form-group mb-2">
                                <label>${card.type === 'event' ? 'Event' : (card.type === 'offer' ? 'Offer' : 'Category')}</label>
                                <select class="form-control ref-select" data-field="category" data-idx="${idx}">
                                    ${buildOptions(card.type, card.category)}
                                </select>
                            </div>
                            <div class="form-group mb-2">
                                <label>Title</label>
                                <input class="form-control" data-field="title" data-idx="${idx}" value="${card.title || ''}" placeholder="Slide title">
                            </div>
                            <div class="form-group mb-2">
                                <label>Subtitle</label>
                                <input class="form-control" data-field="subtitle" data-idx="${idx}" value="${card.subtitle || ''}" placeholder="Short description">
                            </div>
                            <div class="form-group mb-2">
                                <label>Image</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input upload-input" data-preview="block1_preview_${idx}" name="block1_image_${idx}" id="block1_image_${idx}" accept="image/*">
                                    <label class="custom-file-label" for="block1_image_${idx}">Choose image</label>
                                </div>
                                <small class="form-text text-muted">Uploaded image overrides URL below.</small>
                                <div class="mt-2" id="block1_preview_${idx}">
                                    ${card.image ? `<img src="${card.image}" alt="preview" style="max-height:80px;border-radius:8px;">` : '<span class="text-muted small">No image selected</span>'}
                                </div>
                                <input class="form-control mt-2" data-field="image" data-idx="${idx}" value="${card.image || ''}" placeholder="https://">
                            </div>
                            <div class="form-group mb-0">
                                <label>External Link</label>
                                <input class="form-control" data-field="link" data-idx="${idx}" value="${card.link || ''}" placeholder="https://example.com">
                            </div>
                        </div>
                    `;
                    container.appendChild(col);
                });
                if (cards.length === 0) {
                    const col = document.createElement('div');
                    col.className = 'col-12 text-muted';
                    col.textContent = 'No slides yet. Add one to begin.';
                    container.appendChild(col);
                }
                hidden.value = JSON.stringify(cards);
            }

            container.addEventListener('input', (e) => {
                const idx = e.target.getAttribute('data-idx');
                const field = e.target.getAttribute('data-field');
                if (idx !== null && field && e.target.tagName !== 'SELECT') {
                    cards[idx][field] = e.target.value;
                    hidden.value = JSON.stringify(cards);
                }
            });
            container.addEventListener('click', (e) => {
                const rm = e.target.getAttribute('data-remove') || e.target.closest('[data-remove]')?.getAttribute('data-remove');
                if (rm !== null && rm !== undefined) {
                    cards.splice(Number(rm),1);
                    render();
                }
            });
            addBtn.addEventListener('click', () => {
                if (cards.length >= 10) return;
                cards.push(blank());
                render();
            });

            container.addEventListener('change', (e) => {
                const idx = e.target.getAttribute('data-idx');
                if (idx === null) return;
                if (e.target.classList.contains('type-select')) {
                    cards[idx].type = e.target.value;
                    cards[idx].category = '';
                    render();
                }
                if (e.target.classList.contains('ref-select')) {
                    cards[idx].category = e.target.value;
                    hidden.value = JSON.stringify(cards);
                }
            });

            if (cards.length === 0) cards.push(blank());
            render();
        })();
    </script>
    <script>
        document.addEventListener('change', (e) => {
            if (!e.target.classList.contains('custom-file-input')) return;
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
                const idx = previewId.replace('block1_preview_', '');
                const imgEl = document.querySelector(`#preview-img-${idx}`);
                if (imgEl) {
                    imgEl.outerHTML = `<img id="preview-img-${idx}" src="${url}" style="width:48px;height:48px;object-fit:cover;">`;
                }
            }
        });
    </script>
@endsection
