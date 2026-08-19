<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The logical, ongoing quote. Its content lives in quote_versions, and
        // the current version is simply the highest version_number for this
        // quote_id, so there is no pointer column here (SPEC §3).
        //
        // The M4+ columns (status, magic_link_token, valid_until,
        // validity_days_override, sent_at) and the M5 deny_reason are
        // deliberately absent, per SPEC §5.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            // Restricted: a customer with quotes against their name cannot be
            // deleted. Quotes are financial records, so orphaning or cascading
            // them would both destroy history.
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
