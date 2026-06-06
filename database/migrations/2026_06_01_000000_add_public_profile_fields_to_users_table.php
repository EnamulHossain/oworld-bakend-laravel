<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_banner')) {
                $table->string('profile_banner', 500)->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'opening_hours')) {
                $table->string('opening_hours', 120)->nullable()->after('google_map_url');
            }
            if (!Schema::hasColumn('users', 'follower_count')) {
                $table->unsignedInteger('follower_count')->default(0)->after('opening_hours');
            }
            if (!Schema::hasColumn('users', 'rating_average')) {
                $table->decimal('rating_average', 3, 2)->default(0)->after('follower_count');
            }
            if (!Schema::hasColumn('users', 'review_count')) {
                $table->unsignedInteger('review_count')->default(0)->after('rating_average');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'profile_banner',
                'opening_hours',
                'follower_count',
                'rating_average',
                'review_count',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
