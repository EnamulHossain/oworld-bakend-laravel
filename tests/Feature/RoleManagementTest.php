<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Route};
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    public function test_roles_assignments_and_permission_enforcement(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('username'); $table->string('email'); $table->string('role'); $table->softDeletes();
        });
        (require database_path('migrations/2025_11_27_055404_create_permission_tables.php'))->up();
        foreach (['admin', 'superAdmin'] as $name) Role::findOrCreate($name, 'sanctum');
        DB::table('users')->insert([
            ['id' => 1, 'username' => 'Owner', 'email' => 'owner@example.test', 'role' => 'superAdmin'],
            ['id' => 2, 'username' => 'Staff', 'email' => 'staff@example.test', 'role' => 'admin'],
        ]);
        $owner = User::find(1); $owner->assignRole('superAdmin');
        $staff = User::find(2); $staff->assignRole('admin');
        Sanctum::actingAs($owner);
        $this->postJson('/api/admin/roles', ['name' => 'Offer Editor', 'permissions' => ['staff.offers.manage']])->assertOk();
        $role = Role::where('name', 'Offer Editor')->firstOrFail();
        $this->postJson('/api/admin/roles', ['name' => 'superAdmin', 'permissions' => ['staff.offers.manage']])->assertUnprocessable();
        $this->putJson('/api/admin/roles/'.$role->id, ['name' => 'Editor', 'permissions' => ['anything']])->assertUnprocessable();
        $this->putJson('/api/admin/role-assignments/2', ['role_id' => $role->id])->assertOk();
        $this->deleteJson('/api/admin/roles/'.$role->id)->assertUnprocessable();
        $this->getJson('/api/admin/roles')->assertOk();
        Route::middleware(['auth:sanctum', 'role:admin|superAdmin', \App\Http\Middleware\EnforceStaffPermissions::class])->match(['GET', 'POST'], '/api/admin/{section}/permission-probe', fn () => response()->json(['ok' => true]));
        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/admin/offers/permission-probe')->assertOk();
        $this->postJson('/api/admin/offers/permission-probe')->assertOk();
        $this->getJson('/api/admin/events/permission-probe')->assertForbidden();
        $this->getJson('/api/admin/roles')->assertForbidden();
        $this->patchJson('/api/admin/users/1/role', ['role' => 'user'])->assertForbidden();
        $this->putJson('/api/store-profiles/2', [])->assertForbidden();
        Sanctum::actingAs($owner->fresh());
        $this->putJson('/api/admin/roles/'.$role->id, ['name' => 'Viewer', 'permissions' => ['staff.offers.view']])->assertOk();
        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/admin/offers/permission-probe')->assertOk();
        $this->postJson('/api/admin/offers/permission-probe')->assertForbidden();
        Sanctum::actingAs($owner->fresh());
        $this->deleteJson('/api/admin/roles/'.Role::where('name', 'admin')->value('id'))->assertUnprocessable();
        $this->putJson('/api/admin/role-assignments/2', ['role_id' => null])->assertOk();
        $this->deleteJson('/api/admin/roles/'.$role->id)->assertOk();
        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/admin/events/permission-probe')->assertOk();
    }
}
