<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_verifications', function (Blueprint $table) {
            $table->string('owner_full_name', 120)->nullable()->change();
            $table->string('owner_phone', 30)->nullable()->change();
            $table->string('owner_email')->nullable()->change();
            $table->string('nid_no', 50)->nullable()->change();
            $table->string('trade_license_no', 100)->nullable()->change();
            $table->date('trade_license_valid_until')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organization_verifications', function (Blueprint $table) {
            $table->string('owner_full_name', 120)->nullable(false)->change();
            $table->string('owner_phone', 30)->nullable(false)->change();
            $table->string('owner_email')->nullable(false)->change();
            $table->string('nid_no', 50)->nullable(false)->change();
            $table->string('trade_license_no', 100)->nullable(false)->change();
            $table->date('trade_license_valid_until')->nullable(false)->change();
        });
    }
};
