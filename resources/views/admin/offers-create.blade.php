@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-8">
            <h1 class="m-0 text-dark">Offers management</h1>
            <small class="text-muted">Create deals for any organization</small>
        </div>
        <div class="col-sm-4 text-right">
            <a href="{{ route('admin.offers') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    <div class="card card-outline card-primary mb-4">
        <form method="post" action="{{ route('admin.offers.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Offer name</label>
                        <input name="name" class="form-control form-control-lg" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <select name="status" class="form-control form-control-lg">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Details</label>
                    <textarea name="details" class="form-control form-control-lg js-summernote" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Phone number</label>
                    <input name="phone_number" class="form-control form-control-lg" value="{{ old('phone_number') }}">
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Start date</label>
                        <input type="date" name="start_date" class="form-control form-control-lg" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>End date</label>
                        <input type="date" name="end_date" class="form-control form-control-lg" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Discount type</label>
                        <select name="discount_type" class="form-control form-control-lg">
                            <option value="percentage">Percentage</option>
                            <option value="flat">Flat</option>
                            <option value="bogo">BOGO</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Discount value</label>
                        <input type="number" step="0.01" min="0" name="discount_value" class="form-control form-control-lg">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Organization</label>
                        <select name="organization_id" class="form-control form-control-lg">
                            <option value="">Select organization</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->organization_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Event</label>
                        <select name="event_id" class="form-control form-control-lg">
                            <option value="">None</option>
                            @foreach($events as $ev)
                                <option value="{{ $ev->id }}">{{ $ev->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Category</label>
                        <select name="category_id" class="form-control form-control-lg">
                            <option value="">None</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Thumbnail (visible in listings)</label>
                    <div class="custom-file">
                        <input type="file" name="thumbnail" class="custom-file-input" id="thumbnailInput" accept="image/*">
                        <label class="custom-file-label" for="thumbnailInput">Choose file</label>
                    </div>
                    <div class="mt-2 text-muted small" id="thumbPreview">No thumbnail selected.</div>
                </div>

                <div class="form-group">
                    <label>Cover (primary hero image)</label>
                    <div class="custom-file">
                        <input type="file" name="cover" class="custom-file-input" id="coverInput" accept="image/*">
                        <label class="custom-file-label" for="coverInput">Choose file</label>
                    </div>
                    <div class="mt-2 text-muted small" id="coverPreview">No cover selected.</div>
                </div>

                <div class="form-group">
                    <label>Gallery images</label>
                    <div class="custom-file">
                        <input type="file" name="images[]" class="custom-file-input" id="imagesInput" accept="image/*" multiple>
                        <label class="custom-file-label" for="imagesInput">Choose files</label>
                    </div>
                    <small class="text-muted">You can select multiple files.</small>
                    <div class="mt-2 text-muted small" id="imagesPreview">No gallery images selected.</div>
                    <div class="mt-3" id="imagesOrderList"></div>
                </div>

                <div class="form-group mb-0">
                    <label>Videos</label>
                    <div class="custom-file">
                        <input type="file" name="videos[]" class="custom-file-input" id="videosInput" accept="video/mp4,video/webm,video/quicktime" multiple>
                        <label class="custom-file-label" for="videosInput">Choose files</label>
                    </div>
                    <small class="text-muted">MP4, WebM, MOV up to 50MB.</small>
                    <div class="mt-2 text-muted small" id="videosPreview">No videos selected.</div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <a class="btn btn-secondary mr-2" href="{{ route('admin.offers') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Add Offer</button>
            </div>
        </form>
    </div>
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
@endpush
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
    $(function() {
        $('.js-summernote').summernote({
            height: 220,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['codeview']]
            ]
        });
    });

    (function() {
        function setPreview(input, previewId, isImageList = false) {
            const preview = document.getElementById(previewId);
            if (!preview) return;
            const files = input.files;
            if (!files || !files.length) {
                preview.innerHTML = 'No ' + input.name.replace('[]','') + ' selected.';
                const orderList = document.getElementById('imagesOrderList');
                if (orderList) orderList.innerHTML = '';
                return;
            }
            const items = [];
            Array.from(files).forEach(file => {
                if (isImageList && file.type.startsWith('image/')) {
                    const url = URL.createObjectURL(file);
                    items.push(`<img src="${url}" style="height:60px;border-radius:8px;margin-right:6px;margin-bottom:6px;">`);
                } else {
                    items.push(file.name);
                }
            });
            preview.innerHTML = items.join(' ');
        }

        function renderImageOrderInputs(input) {
            const orderList = document.getElementById('imagesOrderList');
            if (!orderList) return;
            const files = input.files;
            if (!files || !files.length) {
                orderList.innerHTML = '';
                return;
            }
            const rows = Array.from(files).map((file, index) => {
                const display = file.name.length > 36 ? file.name.slice(0, 33) + '...' : file.name;
                return `
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-muted small mr-2" style="min-width: 160px;">${display}</div>
                        <input type="number" name="gallery_sort_order_new[]" class="form-control form-control-sm" style="width: 90px;" min="1" value="${index + 1}">
                    </div>
                `;
            });
            orderList.innerHTML = `
                <label class="d-block text-muted small mb-2">Gallery sort order</label>
                ${rows.join('')}
            `;
        }

        ['thumbnailInput','coverInput','imagesInput','videosInput'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('change', () => {
                    if (id === 'imagesInput') {
                        setPreview(input, 'imagesPreview', true);
                        renderImageOrderInputs(input);
                        return;
                    }
                    setPreview(input, id === 'videosInput' ? 'videosPreview' : (id === 'thumbnailInput' ? 'thumbPreview' : 'coverPreview'));
                });
            }
        });
    })();
</script>
@endpush
@endsection
