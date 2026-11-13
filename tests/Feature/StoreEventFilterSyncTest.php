<?php

namespace Tests\Feature;

use App\Models\{Attribute, AttributeValue, Category, User, Offer, Event};
use App\Services\StoreEventFilterSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class StoreEventFilterSyncTest extends TestCase
{
    public function test_sync_automatically_selects_values_and_preserves_existing_store_choices(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Attribute, new AttributeValue, new Category, new Offer, new Event] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $existing = ['attribute_id' => 999, 'value_ids' => [8, 9]];
        $store = User::create(['role' => 'organization', 'store_filters' => [$existing]]);
        $emptyStore = User::create(['role' => 'organization']);
        $customer = User::create(['role' => 'user', 'store_filters' => [$existing]]);
        $attribute = Attribute::create(['name' => 'Common', 'types' => ['event', 'store']]);
        $value = $attribute->values()->create(['value' => 'Iftar']);
        $removedValue = $attribute->values()->create(['value' => 'Removed']);
        $target = ['attribute_id' => $attribute->id, 'value_ids' => [$value->id, $removedValue->id]];
        $store->update(['store_filters' => [$existing, $target]]);
        $removedValue->delete();
        $dinner = $attribute->values()->create(['value' => 'Dinner']);
        $service = app(StoreEventFilterSync::class);
        DB::transaction(fn () => $service->sync($attribute));
        $target = ['attribute_id' => $attribute->id, 'value_ids' => [$value->id]];
        $this->assertSame([$existing, $target], $store->fresh()->store_filters);
        $emptyTarget = ['attribute_id' => $attribute->id, 'value_ids' => [$value->id, $dinner->id]];
        $this->assertSame([$emptyTarget], $emptyStore->fresh()->store_filters);
        $this->assertSame([$existing], $customer->fresh()->store_filters);

        DB::transaction(fn () => $service->sync($attribute));
        $this->assertSame([$existing, $target], $store->fresh()->store_filters);
        $this->assertSame([$emptyTarget], $emptyStore->fresh()->store_filters);

        // Event filters remain available in store profiles for event creation.
        $attribute->update(['types' => ['event']]);
        DB::transaction(fn () => $service->sync($attribute));
        $this->assertSame([$existing, $target], $store->fresh()->store_filters);
        $this->assertSame([$emptyTarget], $emptyStore->fresh()->store_filters);

        // Adding Store automatically selects every value.
        $attribute->update(['types' => ['store', 'offer']]);
        DB::transaction(fn () => $service->sync($attribute));
        $this->assertSame([$existing, $target], $store->fresh()->store_filters);
        $this->assertSame([$emptyTarget], $emptyStore->fresh()->store_filters);
        $this->assertSame([$existing], $customer->fresh()->store_filters);

        $attribute->update(['types' => ['store', 'offer', 'event']]);
        $posts = [];
        foreach ([Offer::class, Event::class] as $model) {
            foreach (['published', 'draft', 'archived', 'expired', 'scheduled', 'canceled'] as $status) {
                $posts[] = $model::create(['status' => $status, 'attributes' => [$existing, $target]]);
            }
            $deleted = $model::create(['status' => 'draft', 'attributes' => [$existing, $target]]);
            $deleted->delete();
            $posts[] = $deleted;
        }

        // A filter's own status must not erase selections from any post status.
        $attribute->update(['status' => 'draft']);
        DB::transaction(fn () => $service->sync($attribute));
        foreach ($posts as $post) $this->assertSame([$existing, $target], $post->fresh()->getAttribute('attributes'));

        // Removing Offer cleans all offers, while events retain their selections.
        $attribute->update(['types' => ['store', 'event']]);
        DB::transaction(fn () => $service->sync($attribute));
        foreach ($posts as $post) {
            $expected = $post instanceof Offer ? [$existing] : [$existing, $target];
            $this->assertSame($expected, $post->fresh()->getAttribute('attributes'));
        }

        // Removing Event cleans all events as well.
        $attribute->update(['types' => ['store']]);
        DB::transaction(fn () => $service->sync($attribute));
        foreach ($posts as $post) $this->assertSame([$existing], $post->fresh()->getAttribute('attributes'));

        DB::transaction(fn () => $service->sync($attribute, true));
        $this->assertSame([$existing], $store->fresh()->store_filters);
        $this->assertSame([], $emptyStore->fresh()->store_filters);

        // Only stores matching both restrictions receive the filter and values.
        $category = Category::create(['name' => 'Food']);
        $attribute->update(['category_ids' => [$category->id], 'subcategory_ids' => [20]]);
        $store->update(['categories' => ['Food'], 'subcategory_ids' => [20]]);
        $emptyStore->update(['categories' => [(string) $category->id], 'subcategory_id' => 20]);
        $otherCategory = User::create(['role' => 'organization', 'categories' => ['Other'], 'subcategory_ids' => [20]]);
        $otherSubcategory = User::create(['role' => 'organization', 'categories' => ['Food'], 'subcategory_ids' => [21]]);
        DB::transaction(fn () => $service->sync($attribute));
        $this->assertSame([$existing, $emptyTarget], $store->fresh()->store_filters);
        $this->assertSame([$emptyTarget], $emptyStore->fresh()->store_filters);
        $this->assertSame([], $otherCategory->fresh()->store_filters ?? []);
        $this->assertSame([], $otherSubcategory->fresh()->store_filters ?? []);

        $attribute->update(['subcategory_ids' => [21]]);
        DB::transaction(fn () => $service->sync($attribute));
        $this->assertSame([$existing], $store->fresh()->store_filters);
        $this->assertSame([], $emptyStore->fresh()->store_filters);
        $this->assertSame([$emptyTarget], $otherSubcategory->fresh()->store_filters);
    }
}
