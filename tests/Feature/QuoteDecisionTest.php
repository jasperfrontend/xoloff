<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\QuoteStatus;
use App\Models\AuditLogEntry;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The customer saying yes or no through the magic link (SPEC §8).
 *
 * Both answers are final. A quote is superseded by a new one rather than
 * re-decided, and a stale tab is all it takes to submit twice, so the guards
 * are what make that true rather than a convention.
 */
class QuoteDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_approve_without_signing_in()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.approve', $quote->magic_link_token))
            ->assertRedirect(route('portal.quote', $quote->magic_link_token));

        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
    }

    public function test_a_customer_can_deny_and_say_why()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.deny', $quote->magic_link_token), [
            'reason' => 'Te duur voor dit kwartaal.',
        ])->assertRedirect(route('portal.quote', $quote->magic_link_token));

        $quote->refresh();

        $this->assertSame(QuoteStatus::Denied, $quote->status);
        $this->assertSame('Te duur voor dit kwartaal.', $quote->deny_reason);
    }

    /**
     * SPEC §8 opens a reason box on denial rather than demanding one. Someone
     * who does not want to explain themselves must still be able to decline.
     */
    public function test_denying_without_a_reason_is_allowed()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.deny', $quote->magic_link_token))
            ->assertSessionHasNoErrors();

        $quote->refresh();

        $this->assertSame(QuoteStatus::Denied, $quote->status);
        $this->assertNull($quote->deny_reason);
    }

    /**
     * Whitespace is not a reason, and storing it would put an empty
     * blockquote on the page that reads it back.
     */
    public function test_a_blank_reason_is_stored_as_no_reason()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.deny', $quote->magic_link_token), [
            'reason' => "   \n  ",
        ]);

        $this->assertNull($quote->fresh()->deny_reason);
    }

    /**
     * The only field in xoloff a stranger can write to, and it is read back on
     * a page.
     */
    public function test_an_absurdly_long_reason_is_refused()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.deny', $quote->magic_link_token), [
            'reason' => str_repeat('a', 2001),
        ])->assertSessionHasErrors('reason');

        $this->assertSame(QuoteStatus::Opened, $quote->fresh()->status);
    }

    /**
     * A quote can be decided straight from the email without the portal page
     * having been rendered first, so sent is as valid a starting point as
     * opened.
     */
    public function test_a_quote_can_be_decided_before_it_was_ever_opened()
    {
        $quote = $this->sentQuote();

        $this->post(route('portal.quote.approve', $quote->magic_link_token));

        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
    }

    public function test_a_decision_cannot_be_changed_by_submitting_again()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.approve', $quote->magic_link_token));
        $this->post(route('portal.quote.deny', $quote->magic_link_token), ['reason' => 'Toch niet']);

        $quote->refresh();

        $this->assertSame(QuoteStatus::Approved, $quote->status);
        $this->assertNull($quote->deny_reason);
    }

    /**
     * A stale tab is all it takes to submit twice, and a second entry would
     * mean a second notification in M7.
     */
    public function test_submitting_the_same_decision_twice_is_one_event()
    {
        $quote = $this->openedQuote();

        AuditLogEntry::query()->delete();

        $this->post(route('portal.quote.approve', $quote->magic_link_token));
        $this->post(route('portal.quote.approve', $quote->magic_link_token));

        $this->assertDatabaseCount('audit_log', 1);
    }

    public function test_an_expired_link_cannot_be_used_to_decide()
    {
        $quote = $this->openedQuote(validForDays: -1);

        $this->post(route('portal.quote.approve', $quote->magic_link_token))
            ->assertRedirect(route('portal.quote', $quote->magic_link_token));

        $this->assertSame(QuoteStatus::Opened, $quote->fresh()->status);
    }

    /**
     * Only reachable if the last version was removed after the quote was sent.
     * There is nothing on the page to agree to.
     */
    public function test_a_quote_with_nothing_in_it_cannot_be_decided()
    {
        $quote = Quote::factory()->for(Customer::factory())->sent()->create();

        $this->post(route('portal.quote.approve', $quote->magic_link_token));

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }

    public function test_an_unknown_link_decides_nothing()
    {
        $this->post(route('portal.quote.approve', str_repeat('a', 64)))->assertNotFound();
        $this->post(route('portal.quote.deny', str_repeat('a', 64)))->assertNotFound();
    }

    /**
     * SPEC §8: both actions correctly update status and are logged.
     */
    public function test_an_approval_is_recorded_as_a_status_change()
    {
        $quote = $this->openedQuote();

        AuditLogEntry::query()->delete();

        $this->post(route('portal.quote.approve', $quote->magic_link_token));

        $entry = AuditLogEntry::sole();

        $this->assertSame(AuditAction::StatusChanged, $entry->action);
        $this->assertSame('quote', $entry->entity_type);
        $this->assertSame($quote->id, $entry->payload['quote_id']);
        $this->assertSame('opened', $entry->payload['changes']['status']['from']);
        $this->assertSame('approved', $entry->payload['changes']['status']['to']);
        // Nobody is signed in, which is what the log's nullable user_id is for.
        $this->assertNull($entry->user_id);
    }

    /**
     * The reason is the substance of the event, so it belongs in the entry
     * rather than only in a column the change log deliberately ignores.
     */
    public function test_a_denial_records_the_reason_with_it()
    {
        $quote = $this->openedQuote();

        AuditLogEntry::query()->delete();

        $this->post(route('portal.quote.deny', $quote->magic_link_token), [
            'reason' => 'Te duur voor dit kwartaal.',
        ]);

        $entry = AuditLogEntry::sole();

        $this->assertSame(AuditAction::StatusChanged, $entry->action);
        $this->assertSame('denied', $entry->payload['changes']['status']['to']);
        $this->assertSame('Te duur voor dit kwartaal.', $entry->payload['attributes']['deny_reason']);
    }

    public function test_the_page_stops_asking_once_a_decision_is_made()
    {
        $quote = $this->openedQuote();

        $this->get(route('portal.quote', $quote->magic_link_token))
            ->assertInertia(fn (Assert $page) => $page->where('quote.can_decide', true));

        $this->post(route('portal.quote.deny', $quote->magic_link_token), ['reason' => 'Nee']);

        $this->get(route('portal.quote', $quote->magic_link_token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('quote.can_decide', false)
                ->where('quote.status', 'denied')
                ->where('quote.deny_reason', 'Nee'),
            );
    }

    /**
     * Going back to read a quote already settled is not a step backwards.
     */
    public function test_reading_a_decided_quote_does_not_reopen_it()
    {
        $quote = $this->openedQuote();

        $this->post(route('portal.quote.approve', $quote->magic_link_token));
        $this->get(route('portal.quote', $quote->magic_link_token))->assertOk();

        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
    }

    private function sentQuote(int $validForDays = 30): Quote
    {
        $quote = Quote::factory()->for(Customer::factory())->sent($validForDays)->create();

        QuoteVersion::factory()->for($quote)->create(['version_number' => 1]);

        return $quote->fresh();
    }

    private function openedQuote(int $validForDays = 30): Quote
    {
        $quote = $this->sentQuote($validForDays);

        $quote->forceFill(['status' => QuoteStatus::Opened])->save();

        return $quote->fresh();
    }
}
