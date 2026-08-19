<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();

            // Line items are part of the version snapshot, not shared between
            // versions, so they go when the version goes.
            $table->foreignId('quote_version_id')
                ->constrained('quote_versions')
                ->cascadeOnDelete();

            // A line can be detached from its originating product once inserted,
            // and deleting a catalog product must never rewrite a quote that has
            // already been sent (SPEC §3).
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Copied from the product then freely editable - catalog values are
            // defaults, not locked (SPEC §3).
            $table->string('name');
            $table->jsonb('specs')->nullable();

            // Fractional quantities are allowed, since these lines price hours
            // as readily as they price units.
            $table->decimal('quantity', 12, 2);

            // numeric(12,2) - exact decimal, never a float. Seeded from
            // products.price_ex_vat and then editable per line.
            $table->decimal('unit_price_ex_vat', 12, 2);

            // Per line, not per quote: one quote can mix tax classes (SPEC §5).
            // Restricted, so a tax class cited by a quote cannot be deleted.
            $table->foreignId('tax_class_id')
                ->constrained('tax_classes')
                ->restrictOnDelete();

            // Line-level discount, applied to the line subtotal pre-VAT
            // (SPEC §5 step 2). Nullable as a pair, as on quote_versions.
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_items');
    }
};
