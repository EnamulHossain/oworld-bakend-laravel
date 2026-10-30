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
        $this->assertCount(3, $service->search('free Dhanmondi'));
        $this->assertSame([$offer->id], array_column(array_filter($service->search('Moon breakfast Dhanmondi'), fn ($result) => $result['type'] === 'offer'), 'id'));
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
        $this->assertSame([$offer->id], array_column(array_filter(app(UniversalSearch::class)->search('lunch gulshan'), fn ($result) => $result['type'] === 'offer'), 'id'));
        $this->assertSame([], app(UniversalSearch::class)->search('lunch banani'));
        $this->assertCount(1, array_filter(app(UniversalSearch::class)->search('moon'), fn ($result) => $result['type'] === 'organization'));
        $this->assertSame(1, User::count());
        $this->assertSame(1, Offer::count());
    }

    public function test_matching_offers_surface_each_eligible_business_once(): void
    {
        $gulshan = Area::create(['name' => 'Gulshan']);
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'area_id' => $gulshan->id];
        $first = User::create($base + ['organization_name' => 'Cafe One', 'username' => 'Cafe One']);
        $second = User::create($base + ['organization_name' => 'Cafe Two']);
        $hidden = User::create(array_replace($base, ['organization_name' => 'Hidden', 'status' => 'inactive']));
        foreach ([$first, $first, $second, $hidden] as $store) {
            Offer::create(['name' => 'BOGO lunch', 'organization_id' => $store->id, 'status' => 'published', 'branch_assignment_status' => 'approved']);
        }
        $results = app(UniversalSearch::class)->search('bogo in gulshan', 15);
        $businesses = array_values(array_filter($results, fn ($result) => $result['type'] === 'organization'));
        $this->assertSame([$first->id, $second->id], array_column($businesses, 'id'));
        $this->assertCount(3, array_filter($results, fn ($result) => $result['type'] === 'offer'));
        $this->assertSame('Gulshan', $businesses[0]['subtitle']);
        $offers = array_values(array_filter($results, fn ($result) => $result['type'] === 'offer'));
        $this->assertSame('Cafe One', $offers[0]['store_name']);
    }

    public function test_multiple_listing_types_are_merged_with_related_stores_and_strict_area_constraints(): void
    {
        $gulshan = Area::create(['name' => 'Gulshan']);
        $banani = Area::create(['name' => 'Banani']);
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true];
        $store = User::create($base + ['organization_name' => 'Moon', 'area_id' => $gulshan->id]);
        $eventStore = User::create($base + ['organization_name' => 'Stage', 'area_id' => $gulshan->id]);
        $elsewhere = User::create($base + ['organization_name' => 'Elsewhere', 'area_id' => $banani->id]);
        User::create($base + ['organization_name' => 'Unrelated', 'area_id' => $gulshan->id]);
        $offer = Offer::create(['name' => 'BOGO lunch', 'organization_id' => $store->id, 'status' => 'published', 'branch_assignment_status' => 'approved']);
        $event = Event::create(['name' => 'Live music', 'organization_id' => $eventStore->id, 'status' => 'published']);
        Offer::create(['name' => 'BOGO elsewhere', 'organization_id' => $elsewhere->id, 'status' => 'published', 'branch_assignment_status' => 'approved']);
        Event::create(['name' => 'Music elsewhere', 'organization_id' => $elsewhere->id, 'status' => 'published']);
        $service = app(UniversalSearch::class);
        foreach (['events and offers in gulshan', 'offers events gulshan', 'events and offers in gulshn'] as $query) {
            $results = $service->search($query, 15);
            $this->assertCount(4, $results, $query);
            $this->assertSame([$offer->id], array_column(array_filter($results, fn ($r) => $r['type'] === 'offer'), 'id'));
            $this->assertSame([$event->id], array_column(array_filter($results, fn ($r) => $r['type'] === 'event'), 'id'));
            $this->assertEqualsCanonicalizing([$store->id, $eventStore->id], array_column(array_filter($results, fn ($r) => $r['type'] === 'organization'), 'id'));
            $this->assertSame(['Gulshan'], array_values(array_unique(array_column($results, 'subtitle'))));
        }
        $this->assertCount(2, $service->search('bogo in gulshan'));
        $this->assertCount(2, $service->search('events in gulshan'));
        $this->assertSame([], $service->search('bogo events in gulshan'));
        $this->assertCount(5, $service->search('directories offers events gulshan', 15));
    }

    public function test_listing_venue_does_not_relocate_its_business_and_matching_branches_are_included(): void
    {
        $gulshan = Area::create(['name' => 'Gulshan']);
        $banani = Area::create(['name' => 'Banani']);
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'branch_mode' => 'independent'];
        $owner = User::create($base + ['organization_name' => 'Banani owner', 'area_id' => $banani->id]);
        $branch = User::create($base + ['organization_name' => 'Gulshan branch', 'area_id' => $gulshan->id, 'parent_org_id' => $owner->id]);
        Offer::create(['name' => 'BOGO', 'organization_id' => $owner->id, 'area_id' => $gulshan->id, 'branch_ids' => [$branch->id], 'status' => 'published', 'branch_assignment_status' => 'approved']);
        Event::create(['name' => 'Visiting show', 'organization_id' => $owner->id, 'area_id' => $gulshan->id, 'status' => 'published']);
        $results = app(UniversalSearch::class)->search('events and offers in gulshan');
        $businesses = array_values(array_filter($results, fn ($r) => $r['type'] === 'organization'));
        $this->assertCount(3, $results);
        $this->assertSame([$branch->id], array_column($businesses, 'id'));
        $this->assertSame('Gulshan', $businesses[0]['subtitle']);
    }

    public function test_word_matches_prefer_intersection_and_fall_back_to_distinct_union(): void
    {
        $gulshan = Area::create(['name' => 'Gulshan']);
        $beauty = Category::create(['name' => 'Beauty', 'status' => 'active']);
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'area_id' => $gulshan->id];
        $common = User::create($base + ['organization_name' => 'Lotus salon', 'categories' => [$beauty->id]]);
        $categoryOnly = User::create($base + ['organization_name' => 'Glow', 'categories' => [$beauty->id]]);
        $nameOnly = User::create($base + ['organization_name' => 'Lotus cafe']);
        $service = app(UniversalSearch::class);
        $ids = fn ($query) => array_column($service->search($query, 15), 'id');
        $this->assertEqualsCanonicalizing([$common->id, $categoryOnly->id], $ids('beauty items'));
        $this->assertSame([$common->id], $ids('beauty lotus'));
        $this->assertEqualsCanonicalizing([$common->id, $categoryOnly->id, $nameOnly->id], $ids('beauty cafe'));
        $this->assertEqualsCanonicalizing([$common->id, $categoryOnly->id], $ids('beauty beauty items in gulshan'));
        $this->assertSame([], $ids('unknownword nonexistent'));
    }

    public function test_only_selected_listing_matches_contribute_related_businesses(): void
    {
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true];
        $first = User::create($base + ['organization_name' => 'First']);
        $second = User::create($base + ['organization_name' => 'Second']);
        $offerBase = ['status' => 'published', 'branch_assignment_status' => 'approved'];
        $common = Offer::create($offerBase + ['name' => 'BOGO lunch', 'organization_id' => $first->id]);
        Offer::create($offerBase + ['name' => 'BOGO dinner', 'organization_id' => $second->id]);
        $service = app(UniversalSearch::class);
        $results = $service->search('bogo lunch');
        $this->assertCount(2, $results);
        $this->assertSame([$first->id], array_column(array_filter($results, fn ($r) => $r['type'] === 'organization'), 'id'));
        $this->assertSame([$common->id], array_column(array_filter($results, fn ($r) => $r['type'] === 'offer'), 'id'));
        $this->assertCount(4, $service->search('bogo unknownword'));
    }

    public function test_unicode_and_spelling_matching(): void
    {
        $this->assertSame(['ধানমন্ডি'], UniversalSearch::words('ধানমন্ডি'));
        $this->assertTrue(UniversalSearch::wordMatches('dhanmodi', 'dhanmondi', true));
        $this->assertTrue(UniversalSearch::wordMatches('cafe', 'cafes', true));
        $this->assertFalse(UniversalSearch::wordMatches('free', 'fee', true));
    }

    public function test_search_excludes_unpublished_listings_and_inactive_business_profiles(): void
    {
        $base = ['role' => 'organization', 'status' => 'active', 'is_verified' => true];
        $visible = User::create($base + ['organization_name' => 'Food Visible']);
        $hidden = User::create($base + ['organization_name' => 'Food Hidden']);
        Organization::create(['user_id' => $hidden->id, 'status' => 'inactive', 'verification_status' => 'approved']);
        $pending = User::create($base + ['organization_name' => 'Food Pending']);
        Organization::create(['user_id' => $pending->id, 'status' => 'active', 'verification_status' => 'pending']);
        foreach (['published', 'active', 'scheduled', 'draft', 'inactive', 'expired', 'archived'] as $status) {
            Offer::create(['name' => 'Food '.$status, 'organization_id' => $visible->id, 'status' => $status, 'branch_assignment_status' => 'approved']);
            Event::create(['name' => 'Food '.$status, 'organization_id' => $visible->id, 'status' => $status]);
        }
        $results = collect(app(UniversalSearch::class)->search('food', PHP_INT_MAX));
        $this->assertSame([$visible->id], $results->where('type', 'organization')->pluck('id')->all());
        foreach (['offer', 'event'] as $type) {
            $this->assertEqualsCanonicalizing(['Food active', 'Food published'], $results->where('type', $type)->pluck('title')->all());
        }
    }

    public function test_category_pagination_returns_new_results_and_full_totals(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('timestamp', fn ($date, $time) => "$date $time", 2);
        for ($i = 1; $i <= 23; $i++) {
            User::create(['role' => 'organization', 'status' => 'active', 'is_verified' => true, 'organization_name' => 'Food '.$i, 'branch_mode' => 'independent']);
        }
        $first = $this->getJson('/api/public/search?q=food&limit=10&page=1')->assertOk()->assertJsonPath('totals.organization', 23)->json('results');
        $second = $this->getJson('/api/public/search?q=food&limit=10&page=2&type=organization')->assertOk()->assertJsonPath('totals.organization', 23)->json('results');
        $third = $this->getJson('/api/public/search?q=food&limit=10&page=3&type=organization')->assertOk()->json('results');
        $this->assertCount(10, $first);
        $this->assertCount(10, $second);
        $this->assertCount(3, $third);
        $this->assertCount(23, array_unique(array_column(array_merge($first, $second, $third), 'id')));
        $this->getJson('/api/public/search?q=food&limit=10&page=2&type=offer')->assertOk()->assertJsonCount(0, 'results');
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
