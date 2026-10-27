<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\{BranchLocationController, BranchManagerController, OrganizationController};
use App\Http\Middleware\RestrictBranchManager;
use App\Models\{User, Offer, StoreLocation, Area};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BranchSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Offer, new StoreLocation, new Area] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) $table->text($field)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::create('branch_managers', function (Blueprint $table) {
            $table->id(); $table->integer('parent_id'); $table->integer('branch_id'); $table->integer('manager_id');
            $table->timestamp('accepted_at')->nullable(); $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
    }

    private function store(array $extra = []): User
    {
        return User::create(array_merge(['role' => 'organization', 'branch_mode' => 'unified', 'status' => 'active'], $extra));
    }

    private function request(User $actor, array $data = [], string $path = '/', string $method = 'PUT'): Request
    {
        $request = Request::create($path, $method, $data);
        $request->setUserResolver(fn () => $actor);
        return $request;
    }

    public function test_locations_do_not_create_profiles_and_save_their_contact_details(): void
    {
        $store = $this->store();
        $response = app(BranchLocationController::class)->update($this->request($store, [
            'branch_mode' => 'unified', 'locations' => [['name' => 'Gulshan', 'phone' => '123', 'status' => 'coming_soon']],
        ]), $store);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, User::count());
        $this->assertSame('Gulshan', $store->locations()->first()->name);
    }

    public function test_super_admin_can_save_store_locations(): void
    {
        $store = $this->store();
        $admin = User::create(['role' => 'superAdmin', 'status' => 'active']);
        $response = app(BranchLocationController::class)->update($this->request($admin, [
            'branch_mode' => 'unified', 'locations' => [['name' => 'Gulshan', 'status' => 'open']],
        ]), $store);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Gulshan', $store->locations()->first()->name);
    }

    public function test_other_store_cannot_modify_locations(): void
    {
        $this->expectException(HttpException::class);
        app(BranchLocationController::class)->update($this->request($this->store(), ['branch_mode' => 'unified', 'locations' => []]), $this->store());
    }

    public function test_location_ids_cannot_be_stolen_from_another_brand(): void
    {
        $store = $this->store();
        $location = StoreLocation::create(['organization_id' => $this->store()->id, 'name' => 'Other', 'status' => 'open']);
        $this->expectException(ValidationException::class);
        app(BranchLocationController::class)->update($this->request($store, ['branch_mode' => 'unified', 'locations' => [['id' => $location->id, 'name' => 'Taken', 'status' => 'open']]]), $store);
    }

    public function test_existing_profiles_cannot_be_silently_converted(): void
    {
        $store = $this->store(['branch_mode' => 'independent']);
        $this->store(['parent_org_id' => $store->id, 'branch_mode' => 'independent']);
        try {
            app(BranchLocationController::class)->update($this->request($store, ['branch_mode' => 'unified', 'locations' => []]), $store);
            $this->fail('Existing family converted');
        } catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $this->assertSame('independent', $store->fresh()->branch_mode);
    }

    public function test_deleting_an_offer_location_does_not_widen_offer_availability(): void
    {
        $store = $this->store();
        $location = $store->locations()->create(['name' => 'Gulshan', 'status' => 'open']);
        Offer::create(['organization_id' => $store->id, 'location_ids' => [$location->id]]);
        try {
            app(BranchLocationController::class)->update($this->request($store, ['branch_mode' => 'unified', 'locations' => []]), $store);
            $this->fail('Referenced location removed');
        } catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $this->assertSame(1, $store->locations()->count());
    }

    public function test_selected_location_offer_stays_one_record_and_all_locations_remains_dynamic(): void
    {
        $store = $this->store();
        $a = $store->locations()->create(['name' => 'Gulshan', 'status' => 'open']);
        $store->locations()->create(['name' => 'Banani', 'status' => 'open']);
        $offer = Offer::create(['organization_id' => $store->id, 'location_ids' => [$a->id]]);
        $this->assertSame(['Gulshan'], array_column($offer->available_at, 'name'));
        $offer->update(['location_ids' => null]);
        $this->assertCount(2, $offer->available_at);
        $this->assertSame(1, Offer::count());
    }

    public function test_offer_assignment_rejects_other_brands_locations(): void
    {
        $store = $this->store();
        $location = $this->store()->locations()->create(['name' => 'Other', 'status' => 'open']);
        $method = new \ReflectionMethod(OrganizationController::class, 'resolveOfferBranchAssignment');
        $this->expectException(ValidationException::class);
        $method->invoke(app(OrganizationController::class), $store, [$store->id], $this->request($store, ['location_ids' => [$location->id]]));
    }

    public function test_branch_manager_can_post_but_cannot_modify_master_settings_and_revocation_is_immediate(): void
    {
        $parent = $this->store(['branch_mode' => 'independent']);
        $branch = $this->store(['branch_mode' => 'independent', 'parent_org_id' => $parent->id]);
        $manager = $this->store();
        $id = DB::table('branch_managers')->insertGetId(['parent_id' => $parent->id, 'branch_id' => $branch->id, 'manager_id' => $manager->id, 'accepted_at' => now()]);
        $branch->withAccessToken(new PersonalAccessToken(['abilities' => ['branch-manager:'.$id]]));
        Auth::guard('sanctum')->setUser($branch);
        $request = $this->request($branch, [], '/api/organization/posts', 'POST');
        $request->headers->set('Authorization', 'Bearer test');
        $middleware = new RestrictBranchManager;
        $this->assertSame('allowed', $middleware->handle($request, fn () => 'allowed'));
        $request = $this->request($branch, [], '/api/organization/branches');
        $request->headers->set('Authorization', 'Bearer test');
        try { $middleware->handle($request, fn () => 'bad'); $this->fail('Master settings allowed'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $request = $this->request($branch, [], '/api/store-profiles/'.$parent->id);
        $request->headers->set('Authorization', 'Bearer test');
        try { $middleware->handle($request, fn () => 'bad'); $this->fail('Other branch profile allowed'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $request = $this->request($branch, [], '/api/store-profiles/'.$branch->id);
        $request->headers->set('Authorization', 'Bearer test');
        $this->assertSame('allowed', $middleware->handle($request, fn () => 'allowed'));
        DB::table('branch_managers')->delete($id);
        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => 'bad');
    }

    public function test_every_independent_branch_can_resolve_the_mother_and_siblings(): void
    {
        $parent = $this->store(['branch_mode' => 'independent']);
        $a = $this->store(['branch_mode' => 'independent', 'parent_org_id' => $parent->id]);
        $b = $this->store(['branch_mode' => 'independent', 'parent_org_id' => $parent->id]);
        $this->store(['branch_mode' => 'independent']);
        $this->assertEqualsCanonicalizing([$parent->id, $b->id], $a->branchProfiles()->whereKeyNot($a->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $parent->branchProfiles()->whereKeyNot($parent->id)->pluck('id')->all());
    }

    public function test_invitation_requires_acceptance_and_only_the_invitee_can_open_the_branch(): void
    {
        $parent = $this->store(['branch_mode' => 'independent']);
        $branch = $this->store(['branch_mode' => 'independent', 'parent_org_id' => $parent->id]);
        $manager = $this->store(['email' => 'manager@example.test']);
        $controller = app(BranchManagerController::class);
        $controller->invite($this->request($parent, ['branch_id' => $branch->id, 'email' => $manager->email]));
        $id = DB::table('branch_managers')->value('id');
        try { $controller->session($this->request($manager), $id); $this->fail('Pending invite opened branch'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $controller->accept($this->request($manager), $id);
        $response = $controller->session($this->request($manager), $id)->getData(true);
        $this->assertSame($branch->id, $response['user']['id']);
        $token = PersonalAccessToken::findToken($response['token']);
        $this->assertSame(['branch-manager:'.$id], $token->abilities);
        $this->assertTrue($token->expires_at->isFuture());
        $this->expectException(HttpException::class);
        $controller->session($this->request($this->store()), $id);
    }
}
