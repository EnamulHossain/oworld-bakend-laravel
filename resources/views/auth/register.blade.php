@extends('layouts.frontend')

@section('content')
    <div class="page-shell">
        <div class="surface" style="max-width:620px; margin:2rem auto;">
            <h2 style="margin-top:0;">Create account</h2>
            <p class="muted">Sign up for users or organizations. Passwords must be at least 6 characters.</p>

            @if ($errors->any())
                <div class="alert" style="margin-bottom:1rem; border-color:#fecdd3; background:#fff1f2; color:#991b1b;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('register.store') }}">
                @csrf
                <div style="display:grid; gap:0.75rem;">
                    <label>Username
                        <input value="{{ old('username') }}" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="text" name="username" placeholder="Your name" required minlength="3">
                    </label>
                    <label>Email
                        <input value="{{ old('email') }}" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="email" name="email" placeholder="you@example.com" required>
                    </label>
                    <label>Password
                        <input style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="password" name="password" required minlength="6">
                    </label>
                    <label>Confirm password
                        <input style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="password" name="password_confirmation" required minlength="6">
                    </label>
                    <label>Role
                        <select name="role" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);">
                            <option value="user" {{ old('role') === 'organization' ? '' : 'selected' }}>User</option>
                            <option value="organization" {{ old('role') === 'organization' ? 'selected' : '' }}>Organization</option>
                        </select>
                    </label>
                    <div id="org-fields" style="display:grid; gap:0.5rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                        <label>Organization name
                            <input value="{{ old('organization_name') }}" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="text" name="organization_name" placeholder="World Eatery Co.">
                        </label>
                        <label>Business type
                            <input value="{{ old('business_type') }}" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="text" name="business_type" placeholder="Restaurant, Store...">
                        </label>
                        <label>Phone
                            <input value="{{ old('phone') }}" style="width:100%; padding:0.65rem 0.85rem; border-radius:10px; border:var(--border-soft);" type="tel" name="phone" placeholder="+91 98765 43210">
                        </label>
                    </div>
                    <button class="navbar-btn primary" type="submit">Create account</button>
                </div>
            </form>
            <p class="muted" style="margin-top:1rem;">Already registered? <a href="{{ route('login') }}">Log in</a></p>
        </div>
    </div>
@endsection
