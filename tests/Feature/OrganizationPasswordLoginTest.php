<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_can_login_by_username_after_admin_updates_password(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'organization', 'guard_name' => 'sanctum']);

        $admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin-user',
        ]);
        $admin->syncRoles(['admin']);

        $store = User::factory()->create([
            'role' => 'organization',
            'username' => 'demo-store',
            'organization_name' => 'Demo Store',
            'password' => 'old-password',
        ]);
        $store->syncRoles(['organization']);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/organizations/{$store->id}", [
            'password' => 'new-password',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'demo-store',
            'password' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'organization');
    }
}
