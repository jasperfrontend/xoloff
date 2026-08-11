<?php

namespace App\Models;

use Database\Factories\TaxClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxClass extends Model
{
    /** @use HasFactory<TaxClassFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'percentage',
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
            'percentage' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
