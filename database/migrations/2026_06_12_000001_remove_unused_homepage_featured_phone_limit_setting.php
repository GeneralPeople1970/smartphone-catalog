<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->where('key', 'homepage_featured_phone_limit')
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'homepage_featured_phone_limit'],
            [
                'value' => '3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
