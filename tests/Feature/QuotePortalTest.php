<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\QuoteStatus;
use App\Models\AppSettings;
use App\Models\AuditLogEntry;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The magic link, and what visiting it records (SPEC §7).
 *
 * Covers the link working, the visit being noticed, the window closing gently
 * rather than with a 404, and the quote itself being readable (SPEC §8).
 */
class QuotePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_open_their_link_without_signing_in()
    {
        $quote = $this->sentQuote(['company_name' => 'Acme BV', 'contact_person' => 'Anna']);

        $this->get($this->link($quote))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Quote')
                ->where('quote.company_name', 'Acme BV')
                ->where('quote.contact_person', 'Anna'),
            );
    }

    /**
     * SPEC §7 tracks opening by portal visit rather than by an email pixel,
     * which is widely blocked and unreliable.
     */
    public function test_visiting_the_link_records_that_the_quote_was_opened()
    {
        $quote = $this->sentQuote();

        AuditLogEntry::query()->delete();

        $this->get($this->link($quote))->assertOk();

        $this->assertSame(QuoteStatus::Opened, $quote->fresh()->status);

        $entry = AuditLogEntry::sole();

        $this->assertSame(AuditAction::StatusChanged, $entry->action);
        $this->assertSame('sent', $entry->payload['changes']['status']['from']);
        $this->assertSame('opened', $entry->payload['changes']['status']['to']);
    }

    /**
     * Nobody is signed in, so the entry has no person behind it - which is the
     * case the audit log's nullable user_id exists for.
     */
    public function test_the_visit_is_recorded_without_a_user()
    {
        $quote = $this->sentQuote();

        AuditLogEntry::query()->delete();

        $this->get($this->link($quote));

        $this->assertNull(AuditLogEntry::sole()->user_id);
    }

    /**
     * One row per refresh would be noise, and in M7 a notification per refresh.
     * What is worth knowing is that the quote reached someone who looked.
     */
    public function test_reading_it_again_is_not_a_second_event()
    {
        $quote = $this->sentQuote();

        $this->get($this->link($quote));

        AuditLogEntry::query()->delete();

        $this->get($this->link($quote))->assertOk();

        $this->assertSame(QuoteStatus::Opened, $quote->fresh()->status);
        $this->assertDatabaseCount('audit_log', 0);
    }

    /**
     * Going back to read a quote already settled is not a step backwards.
     */
    public function test_a_settled_quote_is_not_reopened_by_a_visit()
    {
        $quote = $this->sentQuote();
        $quote->forceFill(['status' => QuoteStatus::Approved])->save();

        $this->get($this->link($quote))->assertOk();

        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
    }

    /**
     * SPEC §7: never a harsh "not found" - the link was real and the customer
     * did nothing wrong, only the timeframe passed.
     */
    public function test_an_expired_link_says_so_gently()
    {
        $quote = $this->sentQuote(validForDays: -1);

        $this->get($this->link($quote))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('portal/Expired'));
    }

    /**
     * They did not get to see it, so recording that they opened it would be
     * false - and in M7 it would tell Stephan something that did not happen.
     */
    public function test_an_expired_visit_is_not_recorded_as_an_opening()
    {
        $quote = $this->sentQuote(validForDays: -1);

        AuditLogEntry::query()->delete();

        $this->get($this->link($quote));

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertDatabaseCount('audit_log', 0);
    }

    /**
     * A quote valid until the 20th is valid for all of the 20th. valid_until
     * is a date for exactly this reason: an expiry falling at midnight would
     * be a support question rather than a rule.
     */
    public function test_the_last_day_of_the_window_still_counts()
    {
        $quote = $this->sentQuote(validForDays: 0);

        $this->get($this->link($quote))
            ->assertInertia(fn (Assert $page) => $page->component('portal/Quote'));
    }

    public function test_an_unknown_link_is_not_found()
    {
        $this->get(route('portal.quote', str_repeat('a', 64)))->assertNotFound();
    }

    /**
     * A quote nobody has sent has no link at all, so there is nothing for a
     * blank or missing token to match.
     */
    public function test_a_quote_that_was_never_sent_has_no_link()
    {
        Quote::factory()->for(Customer::factory())->create();

        $this->get(route('portal.quote', 'null'))->assertNotFound();
    }

    public function test_the_customer_can_read_the_quote_itself()
    {
        $quote = $this->sentQuote();
        $this->addAVersionTo($quote);

        $this->get($this->link($quote))
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Quote')
                ->where('version.version_number', 1)
                ->has('version.line_items', 1)
                ->where('version.line_items.0.name', 'Managed hosting')
                ->where('version.line_items.0.specs.Billing period', 'Monthly'),
            );
    }

    /**
     * SPEC §5 is implemented once, in PHP. The page displays what the engine
     * says and holds no second opinion about the money.
     */
    public function test_the_figures_come_from_the_engine()
    {
        $quote = $this->sentQuote();
        $this->addAVersionTo($quote);

        $this->get($this->link($quote))
            ->assertInertia(fn (Assert $page) => $page
                // Two at 90.00 with 21% VAT: 180.00 net, 37.80 VAT, 217.80.
                ->where('totals.subtotal', '180.00')
                ->where('totals.total', '217.80')
                ->where('totals.taxClassTotals.0.vat', '37.80'),
            );
    }

    /**
     * Snapshotted with the version, so a quote already sent keeps the terms it
     * was sent under (SPEC §3).
     */
    public function test_the_texts_are_the_ones_the_version_was_saved_with()
    {
        $quote = $this->sentQuote();
        $version = $this->addAVersionTo($quote);
        $version->update([
            'intro_text_snapshot' => '<p>Beste klant</p>',
            'footer_text_snapshot' => '<p>Algemene voorwaarden van toepassing</p>',
        ]);

        $this->get($this->link($quote))
            ->assertInertia(fn (Assert $page) => $page
                ->where('version.intro_text_snapshot', '<p>Beste klant</p>')
                ->where('version.footer_text_snapshot', '<p>Algemene voorwaarden van toepassing</p>'),
            );
    }

    /**
     * Only reachable if the last version was removed after the quote was sent.
     * The page then stands as the cover it was rather than rendering an empty
     * table at a customer.
     */
    public function test_a_quote_whose_version_went_missing_still_opens()
    {
        $quote = $this->sentQuote();

        $this->get($this->link($quote))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Quote')
                ->where('version', null)
                ->where('totals', null),
            );
    }

    /**
     * The same document the two of them download internally, from the same
     * action. A quote that read differently depending on who asked for it
     * would be the worst kind of bug to find late.
     */
    public function test_the_customer_can_take_a_copy_as_a_pdf()
    {
        Http::fake(['pdf.test/*' => Http::response('%PDF-1.7 fake', 200)]);
        config()->set('services.gotenberg.url', 'https://pdf.test');

        $quote = $this->sentQuote(['company_name' => 'Acme BV']);
        $this->addAVersionTo($quote);

        $response = $this->get(route('portal.quote.pdf', $quote->magic_link_token))
            ->assertOk();

        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader(
            'content-disposition',
            'attachment; filename="offerte-'.$quote->id.'-v1-acme-bv.pdf"',
        );

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Managed hosting'));
    }

    /**
     * An expired link stops being a way to fetch things, not only a way to
     * read them. Otherwise the gentle page would be a doorway rather than an
     * ending.
     */
    public function test_an_expired_link_cannot_still_fetch_the_pdf()
    {
        Http::fake();
        config()->set('services.gotenberg.url', 'https://pdf.test');

        $quote = $this->sentQuote(validForDays: -1);
        $this->addAVersionTo($quote);

        $this->get(route('portal.quote.pdf', $quote->magic_link_token))->assertNotFound();

        Http::assertNothingSent();
    }

    /**
     * The customer can neither wait usefully nor act on the internal wording,
     * which names environment variables. The page they came from still shows
     * the whole quote, so this is not a dead end.
     */
    public function test_a_pdf_that_cannot_be_produced_sends_them_back_in_dutch()
    {
        config()->set('services.gotenberg.url', null);

        $quote = $this->sentQuote();
        $this->addAVersionTo($quote);

        $this->get(route('portal.quote.pdf', $quote->magic_link_token))
            ->assertRedirect(route('portal.quote', $quote->magic_link_token))
            ->assertSessionHasErrors(['pdf' => 'De offerte kan op dit moment niet als PDF worden klaargezet. Probeer het over een paar minuten opnieuw.']);
    }

    public function test_an_unknown_link_has_no_pdf_either()
    {
        Http::fake();

        $this->get(route('portal.quote.pdf', str_repeat('a', 64)))->assertNotFound();

        Http::assertNothingSent();
    }

    /**
     * The page is served to whoever holds the link, so what it hands back
     * matters.
     *
     * The token is in the address, and legitimately in the props too - the
     * download and the two decisions all have to be addressable. What this
     * pins is that those addresses are the only place it appears: a credential
     * with three reasons to be on a page should not quietly acquire a fourth.
     */
    public function test_the_token_appears_only_inside_the_addresses_that_need_it()
    {
        $quote = $this->sentQuote();
        $token = (string) $quote->magic_link_token;

        $response = $this->get($this->link($quote))->assertOk();

        /** @var array{props: array<string, mixed>} $page */
        $page = $response->viewData('page');

        /** @var array<string, mixed> $quoteProps */
        $quoteProps = $page['props']['quote'];

        foreach (['pdf_url', 'approve_url', 'deny_url'] as $key) {
            $this->assertStringContainsString($token, (string) $quoteProps[$key]);

            unset($quoteProps[$key]);
        }

        $props = $page['props'];
        $props['quote'] = $quoteProps;

        $this->assertStringNotContainsString($token, (string) json_encode($props));
    }

    /**
     * Nothing about the two people who built the quote reaches the customer.
     */
    public function test_the_page_says_nothing_about_who_built_it()
    {
        $this->get($this->link($this->sentQuote()))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_the_page_shows_who_the_quote_is_from()
    {
        AppSettings::current()->update(['company_name' => 'Xolution']);

        $quote = $this->sentQuote();

        $this->get($this->link($quote))
            ->assertInertia(fn (Assert $page) => $page->where('sender.company_name', 'Xolution'));
    }

    /**
     * @param  array<string, mixed>  $customerAttributes
     */
    private function sentQuote(array $customerAttributes = [], int $validForDays = 30): Quote
    {
        return Quote::factory()
            ->for(Customer::factory()->state($customerAttributes))
            ->sent($validForDays)
            ->create();
    }

    private function link(Quote $quote): string
    {
        return route('portal.quote', $quote->magic_link_token);
    }

    private function addAVersionTo(Quote $quote): QuoteVersion
    {
        $version = QuoteVersion::factory()->for($quote)->create(['version_number' => 1]);

        $version->lineItems()->create([
            'name' => 'Managed hosting',
            'specs' => ['Billing period' => 'Monthly'],
            'quantity' => 2,
            'unit_price_ex_vat' => 90.00,
            'tax_class_id' => TaxClass::factory()->create(['percentage' => 21.00])->id,
        ]);

        return $version->fresh();
    }
}
