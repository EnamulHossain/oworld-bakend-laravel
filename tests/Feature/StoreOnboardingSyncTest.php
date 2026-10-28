<?php
namespace Tests\Feature;

use App\Models\{User, Organization, Category, OrganizationVerification, OrganizationDocument};
use App\Services\StoreOnboardingSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class StoreOnboardingSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Organization, new Category, new OrganizationVerification, new OrganizationDocument] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach (array_unique($model->getFillable()) as $field) $table->text($field)->nullable();
                $table->timestamps(); $table->softDeletes();
            });
        }
    }

    public function test_missing_profiles_are_created_once_without_overwriting_reviews_or_restoring_deleted_profiles(): void
    {
        $store = User::create(['role' => 'organization', 'organization_name' => 'New store', 'is_verified' => false]);
        $sync = app(StoreOnboardingSync::class);
        $organization = $sync->ensure($store);
        $this->assertSame('New store', $organization->name);
        $this->assertSame('not_submitted', $organization->verification_status);
        $organization->update(['verification_status' => 'rejected']);
        $this->assertSame('rejected', $sync->ensure($store)->verification_status);
        $this->assertSame(1, Organization::count());
        $organization->delete();
        $sync->ensure($store);
        $this->assertSame(0, Organization::count());
        $this->assertSame(1, Organization::withTrashed()->count());
        $this->assertNull($sync->ensure(User::create(['role' => 'user'])));
    }

    public function test_onboarding_list_includes_unsubmitted_stores_and_excludes_approved_stores(): void
    {
        $admin = User::create(['role' => 'admin']);
        $sync = app(StoreOnboardingSync::class);
        $pending = $sync->ensure(User::create(['role' => 'organization', 'organization_name' => 'Pending', 'is_verified' => false]));
        $sync->ensure(User::create(['role' => 'organization', 'organization_name' => 'Verified', 'is_verified' => true]));
        $reviewed = $sync->ensure(User::create(['role' => 'organization', 'organization_name' => 'Reviewed', 'is_verified' => false]));
        $reviewed->verification()->create(['status' => 'approved']);
        $response = app(\App\Http\Controllers\Api\AdminStoreOnboardingController::class)->index(\Illuminate\Http\Request::create('/api/admin/store-onboardings'));
        $rows = $response->getData(true)['onboardings'];
        $this->assertCount(1, $rows);
        $this->assertSame($pending->id, $rows[0]['id']);
    }
}
