@extends('layouts.frontend')

@section('content')
    <div class="page-shell">
        <div class="surface" style="max-width:520px; margin:2rem auto;">
            <h2 style="margin-top:0;">Log in</h2>
            <p class="muted">Access your dashboard with the same credentials as the API.</p>

            @if ($errors->any())
                <div class="alert" style="margin-bottom:1rem; border-color:#fecdd3; background:#fff1f2; color:#991b1b;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <div style="display:grid; gap:0.75rem;">
                    <label>Email
                        <input value="{{ old('email') }}" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="email" name="email" placeholder="you@example.com" required>
                    </label>
                    <label>Password
                        <input style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="password" name="password" required>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem;">
                        <input type="checkbox" name="remember" value="1"> Remember me
                    </label>
                    <button class="navbar-btn primary" type="submit">Log in</button>
                </div>
            </form>

            <p class="muted" style="margin-top:1rem;">No account yet? <a href="{{ route('register') }}">Create one</a></p>
        </div>
    </div>
@endsection
