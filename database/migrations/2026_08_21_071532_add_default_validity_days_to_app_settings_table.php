<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How long a quote stays valid unless the quote itself says otherwise
        // (SPEC §3, §7). Thirty days, which is the spec's default and also
        // what the existing row gets.
        Schema::table('app_settings', function (Blueprint $table) {
            // Small: this is a number of days, and the form caps it at a year.
            // Not nullable - "no default" would leave every quote without an
            // expiry, which is the one thing the validity window exists to
            // prevent.
            $table->unsignedSmallInteger('default_validity_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('default_validity_days');
        });
    }
};
