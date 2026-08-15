<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('owner_full_name', 120)->nullable()->after('category_id');
            $table->string('owner_phone', 30)->nullable()->after('owner_full_name');
            $table->string('owner_email')->nullable()->after('owner_phone');

            $table->string('nid_no', 50)->nullable()->after('owner_email');
            $table->string('trade_license_no', 100)->nullable()->after('nid_no');
            $table->date('trade_license_valid_until')->nullable()->after('trade_license_no');
            $table->date('organization_valid_until')->nullable()->after('trade_license_valid_until');
            $table->string('nid_front_image', 500)->nullable()->after('organization_valid_until');
            $table->string('nid_back_image', 500)->nullable()->after('nid_front_image');
            $table->string('trade_license_image', 500)->nullable()->after('nid_back_image');

            $table->text('about')->nullable()->after('address');
            $table->string('opening_hours', 120)->nullable()->after('about');
            $table->json('business_hours')->nullable()->after('opening_hours');
            $table->json('interior_media')->nullable()->after('profile_banner');
            $table->json('payment_methods')->nullable()->after('interior_media');
            $table->json('facilities')->nullable()->after('payment_methods');
            $table->json('highlights')->nullable()->after('facilities');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'owner_full_name',
                'owner_phone',
                'owner_email',
                'nid_no',
                'trade_license_no',
                'trade_license_valid_until',
                'organization_valid_until',
                'nid_front_image',
                'nid_back_image',
                'trade_license_image',
                'about',
                'opening_hours',
                'business_hours',
                'interior_media',
                'payment_methods',
                'facilities',
                'highlights',
            ]);
        });
    }
};
