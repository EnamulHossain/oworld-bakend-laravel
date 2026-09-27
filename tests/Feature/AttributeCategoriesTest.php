<?php
namespace Tests\Feature;

use App\Http\Controllers\Api\{AdminController, PublicController, OrganizationController};
use App\Models\{Category, Attribute, AttributeValue};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttributeCategoriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new Category, new Attribute, new AttributeValue] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) $table->text($field)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_multiple_assignments_save_match_secondary_categories_and_clear(): void
    {
        $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B']);
        $sa = Category::create(['name' => 'SA', 'parent_id' => $a->id]);
        $sb = Category::create(['name' => 'SB', 'parent_id' => $b->id]);
        $controller = app(AdminController::class);
        $payload = ['name' => 'Shared', 'type' => 'offer', 'start_date' => '2020-01-01',
            'category_ids' => [$a->id, $b->id], 'subcategory_ids' => [$sa->id, $sb->id],
            'values' => [['value' => 'Yes']]];
        $response = $controller->storeAttribute(Request::create('/', 'POST', $payload));
        $this->assertSame(201, $response->getStatusCode());
        $attribute = Attribute::firstOrFail();
        $this->assertSame([$a->id, $b->id], $attribute->category_ids);
        $this->assertSame([$sa->id, $sb->id], $attribute->subcategory_ids);
        foreach ([AdminController::class => 'listAttributes', PublicController::class => 'attributes', OrganizationController::class => 'attributes'] as $class => $method) {
            $result = app($class)->$method(Request::create('/', 'GET', ['category_id' => $b->id, 'subcategory_id' => $sb->id]));
            $this->assertCount(1, $result->getData(true)['attributes']);
        }
        $controller->updateAttribute(Request::create('/', 'PUT', ['start_date' => '2020-01-01', 'category_ids' => [], 'subcategory_ids' => []]), $attribute);
        $this->assertSame([], $attribute->fresh()->category_ids);
        $this->assertNull($attribute->fresh()->subcategory_id);
    }

    public function test_unrelated_subcategories_are_rejected(): void
    {
        $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B']);
        $sub = Category::create(['name' => 'B child', 'parent_id' => $b->id]);
        $this->expectException(ValidationException::class);
        app(AdminController::class)->storeAttribute(Request::create('/', 'POST', [
            'name' => 'Invalid', 'type' => 'offer', 'start_date' => '2020-01-01',
            'category_ids' => [$a->id], 'subcategory_ids' => [$sub->id],
        ]));
    }

    public function test_legacy_single_assignment_remains_supported(): void
    {
        $category = Category::create(['name' => 'Legacy']);
        app(AdminController::class)->storeAttribute(Request::create('/', 'POST', [
            'name' => 'Legacy', 'type' => 'event', 'start_date' => '2020-01-01', 'category_id' => $category->id,
        ]));
        $this->assertSame([$category->id], Attribute::firstOrFail()->category_ids);
    }
}
