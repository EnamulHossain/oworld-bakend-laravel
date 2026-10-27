<?php

namespace Tests\Feature;

use App\Models\{Area, Attribute, AttributeValue, Category, Event, Offer, Organization, User};
use App\Services\UniversalSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class UniversalSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new Area, new Category, new Attribute, new AttributeValue, new User, new Organization, new Offer, new Event, new \App\Models\StoreLocation] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach (array_unique($model->getFillable()) as $field) $table->text($field)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_search_combines_area_category_subcategory_and_selected_filter_values_in_any_order(): void
    {
        $area = Area::create(['name' => 'Dhanmondi']);
        $food = Category::create(['name' => 'Food', 'status' => 'active']);
        $cafe = Category::create(['name' => 'Cafes', 'parent_id' => $food->id, 'status' => 'active']);
        $filter = Attribute::create(['name' => 'Amenities', 'type' => 'store', 'status' => 'published']);
        $wifi = AttributeValue::create(['attribute_id' => $filter->id, 'value' => 'WiFi']);
        AttributeValue::create(['attribute_id' => $filter->id, 'value' => 'Parking']);
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'categories' => [$food->id], 'subcategory_ids' => [$cafe->id]];
        $store = User::create($base + ['organization_name' => 'Moon Coffee', 'about' => 'Parking is unknown', 'area_id' => $area->id, 'store_filters' => [['attribute_id' => $filter->id, 'value_ids' => [$wifi->id]]]]);
        User::create($base + ['organization_name' => 'Elsewhere Coffee']);
        User::create(array_replace($base, ['organization_name' => 'Hidden Coffee', 'status' => 'inactive', 'area_id' => $area->id]));
        foreach (['Dhanmondi', 'cafe in dhanmodi', 'food wifi Dhanmondi cafe', 'Dhanmondi Moon', 'amenities cafes'] as $query) {
            $this->assertSame([$store->id], array_column(app(UniversalSearch::class)->search($query), 'id'), $query);
        }
        $this->assertSame([], app(UniversalSearch::class)->search('parking dhanmondi'));
    }

    public function test_listings_use_their_own_filters_and_approved_branch_areas_and_exclude_hidden_content(): void
    {
        $area = Area::create(['name' => 'Dhanmondi']);
        $store = User::create(['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'organization_name' => 'Moon Coffee', 'area_id' => $area->id]);
        $filter = Attribute::create(['name' => 'Admission', 'types' => ['offer', 'event'], 'status' => 'published']);
        $free = AttributeValue::create(['attribute_id' => $filter->id, 'value' => 'Free']);
        $base = ['organization_id' => $store->id, 'status' => 'published', 'attributes' => [['attribute_id' => $filter->id, 'value_ids' => [$free->id]]]];
        $offer = Offer::create($base + ['name' => 'Breakfast Deal', 'branch_assignment_status' => 'approved', 'branch_ids' => [$store->id]]);
        Event::create($base + ['name' => 'Jazz Night']);
        Offer::create($base + ['name' => 'Unapproved Deal', 'branch_assignment_status' => 'pending']);
        Event::create(array_replace($base, ['name' => 'Old Concert', 'end_date' => now()->subDays(2)->toDateString()]));
        $service = app(UniversalSearch::class);
        $this->assertCount(2, $service->search('free Dhanmondi'));
        $this->assertSame([$offer->id], array_column($service->search('Moon breakfast Dhanmondi'), 'id'));
        $this->assertSame([], $service->search('unapproved'));
        $this->assertSame([], $service->search('old concert'));
        $this->assertSame('event', $service->search('jazz free')[0]['type']);
    }

    public function test_independent_business_branches_appear_separately_after_strict_area_matching(): void
    {
        $area = Area::create(['name' => 'Dhanmondi']);
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'organization_name' => 'Moon', 'branch_mode' => 'independent', 'area_id' => $area->id];
        $parent = User::create($base);
        User::create($base + ['parent_org_id' => $parent->id]);
        User::create(array_replace($base, ['parent_org_id' => $parent->id, 'area_id' => null]));
        $results = app(UniversalSearch::class)->search('moon dhanmondi');
        $this->assertCount(2, $results);
        $this->assertArrayNotHasKey('other_matching_branches', $results[0]);
    }

    public function test_unified_brand_is_found_by_locations_without_creating_duplicate_profiles_or_offers(): void
    {
        $gulshan = Area::create(['name' => 'Gulshan']);
        $banani = Area::create(['name' => 'Banani']);
        $store = User::create(['role' => 'organization', 'branch_mode' => 'unified', 'status' => 'active', 'is_verified' => true, 'organization_name' => 'Moon']);
        $a = $store->locations()->create(['name' => 'Gulshan', 'area_id' => $gulshan->id, 'status' => 'open']);
        $store->locations()->create(['name' => 'Banani', 'area_id' => $banani->id, 'status' => 'open']);
        $offer = Offer::create(['name' => 'Lunch', 'organization_id' => $store->id, 'status' => 'published', 'branch_assignment_status' => 'approved', 'location_ids' => [$a->id]]);
        $this->assertSame([$store->id], array_column(app(UniversalSearch::class)->search('moon banani'), 'id'));
        $this->assertSame([$offer->id], array_column(app(UniversalSearch::class)->search('lunch gulshan'), 'id'));
        $this->assertSame([], app(UniversalSearch::class)->search('lunch banani'));
        $this->assertCount(1, array_filter(app(UniversalSearch::class)->search('moon'), fn ($result) => $result['type'] === 'organization'));
        $this->assertSame(1, User::count());
        $this->assertSame(1, Offer::count());
    }

    public function test_unicode_and_spelling_matching(): void
    {
        $this->assertSame(['ধানমন্ডি'], UniversalSearch::words('ধানমন্ডি'));
        $this->assertTrue(UniversalSearch::wordMatches('dhanmodi', 'dhanmondi', true));
        $this->assertTrue(UniversalSearch::wordMatches('cafe', 'cafes', true));
        $this->assertFalse(UniversalSearch::wordMatches('free', 'fee', true));
    }

    public function test_homepage_endpoint_returns_relationship_matches_and_validates_limits(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('timestamp', fn ($date, $time) => "$date $time", 2);
        $area = Area::create(['name' => 'Dhanmondi']);
        $store = User::create(['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'organization_name' => 'Moon', 'branch_mode' => 'independent', 'area_id' => $area->id]);
        $this->getJson('/api/public/search?q=dhanmodi')->assertOk()->assertJsonPath('results.0.id', $store->id);
        $this->getJson('/api/public/search?q=moon&limit=-1')->assertUnprocessable();
    }
}
