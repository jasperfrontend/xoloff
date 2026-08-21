<?php

use App\Enums\QuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a quote stands (SPEC §3, §7). The remaining M4 columns follow
        // with the milestone's later slices: the validity window, then the
        // magic link and sent_at.
        Schema::table('quotes', function (Blueprint $table) {
            // A string rather than a database enum: Postgres enums need a
            // migration to gain a value, and the set here is owned by
            // App\Enums\QuoteStatus. Defaulted rather than nullable, because
            // "no status" is not one of the five the spec names - a quote
            // nobody has sent yet is a draft.
            $table->string('status')
                ->default(QuoteStatus::Draft->value)
                ->index()
                ->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
