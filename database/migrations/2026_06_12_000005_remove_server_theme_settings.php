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
            ->whereIn('key', ['ui_theme_mode', 'ui_primary_color'])
            ->delete();
    }

    public function down(): void
    {
        // Theme settings are now browser-local and should not be restored.
    }
};
