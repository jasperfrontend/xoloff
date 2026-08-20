<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AppSettings;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Support\Audit\AuditLog;
use App\Support\Pdf\Gotenberg;
use App\Support\Pdf\PdfUnavailable;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Download PDF" (SPEC §6). No email, no portal, no signature involved - those
 * are M4 and later.
 */
class QuotePdfController extends Controller
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
     * The quote as it currently stands.
     */
    public function current(Quote $quote): Response|RedirectResponse
    {
        $version = $quote->currentVersion;

        if ($version === null) {
            return back()->withErrors(['pdf' => __('This quote has no saved version to download yet.')]);
        }

        return $this->download($quote, $version);
    }

    /**
     * A specific version, which is how a quote already sent can be reprinted
     * exactly as it went out.
     */
    public function version(Quote $quote, QuoteVersion $version): Response|RedirectResponse
    {
        abort_unless($version->quote_id === $quote->id, 404);

        return $this->download($quote, $version);
    }

    private function download(Quote $quote, QuoteVersion $version): Response|RedirectResponse
    {
        $quote->load('customer');
        $version->load('lineItems.taxClass');

        $html = view('pdf.quote', [
            'quote' => $quote,
            'version' => $version,
            'totals' => $this->calculator->calculate($version),
            // Keyed by id so the template can reach a line's specs without
            // trusting the engine and the database to agree on ordering.
            'lineItems' => $version->lineItems->keyBy('id'),
            'logo' => $this->logo(),
        ])->render();

        try {
            $pdf = $this->gotenberg->render($html, view('pdf.footer')->render(), self::MARGINS);
        } catch (PdfUnavailable $exception) {
            // Shown rather than logged: whoever pressed Download needs to know
            // whether to wait, to fix something, or to give up.
            return back()->withErrors(['pdf' => $exception->getMessage()]);
        }

        AuditLog::record($version, AuditAction::Exported, [
            ...$version->auditContext(),
            'attributes' => ['filename' => $this->filename($quote, $version)],
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($quote, $version).'"',
        ]);
    }

    /**
     * The logo as a data url rather than a link.
     *
     * Gotenberg renders in its own container, so an address pointing back at
     * this application is not necessarily one it can reach - and on a private
     * network it certainly is not. Embedding sidesteps that entirely.
     */
    private function logo(): ?string
    {
        $path = AppSettings::current()->logo_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($path)
            .';base64,'.base64_encode(Storage::disk('public')->get($path) ?? '');
    }

    private function filename(Quote $quote, QuoteVersion $version): string
    {
        // No spaces and no accents: this string travels through a download
        // header and a customer's file system.
        $customer = preg_replace('/[^A-Za-z0-9]+/', '-', $quote->customer->company_name) ?? '';

        return trim("offerte-{$quote->id}-v{$version->version_number}-".strtolower($customer), '-').'.pdf';
    }
}
