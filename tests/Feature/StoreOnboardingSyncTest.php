<?php
namespace Tests\Feature;

use App\Models\{User, Organization, Category, OrganizationVerification, OrganizationDocument};
use App\Services\StoreOnboardingSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class StoreOnboardingSyncTest extends TestCase
{
    public function test_admin_created_store_is_managed_and_signup_can_be_unmanaged(): void
    {
        $permissions = require database_path('migrations/2025_11_27_055404_create_permission_tables.php');
        $permissions->up();
        $ordinary = User::create(['role' => 'organization', 'organization_name' => 'Self signup', 'ismanagedbyoworld' => false]);
        $this->assertFalse($ordinary->fresh()->ismanagedbyoworld);

        $request = \Illuminate\Http\Request::create('/api/admin/organizations', 'POST', [
            'organization_name' => 'Admin created store', 'password' => 'test-password-123',
            'ismanagedbyoworld' => false,
        ]);
        $response = app(\App\Http\Controllers\Api\AdminController::class)->storeOrganization($request);
        $this->assertSame(201, $response->getStatusCode());
        $managed = User::findOrFail($response->getData(true)['organization']['id']);
        $this->assertTrue($managed->ismanagedbyoworld);
        $managed->update(['organization_name' => 'Renamed store']);
        $this->assertTrue($managed->fresh()->ismanagedbyoworld);
        $this->assertFalse($ordinary->fresh()->ismanagedbyoworld);
    }

    public function test_database_default_remains_true_and_migration_preserves_existing_flags(): void
    {
        $managed = User::create(['role' => 'organization', 'ismanagedbyoworld' => true]);
        $signup = User::create(['role' => 'organization', 'ismanagedbyoworld' => false]);
        $migration = require database_path('migrations/2026_11_13_000003_restore_store_management_default.php');
        $migration->up();
        $id = DB::table('users')->insertGetId(['role' => 'organization']);
        $this->assertTrue(User::findOrFail($id)->ismanagedbyoworld);
        $this->assertTrue($managed->fresh()->ismanagedbyoworld);
        $this->assertFalse($signup->fresh()->ismanagedbyoworld);
    }

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
