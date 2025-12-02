@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Users</h1>
            <small class="text-muted">Admins, organizations, and members.</small>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Org</th>
                        <th>Joined</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->username }}</td>
                        <td class="text-muted">{{ $user->email }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.users.role', $user) }}" class="form-inline">
                                @csrf @method('PATCH')
                                <select name="role" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>User</option>
                                    <option value="organization" {{ $user->role === 'organization' ? 'selected' : '' }}>Organization</option>
                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                            </form>
                        </td>
                        <td class="text-muted">{{ $user->organization_name ?? '—' }}</td>
                        <td class="text-muted">{{ optional($user->created_at)->format('M d, Y') }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.users.delete', $user) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete user?')" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No users yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $users->links('pagination::bootstrap-4') }}
        </div>
    </div>
@endsection
