<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $customer_id
 * @property-read Customer $customer
 * @property-read QuoteVersion|null $currentVersion
 */
class Quote extends Model implements DescribesItselfForAudit
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, RecordsItsOwnChanges;

    protected $fillable = [
        'customer_id',
    ];

    public function auditLabel(): string
    {
        return __('Quote :id', ['id' => $this->getKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditContext(): array
    {
        return ['label' => $this->auditLabel(), 'quote_id' => $this->getKey()];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<QuoteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class);
    }

    /**
     * The current version is simply the highest version_number for this quote,
     * which is why no pointer column exists on quotes (SPEC §3).
     *
     * @return HasOne<QuoteVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(QuoteVersion::class)->ofMany('version_number', 'max');
    }
}
