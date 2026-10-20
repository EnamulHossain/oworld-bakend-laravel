<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('role')->default('user');
            $table->boolean('isphoneverified')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        $this->user = User::create(['phone' => '+8801712345678', 'role' => 'user']);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_authentication_is_required(): void
    {
        $this->postJson('/api/phone-verification/verify', ['phone' => '+8801712345678'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_signup_availability_explains_duplicates_in_all_phone_formats(): void
    {
        foreach (['01712345678', '+8801712345678', '8801712345678'] as $storedPhone) {
            $this->user->phone = $storedPhone;
            $this->user->save();
            $this->postJson('/api/auth/check-availability', ['field' => 'phone', 'value' => '+8801712345678'])
                ->assertOk()->assertJsonPath('available', false)
                ->assertJsonPath('message', 'This phone number is already registered to another account.');
        }
        $this->postJson('/api/auth/check-availability', ['field' => 'phone', 'value' => '+8801812345678'])
            ->assertOk()->assertJsonPath('available', true);
    }

    public function test_valid_phone_is_confirmed_without_otp_or_sms(): void
    {
        Sanctum::actingAs($this->user);
        $this->assertFalse($this->user->fresh()->isphoneverified);
        $this->postJson('/api/phone-verification/verify', ['phone' => '+8801712345678'])
            ->assertOk()->assertJsonPath('isphoneverified', true);
        $this->assertTrue($this->user->fresh()->isphoneverified);
        Http::assertNothingSent();
    }

    public function test_changed_phone_is_saved_with_country_code_and_later_changes_reset_flag(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/phone-verification/verify', ['phone' => '+8801812345678'])->assertOk();
        $user = $this->user->fresh();
        $this->assertSame('+8801812345678', $user->phone);
        $this->assertTrue($user->isphoneverified);
        $user->phone = '+8801912345678';
        $user->save();
        $this->assertFalse($user->fresh()->isphoneverified);
        Http::assertNothingSent();
    }

    public function test_duplicate_and_invalid_phones_are_rejected(): void
    {
        Sanctum::actingAs($this->user);
        User::create(['phone' => '01812345678', 'role' => 'user']);
        foreach (['+8801812345678', '01712345678', '8801712345678', '+8801212345678', '+88017123'] as $phone) {
            $this->postJson('/api/phone-verification/verify', ['phone' => $phone])->assertUnprocessable();
        }
        $this->assertFalse($this->user->fresh()->isphoneverified);
        $this->assertSame('+8801712345678', $this->user->fresh()->phone);
        Http::assertNothingSent();
    }

    public function test_other_account_roles_cannot_use_confirmation(): void
    {
        foreach (['organization', 'admin', 'superAdmin'] as $role) {
            $this->user->role = $role;
            $this->user->save();
            Sanctum::actingAs($this->user);
            $this->postJson('/api/phone-verification/verify', ['phone' => '+8801712345678'])->assertForbidden();
        }
        $this->assertFalse($this->user->fresh()->isphoneverified);
        Http::assertNothingSent();
    }
}
