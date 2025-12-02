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
                <form method="post" action="{{ route('admin.events.store') }}">
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
                            <label>Banner URL</label>
                            <input name="banner" class="form-control">
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
                                        <form method="post" action="{{ route('admin.events.update', $event) }}">
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
                                                    <label>Banner URL</label>
                                                    <input name="banner" class="form-control" value="{{ is_array($event->banner) ? ($event->banner[0] ?? '') : $event->banner }}">
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
