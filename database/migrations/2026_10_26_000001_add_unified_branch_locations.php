<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('branch_mode', 20)->default('unified'));
        // Preserve every existing linked family and its public profiles.
        $parents = DB::table('users')->whereNotNull('parent_org_id')->pluck('parent_org_id')->unique();
        DB::table('users')->whereIn('id', $parents)->orWhereNotNull('parent_org_id')->update(['branch_mode' => 'independent']);
        Schema::create('store_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('google_map_url', 500)->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();
        });
        Schema::table('offers', fn (Blueprint $table) => $table->json('location_ids')->nullable());
    }

    public function down(): void
    {
        Schema::table('offers', fn (Blueprint $table) => $table->dropColumn('location_ids'));
        Schema::dropIfExists('store_locations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('branch_mode'));
    }
};
