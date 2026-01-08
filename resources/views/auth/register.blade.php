@extends('layouts.frontend')

@section('content')
    <div class="relative overflow-hidden bg-gradient-to-br from-amber-50 via-white to-indigo-50">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-10 top-10 h-64 w-64 rounded-full bg-indigo-200/50 blur-3xl"></div>
            <div class="absolute bottom-0 left-10 h-72 w-72 rounded-full bg-amber-200/50 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col items-center justify-center px-4 py-12 lg:px-6">
            <div class="mb-8 text-center animate-fade-in">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100 shadow">Create account</p>
                <h1 class="mt-3 text-3xl font-black text-slate-900 sm:text-4xl">Join the oWorld community</h1>
                <p class="mt-2 text-sm text-slate-600">Sign up as a user or organization to publish events and offers.</p>
            </div>

            <div class="grid w-full max-w-5xl gap-6 md:grid-cols-5 animate-rise">
                <div class="md:col-span-3 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-xl ring-1 ring-white/70 backdrop-blur">
                    @if ($errors->any())
                        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="post" action="{{ route('register.store') }}" class="space-y-4" id="register-form">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Username</label>
                                <input value="{{ old('username') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="text" name="username" placeholder="Your name" required minlength="3">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Email</label>
                                <input value="{{ old('email') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="email" name="email" placeholder="you@example.com" required>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Date of birth</label>
                                <input value="{{ old('dob') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="date" name="dob">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Password</label>
                                <input class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="password" name="password" required minlength="6">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Confirm password</label>
                                <input class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="password" name="password_confirmation" required minlength="6">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-800">Role</label>
                            <select name="role" id="role-select" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                                <option value="user" {{ old('role') === 'organization' ? '' : 'selected' }}>User</option>
                                <option value="organization" {{ old('role') === 'organization' ? 'selected' : '' }}>Organization</option>
                            </select>
                        </div>
                        <div id="org-fields" class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Organization name</label>
                                <input value="{{ old('organization_name') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="text" name="organization_name" placeholder="World Eatery Co.">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Business type</label>
                                <input value="{{ old('business_type') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="text" name="business_type" placeholder="Restaurant, Store...">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-800">Phone</label>
                                <input value="{{ old('phone') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" type="tel" name="phone" placeholder="+91 98765 43210">
                            </div>
                        </div>
                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-700 hover:shadow-xl" type="submit">
                            <span>Create account</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7"/></svg>
                        </button>
                    </form>
                    <p class="mt-4 text-center text-sm text-slate-600">Already registered? <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-700">Log in</a></p>
                </div>

                <div class="md:col-span-2 flex flex-col justify-between gap-4 rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-500 to-purple-500 p-6 text-white shadow-xl">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-wide text-white/80">Why join</p>
                        <h3 class="text-2xl font-black">Publish and grow with oWorld</h3>
                        <p class="text-sm text-white/80">Host events, launch offers, and reach more people instantly.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-3 py-2 text-sm font-semibold backdrop-blur">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/20">🚀</span>
                            Fast setup for organizations
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-3 py-2 text-sm font-semibold backdrop-blur">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/20">📣</span>
                            Promote events and offers
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-3 py-2 text-sm font-semibold backdrop-blur">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/20">🤝</span>
                            Engage your audience
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@push('scripts')
<script>
    (() => {
        const role = document.getElementById('role-select');
        const orgFields = document.getElementById('org-fields');
        if (!role || !orgFields) return;
        const toggle = () => {
            const isOrg = role.value === 'organization';
            orgFields.style.display = isOrg ? 'grid' : 'none';
        };
        role.addEventListener('change', toggle);
        toggle();
    })();
</script>
@endpush
@endsection
