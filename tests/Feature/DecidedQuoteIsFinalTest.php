<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A quote the customer has answered is finished, and stays as they answered it.
 *
 * Not tidiness. M6 hashes the rendered document at the moment of signing, as
 * the evidence of what the signer actually saw (SPEC §9), and an edit
 * afterwards would leave that hash describing something that no longer exists.
 * A denial is covered for the same reason: the terms someone refused are the
 * terms they refused.
 *
 * The screens stop offering these actions, but the screens are not the
 * protection. A stale tab, a bookmarked address or a second window reaches
 * every one of them, which is why each is tested over HTTP rather than by
 * looking at a button.
 */
class DecidedQuoteIsFinalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * The one that was actually reported: the customer dropdown on the edit
     * screen would happily move an approved quote to somebody else.
     *
     * @param  QuoteStatus::*  $status
     */
    #[DataProvider('decisions')]
    public function test_the_customer_cannot_be_changed(QuoteStatus $status)
    {
        $quote = $this->decidedQuote($status);
        $somebodyElse = Customer::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->put(route('quotes.update', $quote), [
                'customer_id' => $somebodyElse->id,
                'line_items' => [],
            ])
            ->assertSessionHasErrors('quote');

        $this->assertNotSame($somebodyElse->id, $quote->fresh()->customer_id);
    }

    /**
     * The customer was the symptom. Everything the builder submits was
     * editable, which is the part that would have changed what somebody had
     * already agreed to.
     *
     * @param  QuoteStatus::*  $status
     */
    #[DataProvider('decisions')]
    public function test_the_content_cannot_be_changed(QuoteStatus $status)
    {
        $quote = $this->decidedQuote($status);
        $before = $quote->currentVersion->lineItems()->sole();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->put(route('quotes.update', $quote), [
                'customer_id' => $quote->customer_id,
                'line_items' => [[
                    'name' => 'Something else entirely',
                    'quantity' => 99,
                    'unit_price_ex_vat' => 1,
                    'tax_class_id' => $before->tax_class_id,
                ]],
            ])
            ->assertSessionHasErrors('quote');

        $after = $quote->fresh()->currentVersion->lineItems()->sole();

        $this->assertSame($before->name, $after->name);
        $this->assertSame($before->unit_price_ex_vat, $after->unit_price_ex_vat);
    }

    /**
     * @param  QuoteStatus::*  $status
     */
    #[DataProvider('decisions')]
    public function test_no_new_version_can_be_saved(QuoteStatus $status)
    {
        $quote = $this->decidedQuote($status);

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->post(route('quotes.versions.store', $quote), [
                'customer_id' => $quote->customer_id,
                'line_items' => [],
            ])
            ->assertSessionHasErrors('quote');

        $this->assertSame(1, $quote->versions()->count());
    }

    /**
     * @param  QuoteStatus::*  $status
     */
    #[DataProvider('decisions')]
    public function test_it_cannot_be_deleted(QuoteStatus $status)
    {
        $quote = $this->decidedQuote($status);

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->delete(route('quotes.destroy', $quote))
            ->assertSessionHasErrors('quote');

        $this->assertModelExists($quote);
    }

    /**
     * Sending again would move it back to sent, which is a decision being
     * undone by a side effect rather than by anyone deciding to.
     *
     * @param  QuoteStatus::*  $status
     */
    #[DataProvider('decisions')]
    public function test_it_cannot_be_sent_again(QuoteStatus $status)
    {
        $quote = $this->decidedQuote($status);

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->post(route('quotes.send', $quote))
            ->assertSessionHasErrors('quote');

        $this->assertSame($status, $quote->fresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_an_old_version_cannot_be_removed_from_it()
    {
        $quote = $this->decidedQuote(QuoteStatus::Approved);
        $superseded = $quote->currentVersion;
        QuoteVersion::factory()->for($quote)->create(['version_number' => 2]);

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->delete(route('quotes.versions.destroy', [$quote, $superseded]))
            ->assertSessionHasErrors('quote');

        $this->assertModelExists($superseded);
    }

    /**
     * Reading it stays open: the whole point of keeping it is that someone can
     * look at what was agreed.
     */
    public function test_it_can_still_be_read_and_printed()
    {
        $quote = $this->decidedQuote(QuoteStatus::Approved);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.edit', $quote))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('quote.is_editable', false));

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.index', $quote))
            ->assertOk();
    }

    /**
     * Everything above must still work right up to the moment of a decision,
     * or this has locked the wrong thing.
     */
    public function test_a_quote_the_customer_has_not_answered_is_still_editable()
    {
        $quote = $this->decidedQuote(QuoteStatus::Opened);

        $this->actingAs(User::factory()->create())
            ->put(route('quotes.update', $quote), [
                'customer_id' => Customer::factory()->create()->id,
                'line_items' => [],
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, array{QuoteStatus}>
     */
    public static function decisions(): array
    {
        return [
            'approved' => [QuoteStatus::Approved],
            'denied' => [QuoteStatus::Denied],
        ];
    }

    private function decidedQuote(QuoteStatus $status): Quote
    {
        $quote = Quote::factory()->for(Customer::factory())->sent()->create();

        $version = QuoteVersion::factory()->for($quote)->create(['version_number' => 1]);
        $version->lineItems()->create([
            'name' => 'Managed hosting',
            'quantity' => 2,
            'unit_price_ex_vat' => 90.00,
            'tax_class_id' => TaxClass::factory()->create(['percentage' => 21])->id,
        ]);

        $quote->forceFill(['status' => $status])->save();

        return $quote->fresh();
    }
}
