<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Four decimals rather than two, so a rate is stored as it is written
        // rather than as the nearest hundredth.
        //
        // numeric(7,4) in Postgres: three digits before the point for 100, four
        // after. Exact, never a float, because this feeds the calculation
        // engine (SPEC §5) - and the engine reads it at four decimals too, so
        // widening the column alone would not have been enough.
        Schema::table('tax_classes', function (Blueprint $table) {
            $table->decimal('percentage', 7, 4)->change();
        });
    }

    public function down(): void
    {
        // Narrowing rounds, which is the whole thing this migration exists to
        // stop. Nothing is lost going back only because nothing has used the
        // extra precision yet.
        Schema::table('tax_classes', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->change();
        });
    }
};
