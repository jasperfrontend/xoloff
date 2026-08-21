<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Why a customer said no, in their own words (SPEC §3, §8). This
        // replaces the "countered" status outright rather than sitting beside
        // it - that concept was dropped, not deferred (SPEC §11).
        Schema::table('quotes', function (Blueprint $table) {
            // Nullable and optional: SPEC §8 says denial *opens* a reason box,
            // not that it demands one. Someone who does not want to explain
            // themselves must still be able to decline.
            $table->text('deny_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('deny_reason');
        });
    }
};
