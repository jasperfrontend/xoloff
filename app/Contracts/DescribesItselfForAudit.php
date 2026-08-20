<?php

namespace App\Contracts;

/**
 * How a model presents itself in the audit log.
 *
 * Implemented for every model by the Auditable trait; declared as an interface
 * so that recording can be typed against the shape rather than against a trait,
 * which cannot be part of a type.
 */
interface DescribesItselfForAudit
{
    /**
     * How the row is named in the log. Captured with the entry, because the
     * entity is usually gone by the time anyone reads it.
     */
    public function auditLabel(): string;

    /**
     * Detail carried on every entry for this model, including quote_id for
     * anything belonging to a quote.
     *
     * @return array<string, mixed>
     */
    public function auditContext(): array;

    /**
     * What changed, as before and after.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function auditChanges(): array;

    /**
     * The row as it stands, for a creation or a deletion.
     *
     * @return array<string, mixed>
     */
    public function auditAttributes(): array;
}
