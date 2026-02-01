@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Offers</h1>
            <small class="text-muted">Active and draft offers.</small>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="m-0">Offers</h3>
                    <p class="text-muted mb-0">Active and draft offers.</p>
                </div>
                <a href="{{ route('admin.offers.create') }}" class="btn btn-primary">Add offer</a>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Offers</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Org</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Valid</th>
                                <th style="width:150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($offers as $offer)
                            <tr>
                                <td>{{ $offer->name }}</td>
                                <td class="text-muted">{{ $offer->category->name ?? '—' }}</td>
                                <td class="text-muted">{{ $offer->organization->organization_name ?? '—' }}</td>
                                <td class="text-muted">{{ $offer->phone_number ?? '—' }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($offer->status ?? 'draft') }}</span></td>
                                <td class="text-muted">
                                    {{ optional($offer->start_date)->format('M d') }} – {{ optional($offer->end_date)->format('M d, Y') }}
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.offers.edit', $offer) }}">Edit</a>
                                    <form method="post" action="{{ route('admin.offers.delete', $offer) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete offer?')" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No offers found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $offers->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection
