<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsUser;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use FormatsUser;

    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(6)],
            'role' => ['nullable', 'in:user,organization,admin'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'dob' => ['nullable', 'date'],
            'about' => ['nullable', 'string', 'max:1000'],
        ]);

        $role = $data['role'] ?? 'user';

        if ($role === 'organization') {
            $request->validate([
                'organization_name' => ['required', 'string', 'max:255'],
                'business_type' => ['required', 'string', 'max:100'],
                'phone' => ['required', 'string', 'max:30'],
            ]);
        }

        $referrer = $this->resolveReferrerFromCode($data['referral_code'] ?? null);
        if (($data['referral_code'] ?? null) && !$referrer) {
            return response()->json([
                'message' => 'The selected referral code is invalid.',
                'errors' => [
                    'referral_code' => ['The selected referral code is invalid.'],
                ],
            ], 422);
        }

        $user = DB::transaction(function () use ($data, $role, $referrer) {
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $role,
                'organization_name' => $data['organization_name'] ?? null,
                'business_type' => $data['business_type'] ?? null,
                'phone' => $data['phone'] ?? null,
                'full_name' => $data['full_name'] ?? null,
                'dob' => $data['dob'] ?? null,
                'about' => $data['about'] ?? null,
                'referral_code' => $this->generateUniqueReferralCode($data['username']),
                'referred_by_user_id' => $referrer?->id,
            ]);

            if ($referrer) {
                $this->recordCompletedReferral($referrer, $user, $data['referral_code']);
            }

            return $user;
        });

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
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required'],
        ]);

        $identifier = trim($data['email']);
        $user = $this->findUserForLogin($identifier);

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Invalid email or password.'], 401);
        }
        if (($user->status ?? 'active') !== 'active') {
            return response()->json(['error' => 'Your account is inactive. Please contact support.'], 403);
        }

        Role::firstOrCreate(['name' => $user->role, 'guard_name' => 'sanctum']);
        if (!$user->hasRole($user->role)) {
            $user->syncRoles([$user->role]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    private function findUserForLogin(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $identifier)->first();
        }

        $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $identifier);

        return User::where('phone', $identifier)
            ->when($normalizedPhone !== $identifier, function ($query) use ($normalizedPhone) {
                $query->orWhere('phone', $normalizedPhone);
            })
            ->first();
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Keep response generic to avoid exposing whether an email exists.
        if (!$user) {
            return response()->json([
                'message' => 'If this email exists, a temporary password has been sent.',
            ]);
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $user->password = Hash::make($temporaryPassword);
        $user->save();

        try {
            Mail::raw(
                "Hello {$user->username},\n\n" .
                "Your temporary password is: {$temporaryPassword}\n\n" .
                "Please log in and change your password immediately from your profile settings.\n\n" .
                "Regards,\nOworld Support",
                function ($message) use ($user) {
                    $message->from('support@oworldbd.com', 'Oworld Support');
                    $message->to($user->email);
                    $message->subject('Your Temporary Password - Oworld');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Forgot password email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Could not send temporary password email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'message' => 'If this email exists, a temporary password has been sent.',
        ]);
    }

    public function redirectToGoogle(Request $request)
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return response()->json(['error' => 'Google OAuth is not configured.'], 500);
        }

        $frontendRedirect = $request->query('redirect', $this->defaultFrontendRedirect());

        $state = $this->encodeState([
            'redirect' => $frontendRedirect,
            'role' => $this->sanitizeRole($request->query('role')),
            'referral_code' => trim((string) $request->query('referral_code', '')) ?: null,
        ]);

        $redirectUrl = $this->googleProvider($request)
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
        $referralCode = trim((string) ($state['referral_code'] ?? '')) ?: null;

        if ($request->filled('error')) {
            $googleError = (string) $request->query('error', '');

            return redirect($this->buildRedirectUrl($frontendRedirect, [
                'error' => $this->mapGoogleCallbackError($googleError),
            ]));
        }

        try {
            $googleUser = $this->googleProvider($request)->user();
        } catch (\Throwable $e) {
            try {
                Log::warning('Google OAuth callback failed', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'redirect_uri' => $this->resolveGoogleRedirectUri($request),
                    'request_host' => $request->getHost(),
                ]);
            } catch (\Throwable $logError) {
                // Ignore logging failures so auth flow can still return a user-facing error.
            }

            return redirect($this->buildRedirectUrl($frontendRedirect, [
                'error' => $this->mapOAuthFailureCode($e),
            ]));
        }

        if (!$googleUser || !$googleUser->getEmail()) {
            return redirect($this->buildRedirectUrl($frontendRedirect, ['error' => 'google_no_email']));
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = $this->createUserFromGoogle($googleUser, $role, $referralCode);
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
        if (($request->user()->status ?? 'active') !== 'active') {
            $request->user()->currentAccessToken()?->delete();
            return response()->json(['error' => 'Your account is inactive.'], 403);
        }

        $user = $request->user();
        Role::firstOrCreate(['name' => $user->role, 'guard_name' => 'sanctum']);
        if (!$user->hasRole($user->role)) {
            $user->syncRoles([$user->role]);
        }

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    private function createUserFromGoogle($googleUser, string $role, ?string $referralCode = null): User
    {
        $validatedRole = $this->sanitizeRole($role);
        $username = $this->generateUniqueUsername(
            $googleUser->getNickname() ?: $googleUser->getName(),
            $googleUser->getEmail()
        );
        $referrer = $this->resolveReferrerFromCode($referralCode);

        $user = DB::transaction(function () use ($username, $googleUser, $validatedRole, $referrer, $referralCode) {
            $user = User::create([
                'username' => $username,
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(16)),
                'role' => $validatedRole,
                'full_name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'referral_code' => $this->generateUniqueReferralCode($username),
                'referred_by_user_id' => $referrer?->id,
            ]);

            if ($referrer) {
                $this->recordCompletedReferral($referrer, $user, $referralCode);
            }

            return $user;
        });

        Role::firstOrCreate(['name' => $validatedRole, 'guard_name' => 'sanctum']);
        $user->syncRoles([$validatedRole]);

        return $user;
    }

    private function sanitizeRole(?string $role): string
    {
        return in_array($role, ['user', 'organization'], true) ? $role : 'user';
    }

    private function generateTemporaryPassword(): string
    {
        $letters = Str::upper(Str::random(4));
        $numbers = (string) random_int(1000, 9999);

        return $letters . $numbers;
    }

    private function decodeState(?string $state): array
    {
        if (!$state) {
            return [];
        }

        try {
            $normalized = $this->normalizeBase64Url($state);
            $decodedState = base64_decode($normalized, true);

            if ($decodedState === false) {
                return [];
            }

            $decoded = json_decode($decodedState, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function encodeState(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function normalizeBase64Url(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return $value;
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

    private function resolveReferrerFromCode(?string $referralCode): ?User
    {
        $code = trim((string) $referralCode);
        if ($code === '') {
            return null;
        }

        return User::where('referral_code', $code)->first();
    }

    private function generateUniqueReferralCode(?string $seed = null): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $seed) ?: 'OWORLD', 0, 6));

        do {
            $code = $base . Str::upper(Str::random(6));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function recordCompletedReferral(User $referrer, User $referredUser, ?string $referralCode = null): void
    {
        Referral::updateOrCreate(
            ['referred_user_id' => $referredUser->id],
            [
                'referrer_user_id' => $referrer->id,
                'referral_code' => trim((string) $referralCode) ?: $referrer->referral_code,
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );
    }

    private function defaultFrontendRedirect(): string
    {
        return config('services.google.frontend_redirect') ?? config('app.url') ?? 'http://localhost';
    }

    private function googleProvider(Request $request)
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->resolveGoogleRedirectUri($request));
    }

    private function resolveGoogleRedirectUri(Request $request): string
    {
        $configured = trim((string) config('services.google.redirect', ''));
        $requestBased = $request->getUriForPath('/api/auth/google/callback');

        if ($configured === '') {
            return $requestBased;
        }

        $configuredHost = parse_url($configured, PHP_URL_HOST);
        $requestHost = $request->getHost();

        if ($this->isLocalHost($requestHost) && !$this->isLocalHost($configuredHost)) {
            return $requestBased;
        }

        return $configured;
    }

    private function isLocalHost(?string $host): bool
    {
        if (!$host) {
            return false;
        }

        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function mapGoogleCallbackError(string $error): string
    {
        return match ($error) {
            'access_denied' => 'google_access_denied',
            default => 'google_auth_failed',
        };
    }

    private function mapOAuthFailureCode(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'redirect_uri_mismatch')) {
            return 'google_redirect_uri_mismatch';
        }

        if (str_contains($message, 'invalid_client')) {
            return 'google_invalid_client';
        }

        if (str_contains($message, 'invalid_grant')) {
            return 'google_invalid_grant';
        }

        return 'google_auth_failed';
    }
}
