@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-8">
            <h1 class="m-0 text-dark">Edit Offer</h1>
            <small class="text-muted">Update deal details.</small>
        </div>
        <div class="col-sm-4 text-right">
            <a href="{{ route('admin.offers') }}" class="btn btn-secondary">Back to list</a>
        </div>
    </div>

    <div class="card card-outline card-primary mb-4">
        <form method="post" action="{{ route('admin.offers.update', $offer) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Offer name</label>
                        <input name="name" class="form-control form-control-lg" value="{{ $offer->name }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <select name="status" class="form-control form-control-lg">
                            <option value="draft" {{ $offer->status === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ $offer->status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $offer->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="expired" {{ $offer->status === 'expired' ? 'selected' : '' }}>Expired</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Details</label>
                    <textarea name="details" class="form-control form-control-lg" rows="3">{{ $offer->details }}</textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Start date</label>
                        <input type="date" name="start_date" class="form-control form-control-lg" value="{{ optional($offer->start_date)->format('Y-m-d') }}">
                    </div>
                    <div class="form-group col-md-6">
                        <label>End date</label>
                        <input type="date" name="end_date" class="form-control form-control-lg" value="{{ optional($offer->end_date)->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Discount type</label>
                        <select name="discount_type" class="form-control form-control-lg">
                            <option value="percentage" {{ $offer->discount_type === 'percentage' ? 'selected' : '' }}>Percentage</option>
                            <option value="flat" {{ $offer->discount_type === 'flat' ? 'selected' : '' }}>Flat</option>
                            <option value="bogo" {{ $offer->discount_type === 'bogo' ? 'selected' : '' }}>BOGO</option>
                            <option value="custom" {{ $offer->discount_type === 'custom' ? 'selected' : '' }}>Custom</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Discount value</label>
                        <input type="number" step="0.01" min="0" name="discount_value" class="form-control form-control-lg" value="{{ $offer->discount_value }}">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Organization</label>
                        <select name="organization_id" class="form-control form-control-lg">
                            <option value="">Select organization</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" {{ $offer->organization_id == $org->id ? 'selected' : '' }}>{{ $org->organization_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Event</label>
                        <select name="event_id" class="form-control form-control-lg">
                            <option value="">None</option>
                            @foreach($events as $ev)
                                <option value="{{ $ev->id }}" {{ $offer->event_id == $ev->id ? 'selected' : '' }}>{{ $ev->name }}</option>
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
                                <option value="{{ $cat->id }}" {{ $offer->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Offer type</label>
                        <select name="offer_type" class="form-control form-control-lg">
                            <option value="general" {{ $offer->offer_type === 'general' ? 'selected' : '' }}>General</option>
                            <option value="category" {{ $offer->offer_type === 'category' ? 'selected' : '' }}>Category</option>
                            <option value="event" {{ $offer->offer_type === 'event' ? 'selected' : '' }}>Event</option>
                            <option value="special" {{ $offer->offer_type === 'special' ? 'selected' : '' }}>Special</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Thumbnail (visible in listings)</label>
                    <div class="custom-file">
                        <input type="file" name="thumbnail" class="custom-file-input" id="thumbnailInput" accept="image/*">
                        <label class="custom-file-label" for="thumbnailInput">Choose file</label>
                    </div>
                    <div class="mt-2 text-muted small" id="thumbPreview">
                        @if($offer->thumbnail)
                            Current: {{ $offer->thumbnail }}
                        @else
                            No thumbnail selected.
                        @endif
                    </div>
                </div>

                <div class="form-group">
                    <label>Cover (primary hero image)</label>
                    <div class="custom-file">
                        <input type="file" name="cover" class="custom-file-input" id="coverInput" accept="image/*">
                        <label class="custom-file-label" for="coverInput">Choose file</label>
                    </div>
                    <div class="mt-2 text-muted small" id="coverPreview">
                        @if($offer->cover)
                            Current: {{ $offer->cover }}
                        @else
                            No cover selected.
                        @endif
                    </div>
                </div>

                <div class="form-group">
                    <label>Gallery images</label>
                    <div class="custom-file">
                        <input type="file" name="images[]" class="custom-file-input" id="imagesInput" accept="image/*" multiple>
                        <label class="custom-file-label" for="imagesInput">Choose files</label>
                    </div>
                    <small class="text-muted">You can select multiple files.</small>
                    <div class="mt-2 text-muted small" id="imagesPreview">
                        @if(!empty($offer->images))
                            Current: {{ implode(', ', $offer->images) }}
                        @else
                            No gallery images selected.
                        @endif
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label>Videos</label>
                    <div class="custom-file">
                        <input type="file" name="videos[]" class="custom-file-input" id="videosInput" accept="video/mp4,video/webm,video/quicktime" multiple>
                        <label class="custom-file-label" for="videosInput">Choose files</label>
                    </div>
                    <small class="text-muted">MP4, WebM, MOV up to 50MB.</small>
                    <div class="mt-2 text-muted small" id="videosPreview">
                        @if(!empty($offer->videos))
                            Current: {{ implode(', ', $offer->videos) }}
                        @else
                            No videos selected.
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <a class="btn btn-secondary mr-2" href="{{ route('admin.offers') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Update Offer</button>
            </div>
        </form>
    </div>
@push('scripts')
<script>
    (function() {
        function setPreview(input, previewId, isImageList = false) {
            const preview = document.getElementById(previewId);
            if (!preview) return;
            const files = input.files;
            if (!files || !files.length) {
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

        ['thumbnailInput','coverInput','imagesInput','videosInput'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('change', () => {
                    if (id === 'imagesInput') return setPreview(input, 'imagesPreview', true);
                    setPreview(input, id === 'videosInput' ? 'videosPreview' : (id === 'thumbnailInput' ? 'thumbPreview' : 'coverPreview'));
                });
            }
        });
    })();
</script>
@endpush
@endsection
