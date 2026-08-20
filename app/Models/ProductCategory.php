<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model implements DescribesItselfForAudit
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, RecordsItsOwnChanges;

    protected $fillable = [
        'name',
    ];

    public function auditLabel(): string
    {
        return $this->name;
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
