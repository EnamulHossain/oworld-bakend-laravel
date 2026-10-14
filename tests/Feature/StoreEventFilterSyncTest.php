<?php

namespace Tests\Feature;

use App\Models\{Attribute, AttributeValue, User};
use App\Services\StoreEventFilterSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class StoreEventFilterSyncTest extends TestCase
{
    public function test_sync_adds_and_removes_only_the_target_filter_for_stores(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Attribute, new AttributeValue] as $model) {
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
        $attribute = Attribute::create(['name' => 'Event', 'types' => ['event']]);
        $value = $attribute->values()->create(['value' => 'Iftar']);
        $service = app(StoreEventFilterSync::class);
        DB::transaction(fn () => $service->sync($attribute));
        $target = ['attribute_id' => $attribute->id, 'value_ids' => [$value->id]];
        $this->assertSame([$existing, $target], $store->fresh()->store_filters);
        $this->assertSame([$target], $emptyStore->fresh()->store_filters);
        $this->assertSame([$existing], $customer->fresh()->store_filters);

        DB::transaction(fn () => $service->sync($attribute));
        $this->assertSame([$existing, $target], $store->fresh()->store_filters);

        DB::transaction(fn () => $service->sync($attribute, true));
        $this->assertSame([$existing], $store->fresh()->store_filters);
        $this->assertSame([], $emptyStore->fresh()->store_filters);
    }
}
