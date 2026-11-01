<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PublicController;
use App\Models\{Attribute, User, Category};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PublicStoreFiltersTest extends TestCase
{
    public function test_public_profile_only_returns_published_filter_assignments(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create((new Attribute)->getTable(), function (Blueprint $table) {
            $table->id();
            foreach ((new Attribute)->getFillable() as $field) {
                $table->text($field)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create((new Category)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $wellness = Category::create(['name' => 'Wellness']);
        $food = Category::create(['name' => 'Food']);
        $meditation = Category::create(['name' => 'Meditation', 'parent_id' => $wellness->id]);
        $otherSubcategory = Category::create(['name' => 'Other', 'parent_id' => $wellness->id]);

        $selections = [];
        foreach (['published', 'draft', 'inactive'] as $status) {
            $attribute = Attribute::create(['name' => $status, 'status' => $status, 'types' => ['store']]);
            $selections[] = ['attribute_id' => $attribute->id, 'value_ids' => [10]];
        }
        foreach ([
            ['name' => 'Meal plan', 'types' => ['store', 'offer'], 'category_ids' => [$food->id]],
            ['name' => 'Other subcategory', 'types' => ['store'], 'category_ids' => [$wellness->id], 'subcategory_ids' => [$otherSubcategory->id]],
            ['name' => 'Event only', 'types' => ['event']],
        ] as $data) {
            $attribute = Attribute::create([...$data, 'status' => 'published']);
            $selections[] = ['attribute_id' => $attribute->id, 'value_ids' => [10]];
        }
        $matching = Attribute::create(['name' => 'Meditation type', 'status' => 'published', 'types' => ['store'], 'category_ids' => [$wellness->id], 'subcategory_ids' => [$meditation->id]]);
        $matchingSelection = ['attribute_id' => $matching->id, 'value_ids' => [11]];
        $selections[] = $matchingSelection;
        $store = new User(['categories' => ['Wellness'], 'subcategory_ids' => [$meditation->id], 'store_filters' => $selections]);
        $store->setRelation('area', null);
        $method = new \ReflectionMethod(PublicController::class, 'formatPublicOrganization');
        $result = $method->invoke(app(PublicController::class), $store);

        $this->assertSame([$selections[0], $matchingSelection], $result['store_filters']);
        $this->assertSame($selections, $store->store_filters);
        $store->categories = [(string) $wellness->id];
        $this->assertSame($result['store_filters'], $method->invoke(app(PublicController::class), $store)['store_filters']);
        $store->categories = [];
        $this->assertSame([], $method->invoke(app(PublicController::class), $store)['store_filters']);
    }
}
