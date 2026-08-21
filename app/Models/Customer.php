<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use App\Enums\Salutation;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $company_name
 * @property Salutation|null $salutation
 * @property string $first_name
 * @property string $last_name
 * @property-read string $contact_person
 * @property string $email
 * @property string $billing_address
 * @property string $country
 */
class Customer extends Model implements DescribesItselfForAudit
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, RecordsItsOwnChanges;

    protected $fillable = [
        'company_name',
        'salutation',
        'first_name',
        'last_name',
        'email',
        'billing_address',
        'country',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salutation' => Salutation::class,
        ];
    }

    public function auditLabel(): string
    {
        return $this->company_name;
    }

    /**
     * The whole name, for the places that address the person rather than greet
     * them: the addressee block on the PDF, and a column in a list.
     *
     * Derived rather than stored, so it cannot drift from the parts it is made
     * of. The salutation is left out on purpose - this reads as a name on an
     * envelope, and "heer Jan Jansen" is not one.
     *
     * @return Attribute<string, never>
     */
    protected function contactPerson(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
