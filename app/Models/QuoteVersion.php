<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\QuoteVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $quote_id
 * @property int $version_number
 * @property DiscountType|null $discount_type
 * @property string|null $discount_value
 * @property string|null $rounding_override
 * @property string|null $intro_text_snapshot
 * @property string|null $footer_text_snapshot
 */
class QuoteVersion extends Model
{
    /** @use HasFactory<QuoteVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'version_number',
        'discount_type',
        'discount_value',
        'rounding_override',
        'intro_text_snapshot',
        'footer_text_snapshot',
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
            'version_number' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'rounding_override' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return HasMany<QuoteLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class);
    }
}
