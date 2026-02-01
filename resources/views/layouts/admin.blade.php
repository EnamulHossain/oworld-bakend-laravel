<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>oWorld Admin</title>
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars/css/OverlayScrollbars.min.css">
    @stack('styles')
    <style>
        .brand-link { font-weight: 700; }
        .alert-status { margin-bottom: 1rem; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="{{ url('/') }}">View site</a>
            </li>
            <li class="nav-item">
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-link nav-link" type="submit">Logout</button>
                </form>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('admin.dashboard') }}" class="brand-link">
            <span class="brand-text font-weight-light">oWorld Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.categories') }}" class="nav-link {{ request()->routeIs('admin.categories') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-folder-open"></i>
                            <p>Categories</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.events') }}" class="nav-link {{ request()->routeIs('admin.events') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>Events</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.offers') }}" class="nav-link {{ request()->routeIs('admin.offers') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-gift"></i>
                            <p>Offers</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview {{ request()->is('admin/settings/content*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('admin/settings/content*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-images"></i>
                            <p>Content Management <i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.content.home-slider') }}" class="nav-link {{ request()->routeIs('admin.settings.content.home-slider') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Home Slider</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.content.block-one') }}" class="nav-link {{ request()->routeIs('admin.settings.content.block-one') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Content Block 1</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.content.block-two') }}" class="nav-link {{ request()->routeIs('admin.settings.content.block-two') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Content Block 2</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Users</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sliders-h"></i>
                            <p>Settings</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.settings.website') }}" class="nav-link {{ request()->routeIs('admin.settings.website') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-globe"></i>
                            <p>Website Setup</p>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                @if (session('status'))
                    <div class="alert alert-success alert-status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-status">{{ $errors->first() }}</div> @endif
                @yield('content')
            </div>
        </div>
    </div>

    <footer class="main-footer">
    <strong>oWorld Admin</strong>
    <div class="float-right d-none d-sm-inline-block">
        <b>{{ now()->year }}</b>
    </div>
    </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    @stack('scripts')
    </body>

</html>
