<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable()->after('phone');
            $table->json('categories')->nullable()->after('business_type');
            $table->json('payment_methods')->nullable()->after('opening_hours');
            $table->json('facilities')->nullable()->after('payment_methods');
            $table->json('highlights')->nullable()->after('facilities');
            $table->json('catalog_sections')->nullable()->after('highlights');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'categories', 'payment_methods', 'facilities', 'highlights', 'catalog_sections']);
        });
    }
};
