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

    public function test_store_filters_can_be_created_listed_and_updated(): void
    {
        $controller = app(AdminController::class);
        $response = $controller->storeAttribute(Request::create('/', 'POST', [
            'name' => 'Store facilities', 'type' => 'store', 'start_date' => '2020-01-01',
            'values' => [['value' => 'Parking']],
        ]));
        $this->assertSame(201, $response->getStatusCode());
        $attribute = Attribute::firstOrFail();
        $this->assertSame('store', $attribute->type);
        $this->assertSame('Parking', $attribute->values()->firstOrFail()->value);
        $result = $controller->listAttributes(Request::create('/', 'GET', ['type' => 'store']));
        $this->assertCount(1, $result->getData(true)['attributes']);
        $controller->updateAttribute(Request::create('/', 'PUT', [
            'name' => 'Store amenities', 'type' => 'store', 'start_date' => '2020-01-01',
        ]), $attribute);
        $this->assertSame('Store amenities', $attribute->fresh()->name);
        $this->assertSame('store', $attribute->fresh()->type);
    }

    public function test_multiple_types_share_one_filter_and_can_be_updated(): void
    {
        $controller = app(AdminController::class);
        $response = $controller->storeAttribute(Request::create('/', 'POST', [
            'name' => 'Shared type filter', 'types' => ['event', 'offer', 'store'],
            'start_date' => '2020-01-01', 'values' => [['value' => 'Yes']],
        ]));
        $this->assertSame(201, $response->getStatusCode());
        $attribute = Attribute::firstOrFail();
        $this->assertSame(['event', 'offer', 'store'], $attribute->types);
        foreach (['event', 'offer', 'store'] as $type) {
            foreach ([AdminController::class => 'listAttributes', PublicController::class => 'attributes', OrganizationController::class => 'attributes'] as $class => $method) {
                $result = app($class)->$method(Request::create('/', 'GET', ['type' => $type]));
                $this->assertCount(1, $result->getData(true)['attributes']);
            }
        }
        $controller->updateAttribute(Request::create('/', 'PUT', [
            'types' => ['store'], 'start_date' => '2020-01-01',
        ]), $attribute);
        $this->assertSame(['store'], $attribute->fresh()->types);
        $this->assertSame(0, Attribute::forType('offer')->count());
        $this->assertSame(1, Attribute::forType('store')->count());
    }

    public function test_empty_type_selection_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(AdminController::class)->storeAttribute(Request::create('/', 'POST', [
            'name' => 'Empty types', 'types' => [], 'start_date' => '2020-01-01',
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
