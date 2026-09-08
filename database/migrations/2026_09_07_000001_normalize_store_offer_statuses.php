<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('offers')->where('status', 'active')->update(['status' => 'published']);
        DB::table('offers')->where('status', 'inactive')->update(['status' => 'archived']);
    }

    public function down(): void
    {
        // Legacy statuses are intentionally not restored.
    }
};
