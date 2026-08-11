<?php

namespace App\Models;

use Database\Factories\ProductSpecFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpec extends Model
{
    /** @use HasFactory<ProductSpecFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'key',
        'value',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
