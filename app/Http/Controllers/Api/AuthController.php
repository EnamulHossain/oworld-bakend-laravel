<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(6)],
            'role' => ['nullable', 'in:user,organization,admin'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $role = $data['role'] ?? 'user';

        if ($role === 'organization') {
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
            'role' => $role,
            'organization_name' => $data['organization_name'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);
        $user->syncRoles([$role]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Invalid email or password.'], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    public function redirectToGoogle(Request $request)
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return response()->json(['error' => 'Google OAuth is not configured.'], 500);
        }

        $frontendRedirect = $request->query('redirect', $this->defaultFrontendRedirect());

        $state = base64_encode(json_encode([
            'redirect' => $frontendRedirect,
            'role' => $this->sanitizeRole($request->query('role')),
        ]));

        $redirectUrl = Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state, 'prompt' => 'select_account'])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    public function handleGoogleCallback(Request $request)
    {
        $state = $this->decodeState($request->get('state'));
        $frontendRedirect = $state['redirect'] ?? $this->defaultFrontendRedirect();
        $role = $state['role'] ?? 'user';

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect($this->buildRedirectUrl($frontendRedirect, ['error' => 'google_auth_failed']));
        }

        if (!$googleUser || !$googleUser->getEmail()) {
            return redirect($this->buildRedirectUrl($frontendRedirect, ['error' => 'google_no_email']));
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = $this->createUserFromGoogle($googleUser, $role);
        } else {
            $user->forceFill([
                'google_id' => $user->google_id ?: $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ])->save();

            Role::firstOrCreate(['name' => $user->role, 'guard_name' => 'sanctum']);
            $user->syncRoles([$user->role]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return redirect($this->buildRedirectUrl($frontendRedirect, [
            'token' => $token,
            'user' => base64_encode(json_encode($this->formatUser($user))),
        ]));
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'organizationName' => $user->organization_name,
            'business_type' => $user->business_type,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'created_at' => $user->created_at,
        ];
    }

    private function createUserFromGoogle($googleUser, string $role): User
    {
        $validatedRole = $this->sanitizeRole($role);
        $username = $this->generateUniqueUsername(
            $googleUser->getNickname() ?: $googleUser->getName(),
            $googleUser->getEmail()
        );

        $user = User::create([
            'username' => $username,
            'email' => $googleUser->getEmail(),
            'password' => Hash::make(Str::random(16)),
            'role' => $validatedRole,
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
        ]);

        Role::firstOrCreate(['name' => $validatedRole, 'guard_name' => 'sanctum']);
        $user->syncRoles([$validatedRole]);

        return $user;
    }

    private function sanitizeRole(?string $role): string
    {
        return in_array($role, ['user', 'organization'], true) ? $role : 'user';
    }

    private function decodeState(?string $state): array
    {
        if (!$state) {
            return [];
        }

        try {
            $decoded = json_decode(base64_decode($state), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildRedirectUrl(string $base, array $params = []): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($params);
    }

    private function generateUniqueUsername(?string $name, string $email): string
    {
        $base = Str::slug($name ?: explode('@', $email)[0]) ?: 'user';
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    private function defaultFrontendRedirect(): string
    {
        return config('services.google.frontend_redirect') ?? config('app.url') ?? 'http://localhost';
    }
}
