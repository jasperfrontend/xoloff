<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Quotes\RenderQuotePdf;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\QuoteVersion;
use App\Support\Pdf\PdfUnavailable;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the customer reaches through the magic link (SPEC §7, §8).
 *
 * The whole surface is: read the current version, take a copy, say yes or no.
 * No comment field, no "request a call", no history of their past quotes -
 * SPEC §8 names all three as deliberately absent.
 */
class QuotePortalController extends Controller
{
    public function __construct(
        private readonly QuoteCalculator $calculator,
        private readonly RenderQuotePdf $renderQuotePdf,
    ) {}

    public function __invoke(Quote $quote): InertiaResponse
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

        $quote->load('customer:id,company_name,first_name,last_name');
        $version = $quote->currentVersion()->with('lineItems.taxClass')->first();

        return Inertia::render('portal/Quote', [
            'sender' => $this->sender($settings),
            'quote' => [
                'id' => $quote->id,
                'company_name' => $quote->customer->company_name,
                'contact_person' => $quote->customer->contact_person,
                'valid_until' => $quote->valid_until?->toDateString(),
                // The token reaches the page only inside the addresses that
                // need it, never as a value of its own.
                'pdf_url' => route('portal.quote.pdf', $quote->magic_link_token),
                'approve_url' => route('portal.quote.approve', $quote->magic_link_token),
                'deny_url' => route('portal.quote.deny', $quote->magic_link_token),
                'status' => $quote->status->value,
                // Read back to them, so a note they took the trouble to write
                // visibly landed somewhere.
                'deny_reason' => $quote->deny_reason,
                // Whether the page should still be asking. A version that went
                // missing leaves nothing to decide on, and the controller
                // refuses the same case.
                'can_decide' => ! $quote->hasBeenDecided() && $version !== null,
            ],
            // Null is reachable only if the quote's last version was removed
            // after it was sent. The page then stands as the cover it was in
            // M4 rather than rendering an empty table.
            'version' => $version === null ? null : $this->version($version),
            'totals' => $version === null ? null : $this->calculator->calculate($version),
        ]);
    }

    /**
     * The customer's own copy of the document.
     *
     * The same PDF the two of them download internally, from the same action:
     * a quote that reads differently depending on who asked for it would be
     * the worst kind of bug to find out about late.
     */
    public function pdf(Quote $quote): Response
    {
        // An expired link stops being a way to fetch things, not just a way to
        // read them. Otherwise the gentle page would be a doorway rather than
        // an ending.
        abort_if($quote->hasExpired(), 404);

        $version = $quote->currentVersion;

        abort_if($version === null, 404);

        try {
            return $this->renderQuotePdf->handle($quote, $version);
        } catch (PdfUnavailable) {
            // The customer can neither wait usefully nor fix this, and the
            // internal wording names environment variables at them. The page
            // they came from still shows the whole quote, so sending them back
            // to it is not a dead end.
            return redirect()->route('portal.quote', $quote->magic_link_token)
                ->withErrors(['pdf' => __('De offerte kan op dit moment niet als PDF worden klaargezet. Probeer het over een paar minuten opnieuw.')]);
        }
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
     * The version as the customer reads it: the texts it was saved with, and
     * its lines with the specs that belong to them.
     *
     * @return array<string, mixed>
     */
    private function version(QuoteVersion $version): array
    {
        return [
            'version_number' => $version->version_number,
            'intro_text_snapshot' => $version->intro_text_snapshot,
            'footer_text_snapshot' => $version->footer_text_snapshot,
            'line_items' => $version->lineItems->map(fn (QuoteLineItem $lineItem): array => [
                'id' => $lineItem->id,
                'name' => $lineItem->name,
                'specs' => $lineItem->specs,
                'quantity' => $lineItem->quantity,
                'unit_price_ex_vat' => $lineItem->unit_price_ex_vat,
                'tax_class_percentage' => $lineItem->taxClass->percentage,
            ])->all(),
        ];
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
