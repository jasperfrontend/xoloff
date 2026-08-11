<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Freely extensible — not hardcoded to hosting/web dev (SPEC §3).
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();

            // 21.00, 9.00, 0.00. numeric(5,2) is exact in Postgres — never a float,
            // because this feeds the M2 calculation engine.
            $table->decimal('percentage', 5, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_classes');
    }
};
