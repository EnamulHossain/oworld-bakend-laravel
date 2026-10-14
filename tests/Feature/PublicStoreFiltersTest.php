<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PublicController;
use App\Models\{Attribute, User};
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

        $selections = [];
        foreach (['published', 'draft', 'inactive'] as $status) {
            $attribute = Attribute::create(['name' => $status, 'status' => $status]);
            $selections[] = ['attribute_id' => $attribute->id, 'value_ids' => [10]];
        }
        $store = new User(['store_filters' => $selections]);
        $store->setRelation('area', null);
        $method = new \ReflectionMethod(PublicController::class, 'formatPublicOrganization');
        $result = $method->invoke(app(PublicController::class), $store);

        $this->assertSame([$selections[0]], $result['store_filters']);
        $this->assertSame($selections, $store->store_filters);
    }
}
