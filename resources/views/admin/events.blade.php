@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Events</h1>
            <small class="text-muted">Published and draft events.</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Create Event</h3>
                </div>
                <form method="post" action="{{ route('admin.events.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label>Name</label>
                            <input name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Banner URL (optional)</label>
                            <input name="banner_url" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Banner gallery</label>
                            <div class="custom-file">
                                <input type="file" name="banner_files[]" class="custom-file-input banner-files-input" id="bannerFilesCreate" data-preview="bannerPreviewCreate" data-order="bannerOrderCreate" accept="image/*,video/*" multiple>
                                <label class="custom-file-label" for="bannerFilesCreate">Choose files</label>
                            </div>
                            <small class="text-muted">Upload multiple images/videos.</small>
                            <div class="mt-2 text-muted small" id="bannerPreviewCreate">No banner media selected.</div>
                            <div class="mt-3" id="bannerOrderCreate"></div>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input name="location" class="form-control">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Start date</label>
                                <input type="date" name="starting_date" class="form-control" required>
                            </div>
                            <div class="form-group col-6">
                                <label>End date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="">—</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-6">
                                <label>Organization</label>
                                <select name="organization_id" class="form-control">
                                    <option value="">—</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->organization_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary btn-block" type="submit">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Events</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Org</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th style="width:150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>{{ $event->name }}</td>
                                <td class="text-muted">{{ $event->category->name ?? '—' }}</td>
                                <td class="text-muted">{{ $event->organization->organization_name ?? '—' }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($event->status) }}</span></td>
                                <td class="text-muted">{{ optional($event->starting_date)->format('M d, Y') }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editEvent{{ $event->id }}">Edit</button>
                                    <form method="post" action="{{ route('admin.events.delete', $event) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete event?')" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editEvent{{ $event->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <form method="post" action="{{ route('admin.events.update', $event) }}" enctype="multipart/form-data">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit {{ $event->name }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label>Name</label>
                                                    <input name="name" class="form-control" value="{{ $event->name }}" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Description</label>
                                                    <textarea name="description" class="form-control" rows="2">{{ $event->description }}</textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label>Banner URL (optional)</label>
                                                    <input name="banner_url" class="form-control" value="{{ is_array($event->banner) ? ($event->banner[0] ?? '') : $event->banner }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Banner gallery</label>
                                                    <div class="custom-file">
                                                        <input type="file" name="banner_files[]" class="custom-file-input banner-files-input" id="bannerFiles{{ $event->id }}" data-preview="bannerPreview{{ $event->id }}" data-order="bannerOrder{{ $event->id }}" accept="image/*,video/*" multiple>
                                                        <label class="custom-file-label" for="bannerFiles{{ $event->id }}">Choose files</label>
                                                    </div>
                                                    <small class="text-muted">Upload multiple images/videos.</small>
                                                    <div class="mt-2 text-muted small" id="bannerPreview{{ $event->id }}">
                                                        @if(!empty($event->banner))
                                                            Current: {{ implode(', ', is_array($event->banner) ? $event->banner : [$event->banner]) }}
                                                        @else
                                                            No banner media selected.
                                                        @endif
                                                    </div>
                                                    @if(!empty($event->banner))
                                                        <div class="mt-3">
                                                            <label class="d-block text-muted small mb-2">Banner sort order (existing)</label>
                                                            @foreach((is_array($event->banner) ? $event->banner : [$event->banner]) as $index => $url)
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <div class="text-muted small mr-2" style="min-width: 160px;">
                                                                        {{ strlen($url) > 36 ? substr($url, 0, 33) . '...' : $url }}
                                                                    </div>
                                                                    <input
                                                                        type="number"
                                                                        name="gallery_sort_order_existing[{{ $url }}]"
                                                                        class="form-control form-control-sm"
                                                                        style="width: 90px;"
                                                                        min="1"
                                                                        value="{{ (is_array($event->gallery_sort_order ?? null) && array_key_exists($url, $event->gallery_sort_order)) ? $event->gallery_sort_order[$url] : ($index + 1) }}"
                                                                    >
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    <div class="mt-3" id="bannerOrder{{ $event->id }}"></div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Location</label>
                                                    <input name="location" class="form-control" value="{{ $event->location }}">
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-6">
                                                        <label>Start date</label>
                                                        <input type="date" name="starting_date" class="form-control" value="{{ optional($event->starting_date)->format('Y-m-d') }}">
                                                    </div>
                                                    <div class="form-group col-6">
                                                        <label>End date</label>
                                                        <input type="date" name="end_date" class="form-control" value="{{ optional($event->end_date)->format('Y-m-d') }}">
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-6">
                                                        <label>Category</label>
                                                        <select name="category_id" class="form-control">
                                                            <option value="">—</option>
                                                            @foreach($categories as $cat)
                                                                <option value="{{ $cat->id }}" {{ $event->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-6">
                                                        <label>Organization</label>
                                                        <select name="organization_id" class="form-control">
                                                            <option value="">—</option>
                                                            @foreach($organizations as $org)
                                                                <option value="{{ $org->id }}" {{ $event->organization_id == $org->id ? 'selected' : '' }}>{{ $org->organization_name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="status" class="form-control">
                                                        <option value="draft" {{ $event->status === 'draft' ? 'selected' : '' }}>Draft</option>
                                                        <option value="published" {{ $event->status === 'published' ? 'selected' : '' }}>Published</option>
                                                        <option value="cancelled" {{ $event->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                                        <option value="completed" {{ $event->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="6" class="text-muted">No events found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $events->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function() {
        function setPreview(input, previewId) {
            const preview = document.getElementById(previewId);
            if (!preview) return;
            const files = input.files;
            if (!files || !files.length) {
                preview.innerHTML = 'No banner media selected.';
                return;
            }
            const items = [];
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const url = URL.createObjectURL(file);
                    items.push(`<img src="${url}" style="height:60px;border-radius:8px;margin-right:6px;margin-bottom:6px;">`);
                } else {
                    items.push(file.name);
                }
            });
            preview.innerHTML = items.join(' ');
        }

        function renderOrderInputs(input, orderId) {
            const orderList = document.getElementById(orderId);
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
                <label class="d-block text-muted small mb-2">Banner sort order (new uploads)</label>
                ${rows.join('')}
            `;
        }

        document.querySelectorAll('.banner-files-input').forEach((input) => {
            input.addEventListener('change', () => {
                const previewId = input.getAttribute('data-preview');
                const orderId = input.getAttribute('data-order');
                if (previewId) setPreview(input, previewId);
                if (orderId) renderOrderInputs(input, orderId);
            });
        });
    })();
</script>
@endpush
