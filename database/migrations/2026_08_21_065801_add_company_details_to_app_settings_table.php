<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Xolution's own identity, printed on the quote PDF (SPEC §7). Here
        // rather than in the template so a wrong KvK number is a form field
        // and not a redeploy.
        Schema::table('app_settings', function (Blueprint $table) {
            // All nullable, and the template prints only what is filled in.
            // The real values are still being collected (SPEC §12), and a
            // required field would mean nobody could save the logo screen
            // until they arrived.
            $table->string('company_name')->nullable();

            // Text rather than string: a postal address is several lines, and
            // it is printed with the line breaks it was typed with.
            $table->text('company_address')->nullable();

            // Strings, not numbers. A KvK number keeps its leading zero and a
            // BTW number is "NL001234567B01" - neither is arithmetic.
            $table->string('company_kvk')->nullable();
            $table->string('company_vat_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_address',
                'company_kvk',
                'company_vat_number',
            ]);
        });
    }
};
