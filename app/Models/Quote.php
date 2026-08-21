<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use App\Enums\AuditAction;
use App\Enums\QuoteStatus;
use App\Support\Audit\AuditLog;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $customer_id
 * @property QuoteStatus $status
 * @property string|null $magic_link_token
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $valid_until
 * @property int|null $validity_days_override
 * @property string|null $deny_reason
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

    /**
     * The customer's whole credential for this quote. Hidden, which is what
     * keeps it out of audit payloads and out of anything serialised to a page
     * by accident - it is a password, not a column.
     *
     * @var list<string>
     */
    protected $hidden = [
        'magic_link_token',
    ];

    /**
     * Nothing sending touches is fillable. Status, the token and the dates all
     * move through the action that causes them and never through a form post,
     * so no request can nominate one.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'sent_at' => 'immutable_datetime',
            'valid_until' => 'immutable_date',
            'validity_days_override' => 'integer',
        ];
    }

    /**
     * Everything an event writes, all at once. The action that causes the
     * event records one entry describing the whole thing, so leaving these
     * here would report it twice: once as what happened and once as a set of
     * columns that moved.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return [
            'status',
            'magic_link_token',
            'sent_at',
            'valid_until',
            'validity_days_override',
            // Written at the same moment as the denial, and carried in that
            // entry's payload. Left here as well, a customer's refusal would
            // be reported twice.
            'deny_reason',
        ];
    }

    /**
     * How long this quote stays valid once sent: its own answer if it has been
     * given one, and the application default otherwise (SPEC §7).
     */
    public function validityDays(): int
    {
        return $this->validity_days_override ?? AppSettings::current()->default_validity_days;
    }

    /**
     * Whether the customer has already said yes or no. Both are final: a quote
     * is not re-decided, it is superseded by a new one.
     */
    public function hasBeenDecided(): bool
    {
        return in_array($this->status, [QuoteStatus::Approved, QuoteStatus::Denied], true);
    }

    /**
     * Whether the window has closed. A quote valid until the 20th is valid for
     * all of the 20th, which is why valid_until is a date: an expiry falling
     * at midnight would be a support question rather than a rule.
     *
     * A quote with no date has not been sent, so it has no window to be past.
     */
    public function hasExpired(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isBefore(CarbonImmutable::today());
    }

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
     * Moves the quote and records it as the event it is (SPEC §3), rather than
     * as an "updated" entry naming a column.
     *
     * Whatever else the caller has already set on the model is saved in the
     * same write. A send sets the token and the dates first and lands here
     * last, so there is never a moment where a quote is marked sent without
     * the link that makes that true. The entry is written after the save, so
     * a write that fails cannot leave a log of something that did not happen.
     *
     * @param  array<string, mixed>  $detail  what else the event is worth knowing about
     */
    public function transitionTo(QuoteStatus $status, array $detail = []): void
    {
        $from = $this->status;

        $this->status = $status;
        $this->save();

        AuditLog::record($this, AuditAction::StatusChanged, [
            ...$this->auditContext(),
            'changes' => ['status' => ['from' => $from->value, 'to' => $status->value]],
            ...($detail === [] ? [] : ['attributes' => $detail]),
        ]);
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
