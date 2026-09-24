<?php

namespace Tests\Feature;

use Tests\TestCase;

class SignupOtpTest extends TestCase
{
    public function test_generated_test_code_must_match_and_cannot_be_reused(): void
    {
        $phone = '+8801712345678';
        $response = $this->postJson('/api/auth/signup-otp/send', ['phone' => $phone])->assertOk();
        $otp = $response->json('test_otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('otp');
        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => $otp])
            ->assertOk()->assertJsonStructure(['verification_token']);
        $this->postJson('/api/auth/signup-otp/verify', ['phone' => $phone, 'otp' => $otp])
            ->assertUnprocessable()->assertJsonValidationErrors('otp');
    }
}
