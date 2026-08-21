<?php

namespace App\Concerns;

use App\Contracts\DescribesItselfForAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * How a model describes itself in the audit log: what it is called, what
 * context an entry about it carries, and which of its attributes are worth
 * recording.
 *
 * Recording is separate, in RecordsItsOwnChanges. Most models let that trait
 * log them from their own events, so a new screen cannot quietly stop logging.
 * A model whose children carry part of its meaning - a product's specs, a quote
 * version's lines - does not, because a child-only edit fires no event on the
 * parent at all: those record through AuditLog where they are written, so that
 * one edit produces one entry describing the whole thing.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements DescribesItselfForAudit
 */
trait Auditable
{
    /**
     * How this row is named in the log. The entity itself is usually gone by
     * the time anyone reads the entry, so the name has to be captured with it.
     */
    public function auditLabel(): string
    {
        return class_basename($this).' '.$this->getKey();
    }

    /**
     * Extra detail carried on every entry for this model. Anything belonging to
     * a quote overrides this to add quote_id, which is what makes the log
     * filterable by quote.
     *
     * @return array<string, mixed>
     */
    public function auditContext(): array
    {
        return ['label' => $this->auditLabel()];
    }

    /**
     * What changed, as before and after.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function auditChanges(): array
    {
        $changes = [];

        foreach ($this->getChanges() as $attribute => $value) {
            if (! $this->isAuditable($attribute)) {
                continue;
            }

            $changes[$attribute] = [
                'from' => $this->getOriginal($attribute),
                'to' => $value,
            ];
        }

        return $changes;
    }

    /**
     * The row as it stands, for a creation or a deletion.
     *
     * @return array<string, mixed>
     */
    public function auditAttributes(): array
    {
        $attributes = [];

        foreach ($this->getAttributes() as $attribute => $value) {
            if ($this->isAuditable($attribute)) {
                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Attributes this model records some other way.
     *
     * A status change is its own action in the log rather than an "updated"
     * entry (SPEC §3), and the action that causes one writes it by hand. Left
     * in here as well, the same event would be reported twice - once as the
     * thing that happened and once as a column that moved.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return [];
    }

    /**
     * Hidden attributes are excluded by definition rather than by a list, so a
     * password hash or a token added to a model later cannot end up in a
     * payload that people browse.
     */
    private function isAuditable(string $attribute): bool
    {
        return ! in_array($attribute, [
            $this->getKeyName(),
            'created_at',
            'updated_at',
            ...$this->getHidden(),
            ...$this->auditExcept(),
        ], true);
    }
}
