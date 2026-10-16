<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PublicController;
use App\Models\{Category, Event, Offer};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PublicListingClassificationTest extends TestCase
{
    public function test_public_lists_preserve_primary_and_multiple_classifications(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('timestamp', fn ($date, $time) => "$date $time", 2);
        foreach ([new Category, new Event, new Offer] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $category = Category::create(['name' => 'Food']);
        $child = Category::create(['name' => 'Dining', 'parent_id' => $category->id]);
        foreach (['offers' => Offer::class, 'events' => Event::class] as $method => $model) {
            $record = $model::create([
                'name' => 'Classified listing', 'status' => 'published',
                'category_id' => $category->id, 'category_ids' => [$category->id, 100],
                'subcategory_id' => $child->id, 'subcategory_ids' => [$child->id, 200],
            ]);
            $model::create(['name' => 'Unclassified listing', 'status' => 'published']);
            $response = app(PublicController::class)->$method(Request::create('/'));
            $items = collect($response->getData(true)[$method]);
            $item = $items->firstWhere('id', $record->id);
            $this->assertEquals($category->id, $item['category_id']);
            $this->assertSame([$category->id, 100], $item['category_ids']);
            $this->assertEquals($child->id, $item['subcategory_id']);
            $this->assertSame([$child->id, 200], $item['subcategory_ids']);
            $unclassified = $items->firstWhere('title', 'Unclassified listing');
            $this->assertNull($unclassified['subcategory_id']);
            $this->assertSame([], $unclassified['subcategory_ids']);
        }
    }
}
