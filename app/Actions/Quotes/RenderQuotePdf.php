<?php

namespace App\Actions\Quotes;

use App\Enums\AuditAction;
use App\Models\AppSettings;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Support\Audit\AuditLog;
use App\Support\Pdf\Gotenberg;
use App\Support\Pdf\PdfUnavailable;
use App\Support\Quotes\QuoteCalculator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a quote version into a downloadable PDF (SPEC §6).
 *
 * Shared by the internal download and the customer's own, because from M5 the
 * customer gets the document too and the two must be the same document. A
 * second copy of the margins, the logo embedding and the filename would drift.
 */
final class RenderQuotePdf
{
    /**
     * The page margins the quote template is designed around. They live here
     * rather than in the template's CSS because Chromium's print API owns them
     * and ignores an @page rule. The extra room at the foot is for the page
     * numbers Gotenberg repeats there.
     *
     * @var array<string, string>
     */
    private const MARGINS = [
        'top' => '18mm',
        'bottom' => '24mm',
        'left' => '16mm',
        'right' => '16mm',
    ];

    public function __construct(
        private readonly Gotenberg $gotenberg,
        private readonly QuoteCalculator $calculator,
    ) {}

    /**
     * @throws PdfUnavailable
     */
    public function handle(Quote $quote, QuoteVersion $version): Response
    {
        $quote->loadMissing('customer');
        $version->loadMissing('lineItems.taxClass');

        $settings = AppSettings::current();

        $html = view('pdf.quote', [
            'quote' => $quote,
            'version' => $version,
            'totals' => $this->calculator->calculate($version),
            // Keyed by id so the template can reach a line's specs without
            // trusting the engine and the database to agree on ordering.
            'lineItems' => $version->lineItems->keyBy('id'),
            // Xolution's own identity, printed opposite the customer's
            // (SPEC §7). Read live rather than snapshotted onto the version:
            // an address is a fact about the sender today, not part of what
            // was offered, so a correction should show on a reprint.
            'settings' => $settings,
            'logo' => $this->logo($settings),
        ])->render();

        $pdf = $this->gotenberg->render($html, view('pdf.footer')->render(), self::MARGINS);

        $filename = $this->filename($quote, $version);

        // After the render, so a failed one is not recorded as a download.
        // The user is null when the customer is the one downloading, which is
        // what the audit log's nullable user_id is for.
        AuditLog::record($version, AuditAction::Exported, [
            ...$version->auditContext(),
            'attributes' => ['filename' => $filename],
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * The logo as a data url rather than a link.
     *
     * Gotenberg renders in its own container, so an address pointing back at
     * this application is not necessarily one it can reach - and on a private
     * network it certainly is not. Embedding sidesteps that entirely, and it
     * puts the logo's bytes inside the document M6 hashes at signing rather
     * than an address that could later answer with something else.
     */
    private function logo(AppSettings $settings): ?string
    {
        return $settings->webLogo()?->toDataUri();
    }

    private function filename(Quote $quote, QuoteVersion $version): string
    {
        // No spaces and no accents: this string travels through a download
        // header and a customer's file system.
        $customer = preg_replace('/[^A-Za-z0-9]+/', '-', $quote->customer->company_name) ?? '';

        return trim("offerte-{$quote->id}-v{$version->version_number}-".strtolower($customer), '-').'.pdf';
    }
}
