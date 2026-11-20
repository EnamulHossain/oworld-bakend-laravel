<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class OrganizationSelectorTest extends TestCase
{
    public function test_selector_omits_large_fields_and_preserves_store_defaults(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach ((new User)->getFillable() as $field) $table->text($field)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('users')->insert(['id' => 1, 'role' => 'organization', 'username' => 'store',
            'organization_name' => 'Test Store', 'isOnline' => true, 'categories' => '["12"]', 'subcategory_ids' => '[34]',
            'phone' => '01700000000', 'area_id' => 5,
            'store_filters' => '[{"attribute_id":1,"value_ids":[2]}]',
            'catalog_items' => '[{"name":"Item"}]', 'catalog_sections' => '[{"title":"Menu"}]']);
        $controller = app(AdminController::class);
        $response = $controller->organizations(Request::create('/api/admin/organizations?for_selector=1'));
        $this->assertSame(200, $response->getStatusCode());
        $stores = $response->getData(true)['organizations'];
        $this->assertCount(1, $stores);
        $this->assertSame('Test Store', $stores[0]['organization_name']);
        $this->assertTrue($stores[0]['isOnline']);
        $this->assertSame(['12'], $stores[0]['categories']);
        $this->assertSame([34], $stores[0]['subcategory_ids']);
        $this->assertSame('01700000000', $stores[0]['phone']);
        $this->assertSame([], $stores[0]['store_filters']);
        $this->assertSame([], $stores[0]['catalog_items']);
        $detail = $controller->showOrganization(User::findOrFail(1))->getData(true)['organization'];
        $this->assertSame([['attribute_id' => 1, 'value_ids' => [2]]], $detail['store_filters']);
        $this->assertSame([['name' => 'Item']], $detail['catalog_items']);
    }
}
