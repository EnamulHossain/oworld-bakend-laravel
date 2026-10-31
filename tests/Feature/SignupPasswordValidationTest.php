<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SignupPasswordValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->softDeletes();
        });
    }

    private function signupData(string $password): array
    {
        return [
            'username' => 'passwordtest',
            'password' => $password,
            'role' => 'user',
            'first_name' => 'Test',
            'phone' => '+8801712345678',
            'gender' => 'male',
            'otp_verification_token' => str_repeat('a', 64),
        ];
    }

    public function test_eight_or_more_characters_pass_without_complexity_requirements(): void
    {
        foreach (['abcdefgh', '12345678', '!!!!!!!!', 'abcdefghijk'] as $password) {
            // An unverified OTP stops registration after field validation, without creating an account.
            $this->postJson('/api/auth/register', $this->signupData($password))
                ->assertUnprocessable()
                ->assertJsonMissingValidationErrors(['password', 'email', 'last_name'])
                ->assertJsonPath('message', 'Phone verification is required before registration.');
        }
    }

    public function test_short_passwords_are_rejected(): void
    {
        foreach (['abcdefg', '1234567', ''] as $password) {
            $this->postJson('/api/auth/register', $this->signupData($password))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }
    }

    public function test_organization_email_is_still_required(): void
    {
        $data = $this->signupData('abcdefgh');
        $data['role'] = 'organization';
        $this->postJson('/api/auth/register', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonMissingValidationErrors('password');
    }
}
