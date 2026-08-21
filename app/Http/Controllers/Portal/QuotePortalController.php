<?php

namespace App\Http\Controllers\Portal;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the customer reaches through the magic link (SPEC §7).
 *
 * Deliberately thin for now: it confirms whose quote this is and how long it
 * stands, and it records that the customer looked. Reading the quote itself
 * and approving or denying it is M5 (SPEC §8), which fills this page in rather
 * than replacing it.
 */
class QuotePortalController extends Controller
{
    public function __invoke(Quote $quote): Response
    {
        $settings = AppSettings::current();

        if ($quote->hasExpired()) {
            // Never a 404. The link was real and the customer did nothing
            // wrong; only the timeframe passed (SPEC §7).
            return Inertia::render('portal/Expired', [
                'sender' => $this->sender($settings),
                'quote' => [
                    'id' => $quote->id,
                    'valid_until' => $quote->valid_until?->toDateString(),
                ],
            ]);
        }

        $this->recordTheVisit($quote);

        $quote->load('customer:id,company_name,contact_person');

        return Inertia::render('portal/Quote', [
            'sender' => $this->sender($settings),
            'quote' => [
                'id' => $quote->id,
                'company_name' => $quote->customer->company_name,
                'contact_person' => $quote->customer->contact_person,
                'valid_until' => $quote->valid_until?->toDateString(),
            ],
        ]);
    }

    /**
     * SPEC §7 tracks opening by portal visit rather than by an email pixel,
     * which is widely blocked and unreliable.
     *
     * Only the first visit is an event. Recording every one would mean a row
     * per refresh, and in M7 a notification per refresh - the thing worth
     * knowing is that the quote reached someone who looked at it. A quote
     * already approved or denied is left alone: going back to read it is not
     * a step backwards.
     */
    private function recordTheVisit(Quote $quote): void
    {
        if ($quote->status === QuoteStatus::Sent) {
            $quote->transitionTo(QuoteStatus::Opened);
        }
    }

    /**
     * Who the customer is looking at. Only what has been filled in - the
     * details are still being collected (SPEC §12) and a blank line is better
     * than a placeholder on a page a customer reads.
     *
     * @return array{company_name: string|null, logo_url: string|null}
     */
    private function sender(AppSettings $settings): array
    {
        return [
            'company_name' => $settings->company_name,
            'logo_url' => $settings->logo_path === null
                ? null
                : Storage::disk('public')->url($settings->logo_path),
        ];
    }
}
