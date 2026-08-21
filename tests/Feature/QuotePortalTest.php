<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\QuoteStatus;
use App\Models\AppSettings;
use App\Models\AuditLogEntry;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The magic link, and what visiting it records (SPEC §7).
 *
 * Reading the quote itself and approving or denying it is M5, so what this
 * covers is the link working, the visit being noticed, and the window closing
 * gently rather than with a 404.
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

    /**
     * The page is served to whoever holds the link, so what it hands back
     * matters. The address necessarily contains the token; the props must not
     * carry it again, nor anything about the two people who built the quote.
     */
    public function test_the_page_carries_nothing_it_should_not()
    {
        $quote = $this->sentQuote();

        $response = $this->get($this->link($quote))->assertOk();

        /** @var array{props: array<string, mixed>} $page */
        $page = $response->viewData('page');

        $this->assertStringNotContainsString(
            (string) $quote->magic_link_token,
            (string) json_encode($page['props']),
        );

        $response->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
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
}
