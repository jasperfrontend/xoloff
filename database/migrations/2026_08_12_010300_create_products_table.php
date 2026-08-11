<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // numeric(12,2) — exact decimal, never a float. This is the catalog
            // default that seeds quote_line_items.unit_price_ex_vat in M2.
            $table->decimal('price_ex_vat', 12, 2);

            // Default tax class, overridable per quote line (SPEC §3).
            // Restricted: a tax class in use by a product cannot be deleted.
            $table->foreignId('tax_class_id')
                ->constrained('tax_classes')
                ->restrictOnDelete();

            // Optional. Deleting a category orphans its products rather than
            // destroying catalog data.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
