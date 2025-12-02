@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Dashboard</h1>
            <small class="text-muted">Overview of activity and inventory</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['totalUsers'] }}</h3>
                    <p>Total Users</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['adminCount'] }}</h3>
                    <p>Admins</p>
                </div>
                <div class="icon"><i class="fas fa-user-shield"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['organizationCount'] }}</h3>
                    <p>Organizations</p>
                </div>
                <div class="icon"><i class="fas fa-building"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $stats['activeCategories'] }}/{{ $stats['totalCategories'] }}</h3>
                    <p>Categories (Active/Total)</p>
                </div>
                <div class="icon"><i class="fas fa-folder-open"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $stats['publishedEvents'] }}</h3>
                    <p>Published Events</p>
                </div>
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $stats['activeOffers'] }}</h3>
                    <p>Active Offers</p>
                </div>
                <div class="icon"><i class="fas fa-gift"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Events</h3>
                    <div class="card-tools">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.events') }}">View all</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($recentEvents as $event)
                            <tr>
                                <td>{{ $event->name }}</td>
                                <td class="text-muted">{{ $event->category->name ?? '—' }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($event->status) }}</span></td>
                                <td class="text-muted">{{ optional($event->starting_date)->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No events yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Offers</h3>
                    <div class="card-tools">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.offers') }}">View all</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Valid</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($recentOffers as $offer)
                            <tr>
                                <td>{{ $offer->name }}</td>
                                <td class="text-muted">{{ $offer->category->name ?? '—' }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($offer->status ?? 'draft') }}</span></td>
                                <td class="text-muted">
                                    {{ optional($offer->start_date)->format('M d') }} – {{ optional($offer->end_date)->format('M d, Y') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No offers yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
