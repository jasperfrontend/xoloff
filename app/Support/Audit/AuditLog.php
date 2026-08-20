<?php

namespace App\Support\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLogEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The one way anything gets into the audit log (SPEC §3).
 *
 * Most entries are written by the Auditable trait from model events, so nothing
 * has to remember to log. Aggregates whose children changed record through here
 * directly, because a child-only edit never makes its parent dirty and would
 * otherwise leave no trace.
 */
final class AuditLog
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(Model $entity, AuditAction $action, array $payload = []): AuditLogEntry
    {
        return AuditLogEntry::create([
            // Null for anything that happens without a person: a seeder, a
            // console command, a queued job.
            'user_id' => Auth::id(),
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
