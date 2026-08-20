<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Application-wide configuration, as opposed to the per-user settings
        // under /settings. Exactly one row (SPEC §3). The remaining columns
        // arrive with the milestones that need them: default_validity_days in
        // M4, the notification toggles in M7. Actual secrets live in .env,
        // never here.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();

            // Relative to the public disk. Null until a logo is uploaded, which
            // is a legitimate state: the PDF template leaves the space empty.
            $table->string('logo_path')->nullable();

            $table->timestamps();
        });

        // Created here rather than on first read, so there is never a moment
        // where two requests race to create the one row.
        DB::table('app_settings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
