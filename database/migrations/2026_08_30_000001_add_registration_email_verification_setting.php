<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed the registration email-verification switch, off by default: an
     * existing deployment may have no working mailer, and flipping it on
     * silently would break every new registration.
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'registration_email_verification'],
            ['value' => '0', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->where('key', 'registration_email_verification')
            ->delete();
    }
};
