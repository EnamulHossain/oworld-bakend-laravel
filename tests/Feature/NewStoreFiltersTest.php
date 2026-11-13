<?php

namespace Tests\Feature;

use App\Models\{Attribute, AttributeValue, Category, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class NewStoreFiltersTest extends TestCase
{
    public function test_new_stores_select_all_matching_values_without_changing_existing_stores(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Attribute, new AttributeValue, new Category] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) $table->text($field)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $category = Category::create(['name' => 'Food']);
        $matching = Attribute::create(['name' => 'Meals', 'types' => ['store', 'event'],
            'category_ids' => [$category->id], 'subcategory_ids' => [20]]);
        $yes = $matching->values()->create(['value' => 'Lunch']);
        $no = $matching->values()->create(['value' => 'Dinner']);
        $global = Attribute::create(['name' => 'Global', 'type' => 'store']);
        $globalValue = $global->values()->create(['value' => 'Available']);
        foreach ([['types' => ['event']], ['types' => ['store'], 'category_ids' => [99]],
            ['types' => ['store'], 'subcategory_ids' => [21]]] as $data) {
            Attribute::create(['name' => 'Unrelated', ...$data])->values()->create(['value' => 'Other']);
        }

        $expected = [
            ['attribute_id' => $matching->id, 'value_ids' => [$yes->id, $no->id]],
            ['attribute_id' => $global->id, 'value_ids' => [$globalValue->id]],
        ];
        foreach ([['Food'], [(string) $category->id], [['id' => $category->id, 'name' => 'Food']]] as $categories) {
            $store = User::create(['role' => 'organization', 'categories' => $categories,
                'subcategory_ids' => [20], 'store_filters' => [['attribute_id' => $matching->id, 'value_ids' => []]]]);
            $this->assertSame($expected, $store->fresh()->store_filters);
        }
        $legacy = User::create(['role' => 'organization', 'categories' => ['Food'], 'subcategory_id' => 20]);
        $this->assertSame($expected, $legacy->fresh()->store_filters);
        $customer = User::create(['role' => 'user', 'categories' => ['Food'], 'subcategory_ids' => [20]]);
        $this->assertNull($customer->fresh()->store_filters);

        $legacy->update(['store_filters' => [['attribute_id' => $matching->id, 'value_ids' => [$yes->id]]]]);
        $legacy->update(['organization_name' => 'Updated']);
        $this->assertSame([['attribute_id' => $matching->id, 'value_ids' => [$yes->id]]], $legacy->fresh()->store_filters);
    }
}
