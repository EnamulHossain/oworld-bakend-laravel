<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OptionalOrganizationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_can_log_in_without_verification_documents(): void
    {
        User::factory()->create([
            'role' => 'organization',
            'username' => 'optional-docs-store',
            'organization_name' => 'Optional Documents Store',
            'password' => 'StorePassword123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'optional-docs-store',
            'password' => 'StorePassword123',
        ])->assertOk()
            ->assertJsonPath('requires_organization_verification', false)
            ->assertJsonPath('user.requires_organization_verification', false);
    }

    public function test_verification_details_can_be_omitted_without_verifying_the_store(): void
    {
        $user = User::factory()->create(['role' => 'organization']);
        $organization = Organization::create([
            'user_id' => $user->id,
            'name' => 'Optional Documents Store',
            'status' => 'pending_approval',
            'verification_status' => 'not_submitted',
            'is_verified' => false,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/organization/verification', [])->assertOk();

        $this->assertDatabaseHas('organization_verifications', [
            'organization_id' => $organization->id,
            'owner_full_name' => null,
            'nid_no' => null,
            'trade_license_valid_until' => null,
        ]);
        $this->assertDatabaseCount('organization_documents', 0);
        $this->assertFalse((bool) $organization->fresh()->is_verified);
    }
}
