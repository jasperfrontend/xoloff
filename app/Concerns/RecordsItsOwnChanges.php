<?php

namespace App\Concerns;

use App\Contracts\DescribesItselfForAudit;
use App\Enums\AuditAction;
use App\Support\Audit\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Logs a model's creation, change and deletion from its own events rather than
 * from the controllers that cause them, so that a screen added later cannot
 * quietly stop logging.
 *
 * Only for models that stand on their own. A model whose children carry part of
 * its meaning records through AuditLog where those children are written - see
 * Auditable.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements DescribesItselfForAudit
 */
trait RecordsItsOwnChanges
{
    use Auditable;

    public static function bootRecordsItsOwnChanges(): void
    {
        static::created(function (Model&DescribesItselfForAudit $model): void {
            AuditLog::record($model, AuditAction::Created, [
                ...$model->auditContext(),
                'attributes' => $model->auditAttributes(),
            ]);
        });

        static::updated(function (Model&DescribesItselfForAudit $model): void {
            $changes = $model->auditChanges();

            // A save that wrote nothing worth reporting is not an event. This
            // happens whenever a screen resubmits the values it was opened
            // with.
            if ($changes === []) {
                return;
            }

            AuditLog::record($model, AuditAction::Updated, [
                ...$model->auditContext(),
                'changes' => $changes,
            ]);
        });

        static::deleted(function (Model&DescribesItselfForAudit $model): void {
            AuditLog::record($model, AuditAction::Deleted, [
                ...$model->auditContext(),
                'attributes' => $model->auditAttributes(),
            ]);
        });
    }
}
