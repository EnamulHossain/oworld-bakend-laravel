<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SignupOtpController;
use Tests\TestCase;

class SignupRateLimitTest extends TestCase
{
    public function test_signup_requests_have_independent_rate_limits(): void
    {
        $this->mock(AuthController::class, function ($mock) {
            $mock->shouldReceive('checkAvailability')->andReturn(response()->json(['available' => true]));
        });
        $this->mock(SignupOtpController::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(response()->json(['success' => true]));
            $mock->shouldReceive('verify')->andReturn(response()->json(['success' => true]));
        });

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson('/api/auth/check-availability')->assertOk();
        }
        $this->postJson('/api/auth/check-availability')->assertStatus(429);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/signup-otp/send')->assertOk();
        }
        $this->postJson('/api/auth/signup-otp/send')->assertStatus(429);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/signup-otp/verify')->assertOk();
        }
        $this->postJson('/api/auth/signup-otp/verify')->assertStatus(429);
    }
}
