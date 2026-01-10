<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('facebook_url', 500)->nullable()->after('address');
            $table->string('instagram_url', 500)->nullable()->after('facebook_url');
            $table->string('website_url', 500)->nullable()->after('instagram_url');
            $table->string('google_map_url', 500)->nullable()->after('website_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_url',
                'instagram_url',
                'website_url',
                'google_map_url',
            ]);
        });
    }
};
