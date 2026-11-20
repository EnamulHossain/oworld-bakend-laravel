<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class OrganizationUsernameUnicodeTest extends TestCase
{
    public function test_bangla_usernames_remain_valid_utf8_when_truncated_and_suffixed(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->softDeletes();
        });

        $method = new \ReflectionMethod(AdminController::class, 'generateUniqueUsername');
        $controller = app(AdminController::class);
        $name = str_repeat('বাংলা রেস্টুরেন্ট ', 5);
        $expected = mb_substr(trim($name), 0, 50, 'UTF-8');
        $username = $method->invoke($controller, $name);
        $this->assertSame($expected, $username);
        $this->assertTrue(mb_check_encoding($username, 'UTF-8'));

        DB::table('users')->insert(['username' => $username]);
        for ($counter = 1; $counter <= 11; $counter++) {
            $username = $method->invoke($controller, $name);
            $this->assertSame(mb_substr($expected, 0, 50 - strlen((string) $counter), 'UTF-8') . $counter, $username);
            $this->assertTrue(mb_check_encoding($username, 'UTF-8'));
            $this->assertSame(50, mb_strlen($username, 'UTF-8'));
            DB::table('users')->insert(['username' => $username]);
        }

        $this->assertSame('Short Store', $method->invoke($controller, 'Short Store'));
        $this->assertSame('organization', $method->invoke($controller, '  '));
    }
}
