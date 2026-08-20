<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLogEntry;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The browsable, filterable audit log (SPEC §3). One log covering CRUD, version
 * and export events alike, filterable by quote, by date range and by which
 * person caused the entry.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $entries = AuditLogEntry::query()
            ->with('user:id,name')
            ->when($filters['quote_id'] !== null, fn (Builder $query) => $query->forQuote((int) $filters['quote_id']))
            ->when($filters['user_id'] !== null, fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['action'] !== null, fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            // Inclusive: someone filtering to today means up to the end of
            // today, not up to midnight this morning.
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('audit-log/Index', [
            'entries' => $entries->through(fn (AuditLogEntry $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action->value,
                'action_label' => $entry->action->label(),
                'entity_type' => $entry->entity_type,
                'entity_id' => $entry->entity_id,
                'label' => $entry->payload['label'] ?? null,
                'quote_id' => $entry->payload['quote_id'] ?? null,
                'payload' => $entry->payload,
                // Null where the entry has no person behind it: a seeder, a
                // console command, or someone since removed.
                'user_name' => $entry->user?->name,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'quotes' => Quote::query()
                ->with('customer:id,company_name')
                ->latest('id')
                ->get(['id', 'customer_id'])
                ->map(fn (Quote $quote): array => [
                    'id' => $quote->id,
                    'label' => __('Quote :id, :customer', [
                        'id' => $quote->id,
                        'customer' => $quote->customer->company_name,
                    ]),
                ]),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => array_map(
                fn (AuditAction $action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                ],
                AuditAction::cases(),
            ),
        ]);
    }

    /**
     * Read rather than validated, because a filter that makes no sense should
     * show everything rather than throw a validation error at someone who was
     * only browsing.
     *
     * @return array{quote_id: int|null, user_id: int|null, action: string|null, from: string|null, to: string|null}
     */
    private function filters(Request $request): array
    {
        $action = $request->string('action')->toString();

        return [
            'quote_id' => $request->integer('quote_id') ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'action' => AuditAction::tryFrom($action)?->value,
            'from' => $this->date($request->string('from')->toString()),
            'to' => $this->date($request->string('to')->toString()),
        ];
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
