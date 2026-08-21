<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DiscountType;
use App\Models\AppSettings;
use App\Models\AuditLogEntry;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Download PDF" (SPEC §6).
 *
 * Gotenberg itself is faked. What is being tested is everything this side of
 * it: what the template says, what is sent, what comes back, and what happens
 * when the container is not there.
 */
class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gotenberg.url', 'https://pdf.test');
        config()->set('services.gotenberg.username', 'xolution');
        config()->set('services.gotenberg.password', 'secret');

        Storage::fake('public');
    }

    public function test_it_returns_a_pdf_to_download()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF-1.7 fake', 200)]);

        $quote = $this->quote();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('quotes.pdf', $quote))
            ->assertOk();

        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF-1.7 fake', $response->getContent());
    }

    public function test_the_filename_says_which_quote_and_which_version()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote(['company_name' => 'Acme Hosting BV']);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.pdf', $quote))
            ->assertHeader(
                'content-disposition',
                'attachment; filename="offerte-'.$quote->id.'-v1-acme-hosting-bv.pdf"',
            );
    }

    public function test_it_sends_the_quote_as_the_document_gotenberg_expects()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $quote));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($request->url(), '/forms/chromium/convert/html')
                // Gotenberg requires the page itself to arrive under this name.
                && str_contains($body, 'filename="index.html"')
                && str_contains($body, 'filename="footer.html"');
        });
    }

    /**
     * SPEC §6 calls page numbering automatic. Chromium does fill the numbers
     * in, but only into a footer document that is supplied - without one the
     * pages come out unnumbered.
     */
    public function test_the_footer_asks_chromium_for_page_numbers()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'class="pageNumber"')
            && str_contains($request->body(), 'class="totalPages"'));
    }

    /**
     * Chromium renders the footer document with a font size of zero unless
     * every element carries an explicit one, so the page numbers come out
     * present and invisible. That is how this shipped until it was rendered
     * against the real container.
     *
     * A faked Gotenberg cannot see an invisible footer, so this asserts the
     * one thing it can: that the markup still carries the sizes. It is a proxy
     * for the real check, and it is here because the real check only ever
     * happens when someone looks at a PDF.
     */
    public function test_the_page_numbers_carry_a_size_chromium_will_honour()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        $footer = view('pdf.footer')->render();

        // Every element between the body and the numbers themselves.
        $this->assertSame(4, substr_count($footer, 'font-size: 9px'));
        $this->assertStringContainsString('class="pageNumber" style="font-size: 9px;"', $footer);
        $this->assertStringContainsString('class="totalPages" style="font-size: 9px;"', $footer);
    }

    /**
     * Chromium's print API owns the page margins and ignores an @page rule in
     * the document, so the template's layout only holds if they are declared
     * in the request itself.
     */
    public function test_the_page_margins_are_declared_rather_than_left_to_css()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($body, 'name="marginTop"')
                && str_contains($body, 'name="marginBottom"')
                && str_contains($body, 'name="marginLeft"')
                && str_contains($body, 'name="marginRight"')
                // Room at the foot for the page numbers.
                && str_contains($body, '24mm');
        });
    }

    public function test_the_document_carries_everything_the_spec_asks_for()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote(['company_name' => 'Acme BV']);

        $quote->currentVersion->update([
            'intro_text_snapshot' => '<p>Beste klant</p>',
            'footer_text_snapshot' => '<p>Algemene voorwaarden van toepassing</p>',
        ]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $quote));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($body, 'Acme BV')
                && str_contains($body, '<p>Beste klant</p>')
                && str_contains($body, '<p>Algemene voorwaarden van toepassing</p>')
                && str_contains($body, 'Managed hosting')
                && str_contains($body, 'Billing period');
        });
    }

    /**
     * SPEC §7: the template printed the logo and the customer but nothing
     * identifying the sender, which is conspicuous on Dutch business
     * correspondence.
     */
    public function test_it_prints_xolutions_own_details_alongside_the_customers()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        AppSettings::current()->update([
            'company_name' => 'Xolution',
            'company_address' => 'Voorbeeldstraat 1',
            'company_kvk' => '01234567',
            'company_vat_number' => 'NL001234567B01',
        ]);

        $quote = $this->quote(['company_name' => 'Acme BV']);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $quote));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($body, 'Xolution')
                && str_contains($body, 'Voorbeeldstraat 1')
                && str_contains($body, 'KvK 01234567')
                && str_contains($body, 'BTW NL001234567B01')
                // Both parties, not one in place of the other.
                && str_contains($body, 'Acme BV');
        });
    }

    /**
     * The values are still being collected (SPEC §12), so a blank block is a
     * legitimate state - and an empty "Van" heading over nothing would reach a
     * customer looking like a fault.
     */
    public function test_it_prints_no_sender_block_while_the_details_are_blank()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), '>Van<')
            && str_contains($request->body(), '>Aan<'));
    }

    public function test_it_prints_the_details_that_have_been_filled_in_so_far()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        AppSettings::current()->update(['company_name' => 'Xolution']);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '>Van<')
            && str_contains($request->body(), 'Xolution')
            // The registration numbers are the block that stays away: "BTW"
            // on its own also heads a column in the line items table.
            && ! str_contains($request->body(), 'class="registration"'));
    }

    /**
     * Read live rather than snapshotted onto the version: where the sender
     * sits today is a fact about the sender, not part of what was offered, so
     * a corrected address belongs on a reprint of an old version too.
     */
    public function test_a_corrected_address_shows_on_a_reprint_of_an_old_version()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        AppSettings::current()->update(['company_address' => 'Oude straat 1']);

        $quote = $this->quote();
        $first = $quote->currentVersion;

        QuoteVersion::factory()->for($quote)->create(['version_number' => 2]);

        AppSettings::current()->update(['company_address' => 'Nieuwe straat 2']);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.pdf', [$quote, $first]))
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Nieuwe straat 2')
            && ! str_contains($request->body(), 'Oude straat 1'));
    }

    /**
     * Every figure a customer reads has to come from the engine, not from a
     * second calculation written into the template.
     */
    public function test_the_figures_are_the_engine_figures_in_dutch()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            // Two at 90.00 with 21% VAT: 180,00 net, 37,80 VAT, 217,80 total.
            return str_contains($body, '180,00')
                && str_contains($body, '37,80')
                && str_contains($body, '217,80');
        });
    }

    public function test_a_rounding_override_is_what_the_customer_sees()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote();
        $quote->currentVersion->update(['rounding_override' => 200.00]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $quote));

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '200,00')
            && ! str_contains($request->body(), '217,80'));
    }

    public function test_a_quote_discount_is_shown_rather_than_folded_away()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote();
        $quote->currentVersion->update([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
        ]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $quote));

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Korting op de offerte'));
    }

    /**
     * Gotenberg renders in its own container, so a link back to this
     * application is not necessarily one it can reach.
     */
    public function test_the_logo_is_embedded_rather_than_linked()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        AppSettings::current()->update([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_vector_mime' => 'image/svg+xml',
            'logo_vector_data' => base64_encode('bytes'),
        ]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($body, 'src="data:image/svg+xml;base64,'.base64_encode('bytes'))
                // Neither a link back to this application, which Gotenberg
                // cannot rely on reaching, nor the address the logo came from,
                // which would put someone else's web server in the path of
                // printing a quote.
                && ! str_contains($body, 'src="/storage/')
                && ! str_contains($body, 'https://xolution.test/logo.svg');
        });
    }

    public function test_it_prints_without_a_logo_when_none_was_saved()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->assertFalse(AppSettings::current()->hasWebLogo());

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.pdf', $this->quote()))
            ->assertOk();
    }

    public function test_an_old_version_reprints_as_it_went_out()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote();
        $first = $quote->currentVersion;
        $first->update(['footer_text_snapshot' => '<p>Voorwaarden van toen</p>']);

        QuoteVersion::factory()->for($quote)->create([
            'version_number' => 2,
            'footer_text_snapshot' => '<p>Voorwaarden van nu</p>',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.pdf', [$quote, $first]))
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Voorwaarden van toen')
            && ! str_contains($request->body(), 'Voorwaarden van nu'));
    }

    public function test_a_version_belonging_to_another_quote_is_not_found()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $mine = $this->quote();
        $theirs = $this->quote();

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.pdf', [$mine, $theirs->currentVersion]))
            ->assertNotFound();
    }

    /**
     * SPEC §6 requires PDF actions to be visible in the audit log.
     */
    public function test_a_download_is_recorded()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $quote = $this->quote();

        AuditLogEntry::query()->delete();

        $this->actingAs($user = User::factory()->create())
            ->get(route('quotes.pdf', $quote))
            ->assertOk();

        $entry = AuditLogEntry::sole();

        $this->assertSame(AuditAction::Exported, $entry->action);
        $this->assertSame('quote_version', $entry->entity_type);
        $this->assertSame($quote->id, $entry->payload['quote_id']);
        $this->assertSame($user->id, $entry->user_id);
    }

    public function test_a_failed_download_is_not_recorded_as_one()
    {
        Http::fake(['pdf.test/*' => Http::response('boom', 500)]);

        $quote = $this->quote();

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->get(route('quotes.pdf', $quote))
            ->assertSessionHasErrors('pdf');

        $this->assertDatabaseCount('audit_log', 0);
    }

    /**
     * The container sleeps when idle, so a first request after a quiet spell
     * genuinely can time out. That is worth waiting through rather than
     * reporting as a fault.
     */
    public function test_an_unreachable_service_says_to_try_again()
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->get(route('quotes.pdf', $quote))
            ->assertRedirect(route('quotes.edit', $quote))
            ->assertSessionHasErrors(['pdf' => 'The PDF service did not respond. It may be starting up, so try again in a moment.']);
    }

    /**
     * The container is behind basic auth (SPEC §7). Sending the credentials is
     * the whole of what this side has to do about that.
     */
    public function test_it_authenticates_to_the_container()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF', 200)]);

        $this->actingAs(User::factory()->create())->get(route('quotes.pdf', $this->quote()));

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('xolution:secret'),
        ));
    }

    /**
     * A 401 has one cause worth naming: the credentials here have drifted from
     * the ones the container was started with. "Refused (401)" would send
     * whoever hit it looking at the template instead.
     */
    public function test_refused_credentials_say_which_two_settings_to_look_at()
    {
        Http::fake(['pdf.test/*' => Http::response('unauthorized', 401)]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->get(route('quotes.pdf', $quote))
            ->assertSessionHasErrors([
                'pdf' => 'The PDF service refused our credentials. GOTENBERG_API_BASIC_AUTH_USERNAME and GOTENBERG_API_BASIC_AUTH_PASSWORD no longer match what the container was started with.',
            ]);
    }

    public function test_any_other_refusal_still_reports_its_status()
    {
        Http::fake(['pdf.test/*' => Http::response('boom', 503)]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->get(route('quotes.pdf', $quote))
            ->assertSessionHasErrors(['pdf' => 'The PDF service refused the request (503).']);
    }

    public function test_a_missing_configuration_explains_itself_rather_than_failing()
    {
        config()->set('services.gotenberg.url', null);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->get(route('quotes.pdf', $quote))
            ->assertSessionHasErrors(['pdf' => 'The PDF service is not configured yet, so quotes cannot be downloaded.']);

        Http::assertNothingSent();
    }

    public function test_a_quote_with_no_version_says_so()
    {
        Http::fake();

        $quote = Quote::factory()->for(Customer::factory())->create();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->get(route('quotes.pdf', $quote))
            ->assertSessionHasErrors('pdf');

        Http::assertNothingSent();
    }

    public function test_a_guest_cannot_download_anything()
    {
        Http::fake();

        $quote = $this->quote();

        $this->get(route('quotes.pdf', $quote))->assertRedirect(route('login'));
        $this->get(route('quotes.versions.pdf', [$quote, $quote->currentVersion]))
            ->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $customerAttributes
     */
    private function quote(array $customerAttributes = []): Quote
    {
        $quote = Quote::factory()
            ->for(Customer::factory()->state($customerAttributes))
            ->create();

        $version = QuoteVersion::factory()->for($quote)->create(['version_number' => 1]);

        $version->lineItems()->create([
            'name' => 'Managed hosting',
            'specs' => ['Billing period' => 'Monthly'],
            'quantity' => 2,
            'unit_price_ex_vat' => 90.00,
            'tax_class_id' => TaxClass::factory()->create(['percentage' => 21.00])->id,
        ]);

        return $quote->fresh();
    }
}
