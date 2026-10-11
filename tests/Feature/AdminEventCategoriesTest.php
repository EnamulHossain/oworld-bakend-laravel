<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminController;
use App\Models\{Category, Event};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminEventCategoriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new Category, new Event] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) $table->text($field)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function normalize(array $payload, ?Event $event = null): array
    {
        $method = new \ReflectionMethod(AdminController::class, 'normalizeEventClassification');
        return $method->invoke(app(AdminController::class), Request::create('/', 'POST', $payload), $payload, $event);
    }

    public function test_multiple_categories_and_children_persist_and_clear(): void
    {
        $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B']);
        $sa = Category::create(['name' => 'SA', 'parent_id' => $a->id]);
        $sb = Category::create(['name' => 'SB', 'parent_id' => $b->id]);
        $event = Event::create($this->normalize([
            'category_ids' => [$a->id, $b->id], 'subcategory_ids' => [$sb->id, $sa->id],
        ]));
        $this->assertSame([$a->id, $b->id], $event->fresh()->category_ids);
        $this->assertSame([$sb->id, $sa->id], $event->fresh()->subcategory_ids);
        $this->assertEquals($sb->id, $event->fresh()->subcategory_id);
        $event->update($this->normalize(['category_ids' => [], 'subcategory_ids' => []], $event));
        $this->assertSame([], $event->fresh()->category_ids);
        $this->assertSame([], $event->fresh()->subcategory_ids);
        $this->assertNull($event->fresh()->category_id);
        $this->assertNull($event->fresh()->subcategory_id);
    }

    public function test_unrelated_subcategories_are_rejected(): void
    {
        $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B']);
        $sub = Category::create(['name' => 'B child', 'parent_id' => $b->id]);
        $this->expectException(ValidationException::class);
        $this->normalize(['category_ids' => [$a->id], 'subcategory_ids' => [$sub->id]]);
    }
}
