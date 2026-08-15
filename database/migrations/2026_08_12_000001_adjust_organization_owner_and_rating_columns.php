<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('owner_user_id', 'user_id');
            $table->dropColumn('rating_average');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('user_id', 'owner_user_id');
            $table->decimal('rating_average', 3, 2)->default(0)->after('follower_count');
        });
    }
};
