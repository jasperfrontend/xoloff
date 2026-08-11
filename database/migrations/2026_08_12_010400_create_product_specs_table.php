<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flexible key/value list per product - billing period, startup cost,
        // contract duration, etc. Deliberately not fixed columns (SPEC §3).
        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('key');
            $table->string('value');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specs');
    }
};
