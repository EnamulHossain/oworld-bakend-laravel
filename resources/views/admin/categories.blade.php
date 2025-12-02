@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Categories</h1>
            <small class="text-muted">Manage discovery sections.</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Add Category</h3>
                </div>
                <form method="post" action="{{ route('admin.categories.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label>Name</label>
                            <input name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Short name</label>
                            <input name="short_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Icon</label>
                            <input name="icon" class="form-control" placeholder="Emoji or icon class">
                        </div>
                        <div class="form-group">
                            <label>Image URL</label>
                            <input name="image" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Order</label>
                                <input type="number" name="order" class="form-control" min="0">
                            </div>
                            <div class="form-group col-6">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
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
                    <h3 class="card-title">Categories</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Short</th>
                                <th>Status</th>
                                <th>Order</th>
                                <th>Updated</th>
                                <th style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td class="text-muted">{{ $category->short_name ?? '—' }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($category->status) }}</span></td>
                                <td class="text-muted">{{ $category->order ?? '—' }}</td>
                                <td class="text-muted">{{ optional($category->updated_at)->diffForHumans() }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editCategory{{ $category->id }}">Edit</button>
                                    <form method="post" action="{{ route('admin.categories.delete', $category) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete category?')" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editCategory{{ $category->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <form method="post" action="{{ route('admin.categories.update', $category) }}">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit {{ $category->name }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label>Name</label>
                                                    <input name="name" class="form-control" value="{{ $category->name }}" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Short name</label>
                                                    <input name="short_name" class="form-control" value="{{ $category->short_name }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Icon</label>
                                                    <input name="icon" class="form-control" value="{{ $category->icon }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Image URL</label>
                                                    <input name="image" class="form-control" value="{{ $category->image }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Description</label>
                                                    <textarea name="description" class="form-control" rows="2">{{ $category->description }}</textarea>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-6">
                                                        <label>Order</label>
                                                        <input type="number" name="order" class="form-control" min="0" value="{{ $category->order }}">
                                                    </div>
                                                    <div class="form-group col-6">
                                                        <label>Status</label>
                                                        <select name="status" class="form-control" value="{{ $category->status }}">
                                                            <option value="active" {{ $category->status === 'active' ? 'selected' : '' }}>Active</option>
                                                            <option value="inactive" {{ $category->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                                            <option value="archived" {{ $category->status === 'archived' ? 'selected' : '' }}>Archived</option>
                                                        </select>
                                                    </div>
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
                            <tr><td colspan="6" class="text-muted">No categories found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $categories->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection
