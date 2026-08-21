<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What sending a quote records (SPEC §3, §7).
        Schema::table('quotes', function (Blueprint $table) {
            // The customer's whole credential for this quote, so it is unique
            // and indexed: the portal looks a quote up by nothing else. Null
            // until the quote is first sent, which is also what makes "has
            // this ever been sent" answerable without reading the status.
            $table->string('magic_link_token', 64)->nullable()->unique();

            $table->timestamp('sent_at')->nullable();

            // A date rather than a timestamp. A quote valid until the 20th is
            // valid for all of the 20th, and an expiry that fell at midnight
            // in one timezone and lunchtime in another would be a support
            // question rather than a rule.
            $table->date('valid_until')->nullable();

            // Null means "follow app_settings.default_validity_days". Storing
            // the default here instead would quietly detach the quote from it,
            // so a later change to the default would stop reaching quotes
            // nobody had deliberately given a different window.
            $table->unsignedSmallInteger('validity_days_override')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'magic_link_token',
                'sent_at',
                'valid_until',
                'validity_days_override',
            ]);
        });
    }
};
