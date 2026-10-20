<?php

namespace Tests\Feature;

use App\Models\{Area, Category, Event, Offer, Organization, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PublicCollectionAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('timestamp', fn ($date, $time) => "$date $time", 2);
        foreach ([new Offer, new Event, new User, new Organization, new Category, new Area] as $model) {
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

    public function test_availability_includes_every_public_item_and_excludes_hidden_items(): void
    {
        foreach (['offers' => Offer::class, 'events' => Event::class] as $path => $model) {
            for ($index = 0; $index < 101; $index++) {
                $model::create(['status' => 'published', 'category_id' => 1]);
            }
            $model::create([
                'status' => 'expired', 'category_id' => 2, 'category_ids' => [2, 3],
                'subcategory_id' => 4, 'subcategory_ids' => [4, 5], 'area_id' => 6,
                'attributes' => [['attribute_id' => 7, 'value_ids' => [8]]],
            ]);
            foreach (['draft', 'scheduled', 'archived', 'inactive'] as $status) {
                $model::create(['status' => $status, 'category_id' => 99]);
            }
            $deleted = $model::create(['status' => 'published', 'category_id' => 99]);
            $deleted->delete();

            $response = $this->getJson("/api/public/$path?availability=1&limit=1&offset=100")
                ->assertOk()->assertJsonCount(102, 'availability');
            $items = collect($response->json('availability'));
            $this->assertFalse($items->contains(fn ($item) => (int) $item['category_id'] === 99));
            $last = $items->first(fn ($item) => (int) $item['category_id'] === 2);
            $this->assertSame([2, 3], $last['category_ids']);
            $this->assertSame([4, 5], $last['subcategory_ids']);
            $this->assertSame([8], $last['attributes'][0]['value_ids']);
        }
    }

    public function test_store_availability_uses_visible_stores_and_scopes_filter_values(): void
    {
        $category = Category::create(['name' => 'Food']);
        $store = User::create([
            'role' => 'organization', 'status' => 'active', 'is_verified' => true,
            'categories' => [$category->id], 'subcategory_ids' => [4], 'area_id' => 6,
        ]);
        User::create([
            'role' => 'organization', 'status' => 'inactive', 'is_verified' => true,
            'categories' => [99], 'subcategory_ids' => [99], 'area_id' => 99,
        ]);
        Offer::create([
            'organization_id' => $store->id, 'category_id' => $category->id, 'status' => 'published',
            'attributes' => [['attribute_id' => 7, 'value_ids' => [8]]],
        ]);
        $this->getJson('/api/public/organizations?availability=1&limit=1')
            ->assertOk()->assertJsonPath('availability.categories', [$category->id])
            ->assertJsonPath('availability.subcategories', [4])
            ->assertJsonPath("availability.category_subcategories.{$category->id}", [4])
            ->assertJsonPath('availability.areas', fn ($ids) => array_map('intval', $ids) === [6])
            ->assertJsonPath('availability.attributes.7', [8]);
        $this->getJson("/api/public/organizations?availability=1&category_id={$category->id}&subcategory_id=99")
            ->assertOk()->assertJsonPath('availability.categories', [])
            ->assertJsonPath('availability.areas', []);
    }
}
