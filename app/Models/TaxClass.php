<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use Database\Factories\TaxClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxClass extends Model implements DescribesItselfForAudit
{
    /** @use HasFactory<TaxClassFactory> */
    use HasFactory, RecordsItsOwnChanges;

    protected $fillable = [
        'name',
        'percentage',
    ];

    /**
     * Cast as a fixed-precision string, never a float - this value feeds the
     * M2 calculation engine.
     *
     * Four decimals, matching the column. At two the cast would round on the
     * way out and the extra precision in the database would never reach the
     * engine, which is a silent way to lose it.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
        ];
    }

    public function auditLabel(): string
    {
        return $this->name;
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
