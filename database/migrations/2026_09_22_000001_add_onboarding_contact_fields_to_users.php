<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_email')->nullable();
            foreach (['messenger_url', 'tiktok_url', 'linkedin_url', 'youtube_url'] as $field) {
                $table->string($field, 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'messenger_url', 'tiktok_url', 'linkedin_url', 'youtube_url']);
        });
    }
};
