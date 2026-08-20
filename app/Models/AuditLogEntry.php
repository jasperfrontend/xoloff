<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the audit log (SPEC §3).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $entity_type
 * @property int $entity_id
 * @property AuditAction $action
 * @property array<string, mixed> $payload
 * @property-read User|null $user
 */
class AuditLogEntry extends Model
{
    protected $table = 'audit_log';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'payload' => 'array',
        ];
    }

    /**
     * Nullable: an entry can outlive the person who caused it, and system
     * events have no person at all.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Everything that happened to one quote, including its versions.
     *
     * Versions are recorded against themselves rather than against the quote,
     * so that "deleted version 2" names version 2. They carry the quote they
     * belong to in the payload, which is what lets both come back from one
     * filter without a column the spec does not define.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForQuote(Builder $query, int $quoteId): void
    {
        $query->where(function (Builder $query) use ($quoteId): void {
            $query
                ->where(fn (Builder $query) => $query
                    ->where('entity_type', 'quote')
                    ->where('entity_id', $quoteId),
                )
                ->orWhereRaw("payload->>'quote_id' = ?", [(string) $quoteId]);
        });
    }
}
