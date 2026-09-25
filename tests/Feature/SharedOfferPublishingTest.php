<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\OrganizationController;
use App\Models\{User, Category, Offer, Event, StorePost, Attribute, AttributeValue};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SharedOfferPublishingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolated schema: the application's historical MySQL migrations cannot run on SQLite.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('timestamp', fn ($date, $time) => "$date $time", 2);
        foreach ([new User, new Category, new Offer, new Event, new StorePost, new Attribute, new AttributeValue] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) {
                    if (str_ends_with($field, '_id') || in_array($field, ['sort_order', 'pin_order'])) $table->integer($field)->nullable();
                    else $table->text($field)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function store(): User
    {
        return User::create(['role' => 'organization', 'username' => 'store', 'organization_name' => 'Store', 'categories' => [], 'subcategory_ids' => []]);
    }

    private function request(User $actor, array $data): Request
    {
        $request = Request::create('/api/offers', 'POST', $data);
        $request->setUserResolver(fn () => $actor);
        return $request;
    }

    private function payload(User $store): array
    {
        return ['name' => 'Lunch offer', 'organization_id' => $store->id,
            'start_date' => '2030-01-01', 'end_date' => '2030-01-03', 'status' => 'draft',
            'start_time' => '10:00', 'end_time' => '18:00', 'offer_type' => 'exclusive',
            'images' => ['/lunch.jpg'], 'videos' => [], 'category_ids' => [], 'subcategory_ids' => [],
            'branch_ids' => [$store->id], 'create_post' => true];
    }

    public function test_admin_create_and_edits_keep_one_post_for_the_selected_store(): void
    {
        $admin = User::create(['role' => 'admin', 'username' => 'admin']);
        $store = $this->store();
        $controller = app(OrganizationController::class);
        $created = $controller->storeOffer($this->request($admin, $this->payload($store)));
        $this->assertSame(201, $created->getStatusCode());
        $offer = Offer::firstOrFail();
        $this->assertSame($store->id, (int) $offer->organization_id);
        $this->assertSame($admin->id, (int) $offer->created_by);
        $this->assertSame('10:00:00', $offer->start_time);
        $this->assertSame('exclusive', $offer->offer_type);
        $this->assertSame('approved', $offer->branch_assignment_status);
        for ($i = 0; $i < 2; $i++) {
            $controller->updateOffer($this->request($admin, array_merge($this->payload($store), ['name' => 'Updated lunch', 'thumbnail' => '/new.jpg', 'images' => ['/new.jpg']])), $offer->fresh());
        }
        $this->assertSame(1, StorePost::count());
        $post = StorePost::firstOrFail();
        $this->assertSame($store->id, (int) $post->organization_id);
        $this->assertSame('Updated lunch', $post->title);
        $this->assertSame('/new.jpg', $post->image);
    }

    public function test_multiple_categories_subcategories_and_branches_are_saved(): void
    {
        $admin = User::create(['role' => 'admin']);
        $store = $this->store();
        $child = User::create(['role' => 'organization', 'parent_org_id' => $store->id]);
        $a = Category::create(['name' => 'Food', 'status' => 'active']);
        $b = Category::create(['name' => 'Drinks', 'status' => 'active']);
        $sa = Category::create(['name' => 'Lunch', 'status' => 'active', 'parent_id' => $a->id]);
        $sb = Category::create(['name' => 'Tea', 'status' => 'active', 'parent_id' => $b->id]);
        $store->update(['categories' => ['Food', 'Drinks'], 'subcategory_ids' => [$sa->id, $sb->id]]);
        app(OrganizationController::class)->storeOffer($this->request($admin, array_merge($this->payload($store), [
            'category_ids' => [$a->id, $b->id], 'subcategory_ids' => [$sa->id, $sb->id], 'branch_ids' => [$store->id, $child->id],
        ])));
        $offer = Offer::firstOrFail();
        $this->assertSame([$a->id, $b->id], $offer->category_ids);
        $this->assertSame([$sa->id, $sb->id], $offer->subcategory_ids);
        $this->assertSame([$store->id, $child->id], $offer->branch_ids);
    }

    public function test_store_owner_cannot_create_for_a_different_store(): void
    {
        $store = $this->store();
        $other = User::create(['role' => 'organization']);
        $payload = $this->payload($other);
        $payload['branch_ids'] = [$store->id];
        app(OrganizationController::class)->storeOffer($this->request($store, $payload));
        $this->assertSame($store->id, (int) Offer::firstOrFail()->organization_id);
        $this->assertSame($store->id, (int) StorePost::firstOrFail()->organization_id);
    }

    public function test_unrelated_branch_is_rejected_before_creating_an_offer(): void
    {
        $admin = User::create(['role' => 'admin']);
        $store = $this->store();
        $other = User::create(['role' => 'organization']);
        try {
            app(OrganizationController::class)->storeOffer($this->request($admin, array_merge($this->payload($store), ['branch_ids' => [$other->id]])));
            $this->fail('Unrelated branch accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('branch_ids', $exception->errors());
            $this->assertSame(0, Offer::count());
        }
    }
    public function test_store_cannot_edit_another_stores_offer(): void
    {
        $admin = User::create(['role' => 'admin']);
        $store = $this->store();
        $other = User::create(['role' => 'organization']);
        $controller = app(OrganizationController::class);
        $controller->storeOffer($this->request($admin, $this->payload($store)));
        $this->expectException(HttpException::class);
        $controller->updateOffer($this->request($other, ['name' => 'Unauthorized']), Offer::firstOrFail());
    }

    public function test_event_edits_from_admin_and_owner_update_the_same_post(): void
    {
        $admin = User::create(['role' => 'admin']);
        $store = $this->store();
        $controller = app(OrganizationController::class);
        $payload = ['name' => 'Event', 'organization_id' => $store->id,
            'starting_date' => '2030-01-01', 'end_date' => '2030-01-01', 'status' => 'draft',
            'banner' => ['/event.jpg'], 'expiration_date' => '2030-01-05', 'sort_order' => 3];
        $controller->storeEvent($this->request($admin, $payload));
        $event = Event::firstOrFail();
        $this->assertSame($store->id, (int) $event->organization_id);
        $this->assertSame('2030-01-05', $event->expiration_date->format('Y-m-d'));
        $this->assertSame(3, (int) $event->sort_order);
        $controller->updateEvent($this->request($store, ['name' => 'Owner edit', 'pin_event' => true, 'banner' => ['/new.jpg']]), $event);
        $this->assertSame('Owner edit', StorePost::firstOrFail()->title);
        $controller->updateEvent($this->request($admin, ['name' => 'Admin edit']), $event->fresh());
        $this->assertSame(1, StorePost::where('type', 'event')->count());
        $post = StorePost::firstOrFail();
        $this->assertSame('Admin edit', $post->title);
        $this->assertSame('/new.jpg', $post->image);
        $this->assertTrue($post->is_pinned);
    }

    public function test_event_update_rejects_another_store_owner(): void
    {
        $store = $this->store();
        $other = User::create(['role' => 'organization']);
        $event = Event::create(['organization_id' => $store->id, 'name' => 'Private edit']);
        $this->expectException(HttpException::class);
        app(OrganizationController::class)->updateEvent($this->request($other, ['name' => 'Changed']), $event);
    }

}
