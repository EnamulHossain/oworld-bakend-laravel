<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class WebAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended('/');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'role' => ['required', Rule::in(['user', 'organization'])],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
        ]);

        if ($data['role'] === 'organization') {
            $request->validate([
                'organization_name' => ['required', 'string', 'max:255'],
                'business_type' => ['required', 'string', 'max:100'],
                'phone' => ['required', 'string', 'max:30'],
            ]);
        }

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'organization_name' => $data['role'] === 'organization' ? $data['organization_name'] : null,
            'business_type' => $data['role'] === 'organization' ? $data['business_type'] : null,
            'phone' => $data['role'] === 'organization' ? $data['phone'] : null,
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
        ]);

        Role::firstOrCreate(['name' => $data['role'], 'guard_name' => 'sanctum']);
        $user->syncRoles([$data['role']]);

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
