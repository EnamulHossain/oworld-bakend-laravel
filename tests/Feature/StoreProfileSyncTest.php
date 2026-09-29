<?php

namespace Tests\Feature;

use App\Models\{User, Category, Offer};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class StoreProfileSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Category, new Offer] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_admin_and_owner_share_profile_updates_without_changing_verification(): void
    {
        $store = User::create(['role' => 'organization', 'organization_name' => 'Test', 'is_verified' => true]);
        $admin = User::create(['role' => 'admin']);
        $category = Category::create(['name' => 'Food', 'status' => 'active']);
        $subcategory = Category::create(['name' => 'Cafe', 'parent_id' => $category->id, 'status' => 'active']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/store-profiles/{$store->id}", [
            'about' => 'Updated by admin', 'contact_email' => 'contact@example.com',
            'categories' => [(string) $category->id], 'subcategory_ids' => [$subcategory->id],
            'store_tags' => ['Coffee'], 'interior_media' => [['url' => '/cafe.jpg', 'type' => 'image']],
            'is_verified' => false,
        ])->assertOk()->assertJsonPath('profile.about', 'Updated by admin')
            ->assertJsonPath('profile.contact_email', 'contact@example.com')
            ->assertJsonPath('profile.is_verified', true);

        $this->actingAs($store, 'sanctum')->getJson("/api/store-profiles/{$store->id}")
            ->assertOk()->assertJsonPath('profile.store_tags.0', 'Coffee');
        $this->putJson("/api/store-profiles/{$store->id}", ['about' => 'Updated by owner'])->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson("/api/store-profiles/{$store->id}")
            ->assertOk()->assertJsonPath('profile.about', 'Updated by owner')
            ->assertJsonPath('profile.interior_media.0.url', '/cafe.jpg');
    }

    public function test_other_users_cannot_read_or_update_store_profile(): void
    {
        $store = User::create(['role' => 'organization']);
        foreach (['user', 'organization'] as $role) {
            $actor = User::create(['role' => $role]);
            $this->actingAs($actor, 'sanctum')->getJson("/api/store-profiles/{$store->id}")->assertForbidden();
            $this->putJson("/api/store-profiles/{$store->id}", ['about' => 'Unauthorized'])->assertForbidden();
        }
        $this->assertNull($store->fresh()->about);
    }

    public function test_mixed_case_super_admin_can_load_and_update_store(): void
    {
        $store = User::create(['role' => 'organization', 'organization_name' => 'Test']);
        $admin = User::create(['role' => 'superAdmin']);
        $this->actingAs($admin, 'sanctum')->getJson("/api/store-profiles/{$store->id}")
            ->assertOk()->assertJsonPath('profile.organization_name', 'Test');
        $this->putJson("/api/store-profiles/{$store->id}", ['about' => 'Super admin edit'])
            ->assertOk()->assertJsonPath('profile.about', 'Super admin edit');
    }

    public function test_changed_subcategories_must_belong_to_selected_categories(): void
    {
        $store = User::create(['role' => 'organization']);
        $food = Category::create(['name' => 'Food']);
        $beauty = Category::create(['name' => 'Beauty']);
        $salon = Category::create(['name' => 'Salon', 'parent_id' => $beauty->id]);
        $this->actingAs($store, 'sanctum')->putJson("/api/store-profiles/{$store->id}", [
            'categories' => ['Food'], 'subcategory_ids' => [$salon->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('subcategory_ids');
    }

    public function test_existing_imported_classifications_survive_unrelated_edits(): void
    {
        $food = Category::create(['name' => 'Food']);
        $beauty = Category::create(['name' => 'Beauty']);
        $salon = Category::create(['name' => 'Salon', 'parent_id' => $beauty->id]);
        $store = User::create(['role' => 'organization', 'categories' => [$food->id], 'subcategory_ids' => [$salon->id]]);
        $this->actingAs($store, 'sanctum')->putJson("/api/store-profiles/{$store->id}", [
            'about' => 'New description', 'categories' => [(string) $food->id], 'subcategory_ids' => [$salon->id],
        ])->assertOk()->assertJsonPath('profile.subcategory_ids.0', $salon->id);
    }

    public function test_profile_endpoint_requires_authentication_and_a_store_target(): void
    {
        $store = User::create(['role' => 'organization']);
        $this->getJson("/api/store-profiles/{$store->id}")->assertUnauthorized();
        $this->putJson("/api/store-profiles/{$store->id}", ['about' => 'Unauthorized'])->assertUnauthorized();
        $admin = User::create(['role' => 'superadmin']);
        $this->actingAs($admin, 'sanctum')->putJson("/api/store-profiles/{$admin->id}", ['about' => 'Invalid'])->assertNotFound();
    }
}
