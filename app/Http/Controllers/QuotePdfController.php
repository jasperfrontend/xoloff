<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\RenderQuotePdf;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Support\Pdf\PdfUnavailable;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Download PDF" from inside the app (SPEC §6). The customer's own copy comes
 * through the portal instead, from the same action.
 */
class QuotePdfController extends Controller
{
    public function __construct(private readonly RenderQuotePdf $renderQuotePdf) {}

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
        try {
            return $this->renderQuotePdf->handle($quote, $version);
        } catch (PdfUnavailable $exception) {
            // Shown rather than logged: whoever pressed Download needs to know
            // whether to wait, to fix something, or to give up.
            return back()->withErrors(['pdf' => $exception->getMessage()]);
        }
    }
}
