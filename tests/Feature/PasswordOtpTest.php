<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordOtpTest extends TestCase
{
    private string $otp;
    private bool $rejectSms = false;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('email');
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
        });
        Schema::create('sessions', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); });
        Schema::create('password_reset_tokens', function (Blueprint $table) { $table->string('email'); });
        User::create(['phone' => '+8801712345678', 'email' => 'recovery@example.com', 'password' => Hash::make('old-password')]);
        config(['services.gennet.domain' => 'https://sms.example.test', 'services.gennet.api_token' => 'fake-token', 'services.gennet.sid' => 'TEST']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            preg_match('/code is (\d{6})/', $request['sms'], $matches);
            $this->otp = $matches[1];
            $this->assertSame('8801712345678', $request['msisdn']);
            return Http::response(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => $this->rejectSms ? 'INVALID' : 'SUCCESS']]]);
        });
    }

    public function test_recovery_requires_otp_and_token_is_single_use(): void
    {
        $phone = '01712345678';
        $this->postJson('/api/auth/password-otp/send', ['phone' => $phone])->assertOk()->assertJsonMissingPath('test_otp');
        $this->postJson('/api/auth/password-otp/send', ['phone' => $phone])->assertUnprocessable();
        $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => '000000'])->assertUnprocessable();
        $token = $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => $this->otp])->assertOk()->json('reset_token');
        $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => $this->otp])->assertUnprocessable();
        $body = ['phone' => $phone, 'reset_token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        DB::table('personal_access_tokens')->insert(['tokenable_id' => User::first()->id, 'tokenable_type' => User::class]);
        DB::table('sessions')->insert(['user_id' => User::first()->id]);
        $this->postJson('/api/auth/password-otp/reset', array_merge($body, ['password_confirmation' => 'different']))->assertUnprocessable();
        $this->postJson('/api/auth/password-otp/reset', $body)->assertOk();
        $this->assertTrue(Hash::check('new-password', User::first()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('sessions', 0);
        $this->postJson('/api/auth/password-otp/reset', $body)->assertUnprocessable();
    }

    public function test_unknown_and_ambiguous_phone_numbers_do_not_send_sms(): void
    {
        $this->postJson('/api/auth/password-otp/send', ['phone' => '01812345678'])->assertUnprocessable();
        User::create(['phone' => '01712345678', 'email' => 'other@example.com', 'password' => 'other-password']);
        $this->postJson('/api/auth/password-otp/send', ['phone' => '01712345678'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_expired_codes_and_exhausted_attempts_are_rejected(): void
    {
        $phone = '01712345678';
        $this->postJson('/api/auth/password-otp/send', ['phone' => $phone])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => '000000'])->assertUnprocessable();
        }
        $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => $this->otp])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/password-otp/send', ['phone' => $phone])->assertOk();
        $this->travel(301)->seconds();
        $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => $this->otp])->assertUnprocessable();
    }

    public function test_provider_rejection_does_not_create_a_challenge(): void
    {
        $this->rejectSms = true;
        $this->postJson('/api/auth/password-otp/send', ['phone' => '01712345678'])->assertStatus(503);
        $this->assertNull(Cache::get('password_otp:'.hash('sha256', '8801712345678')));
    }

    public function test_reset_tokens_expire_and_cannot_target_another_phone(): void
    {
        $phone = '01712345678';
        $this->postJson('/api/auth/password-otp/send', ['phone' => $phone])->assertOk();
        $token = $this->postJson('/api/auth/password-otp/verify', ['phone' => $phone, 'otp' => $this->otp])->assertOk()->json('reset_token');
        User::create(['phone' => '01812345678', 'email' => 'other@example.com', 'password' => 'other-password']);
        $body = ['phone' => '01812345678', 'reset_token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->postJson('/api/auth/password-otp/reset', $body)->assertUnprocessable();
        $this->travel(601)->seconds();
        $this->postJson('/api/auth/password-otp/reset', array_merge($body, ['phone' => $phone]))->assertUnprocessable();
    }
}
