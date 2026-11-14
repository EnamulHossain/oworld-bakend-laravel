<?php
namespace Tests\Feature;

use App\Models\{User, Offer, Event};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PublishedStoreContentTest extends TestCase
{
    public function test_offers_and_events_require_published_store_without_requiring_verification(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([new User, new Offer, new Event] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) $table->text($field)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        $published = User::create(['role' => 'organization', 'status' => 'active', 'is_verified' => false]);
        $hidden = User::create(['role' => 'organization', 'status' => 'inactive', 'is_verified' => true]);
        foreach ([Offer::class, Event::class] as $class) {
            $visible = $class::create(['name' => 'Visible', 'organization_id' => $published->id, 'status' => 'published']);
            $class::create(['name' => 'Hidden', 'organization_id' => $hidden->id, 'status' => 'published']);
            $class::create(['name' => 'Orphan', 'organization_id' => 9999, 'status' => 'published']);
            $this->assertSame([$visible->id], $class::fromPublishedStore()->pluck('id')->all());
            $published->update(['status' => 'inactive']);
            $this->assertSame(0, $class::fromPublishedStore()->count());
            $published->update(['status' => 'active']);
        }
    }
}
