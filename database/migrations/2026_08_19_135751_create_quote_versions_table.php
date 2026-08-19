<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A snapshot of quote content. Rows are created only on an explicit
        // "Save as new version" action, never automatically on every save
        // (SPEC §3).
        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();

            // Versions have no meaning without their quote, so they go with it.
            $table->foreignId('quote_id')
                ->constrained('quotes')
                ->cascadeOnDelete();

            $table->unsignedInteger('version_number');

            // Quote-level discount, applied pre-VAT to the quote subtotal
            // (SPEC §5 step 4). Nullable as a pair: both columns are set or
            // neither is.
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();

            // Replaces the calculated total outright for display and PDF.
            // No reconciliation line, no adjustment entry (SPEC §5).
            $table->decimal('rounding_override', 12, 2)->nullable();

            // Copied from premade_texts at save time rather than referenced
            // live, so a quote already viewed or signed stays accurate when the
            // global texts are edited later (SPEC §3). premade_texts arrives in
            // M3, so nothing writes these yet.
            $table->text('intro_text_snapshot')->nullable();
            $table->text('footer_text_snapshot')->nullable();

            $table->timestamps();

            // version_number is per quote, and the pair is how a version is
            // addressed, so the database enforces it rather than trusting
            // application-side increments to never race.
            $table->unique(['quote_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_versions');
    }
};
