<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signup_source', 50)->nullable()->after('avatar')->index();
            $table->string('signup_referrer', 500)->nullable()->after('signup_source');
            $table->string('signup_utm_campaign', 150)->nullable()->after('signup_referrer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'signup_source',
                'signup_referrer',
                'signup_utm_campaign',
            ]);
        });
    }
};
