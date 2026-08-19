<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\QuoteLineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quote_version_id
 * @property int|null $product_id
 * @property string $name
 * @property array<string, string>|null $specs
 * @property string $quantity
 * @property string $unit_price_ex_vat
 * @property int $tax_class_id
 * @property DiscountType|null $discount_type
 * @property string|null $discount_value
 */
class QuoteLineItem extends Model
{
    /** @use HasFactory<QuoteLineItemFactory> */
    use HasFactory;

    protected $fillable = [
        'quote_version_id',
        'product_id',
        'name',
        'specs',
        'quantity',
        'unit_price_ex_vat',
        'tax_class_id',
        'discount_type',
        'discount_value',
    ];

    /**
     * Money and discount values are cast as fixed-precision strings, never
     * floats - they feed the calculation engine (SPEC §5).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'quantity' => 'decimal:2',
            'unit_price_ex_vat' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<QuoteVersion, $this>
     */
    public function quoteVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class);
    }

    /**
     * Nullable: a line can be detached from its originating product, and the
     * product can be deleted from the catalog entirely (SPEC §3).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }
}
