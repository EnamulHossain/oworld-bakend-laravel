<?php

namespace Tests\Feature;

use App\Services\GenNetSmsService;
use Tests\TestCase;

class SignupOtpTest extends TestCase
{
    public function test_preview_skips_sms_and_verifies_the_generated_code(): void
    {
        config(['services.otp.test_mode' => true]);
        $this->mock(GenNetSmsService::class)->shouldNotReceive('send');
        $phone = '+8801812345678';
        $response = $this->postJson('/api/auth/signup-otp/send', ['phone' => $phone, 'sms_enabled' => false])->assertOk();
        $otp = $response->json('test_otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => '000000'])->assertUnprocessable();
        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => $otp])->assertOk();
    }

    public function test_production_cannot_enable_preview_or_send_sms_when_disabled(): void
    {
        $this->app->instance('env', 'production');
        config(['services.otp.test_mode' => true]);
        $this->mock(GenNetSmsService::class)->shouldNotReceive('send');
        $this->postJson('/api/auth/signup-otp/send', ['phone' => '+8801912345678', 'sms_enabled' => false])
            ->assertUnprocessable()->assertJsonMissingPath('test_otp');
    }

    public function test_preview_requires_server_opt_in(): void
    {
        config(['services.otp.test_mode' => false]);
        $this->mock(GenNetSmsService::class)->shouldNotReceive('send');
        $this->postJson('/api/auth/signup-otp/send', ['phone' => '+8801612345678', 'sms_enabled' => false])
            ->assertUnprocessable()->assertJsonMissingPath('test_otp');
    }

    public function test_generated_test_code_must_match_and_cannot_be_reused(): void
    {
        $phone = '+8801712345678';
        $otp = '';
        $this->mock(GenNetSmsService::class, function ($mock) use (&$otp) {
            $mock->shouldReceive('send')->once()->andReturnUsing(function ($phone, $message, $reference) use (&$otp) {
                preg_match('/verification code is (\d{6})/', $message, $matches);
                $otp = $matches[1] ?? '';
                return [];
            });
        });
        $response = $this->postJson('/api/auth/signup-otp/send', ['phone' => $phone])->assertOk();
        $response->assertJsonMissingPath('test_otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('otp');
        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => $otp])
            ->assertOk()->assertJsonStructure(['verification_token']);
        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => $otp])
            ->assertUnprocessable()->assertJsonValidationErrors('otp');
    }
}
