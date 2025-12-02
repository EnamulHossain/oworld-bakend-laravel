@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Settings</h1>
            <small class="text-muted">Manage system settings used by the site.</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Add Setting</h3>
                </div>
                <form method="post" action="{{ route('admin.settings.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label>Key</label>
                            <input name="key" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Value</label>
                            <input name="value" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <input name="type" class="form-control" placeholder="text, image, json...">
                        </div>
                        <div class="form-group">
                            <label>Label</label>
                            <input name="label" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Group</label>
                            <input name="group" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
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
                    <h3 class="card-title">Settings</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Value</th>
                                <th>Group</th>
                                <th>Status</th>
                                <th style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($settings as $setting)
                            <tr>
                                <td>{{ $setting->key }}</td>
                                <td class="text-muted">{{ is_array($setting->value) ? json_encode($setting->value) : $setting->value }}</td>
                                <td class="text-muted">{{ $setting->group ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $setting->is_active ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $setting->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editSetting{{ $setting->id }}">Edit</button>
                                    <form method="post" action="{{ route('admin.settings.delete', $setting) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete setting?')" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editSetting{{ $setting->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <form method="post" action="{{ route('admin.settings.update', $setting) }}">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit {{ $setting->key }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label>Key</label>
                                                    <input name="key" class="form-control" value="{{ $setting->key }}" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Value</label>
                                                    <input name="value" class="form-control" value="{{ is_array($setting->value) ? json_encode($setting->value) : $setting->value }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Type</label>
                                                    <input name="type" class="form-control" value="{{ $setting->type }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Label</label>
                                                    <input name="label" class="form-control" value="{{ $setting->label }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Description</label>
                                                    <textarea name="description" class="form-control" rows="2">{{ $setting->description }}</textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label>Group</label>
                                                    <input name="group" class="form-control" value="{{ $setting->group }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="is_active" class="form-control">
                                                        <option value="1" {{ $setting->is_active ? 'selected' : '' }}>Active</option>
                                                        <option value="0" {{ !$setting->is_active ? 'selected' : '' }}>Inactive</option>
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
                            <tr><td colspan="5" class="text-muted">No settings yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $settings->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
