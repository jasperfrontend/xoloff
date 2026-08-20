<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Contracts\DescribesItselfForAudit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model implements DescribesItselfForAudit
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'price_ex_vat',
        'tax_class_id',
        'category_id',
    ];

    /**
     * Cast as a fixed-precision string, never a float - this value feeds the
     * M2 calculation engine.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_ex_vat' => 'decimal:2',
        ];
    }

    public function auditLabel(): string
    {
        return $this->name;
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ProductSpec, $this>
     */
    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class);
    }
}
