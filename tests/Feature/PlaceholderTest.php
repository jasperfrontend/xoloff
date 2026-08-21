<?php

namespace Tests\Feature;

use App\Actions\Quotes\SaveQuoteVersion;
use App\Enums\Placeholder;
use App\Enums\PremadeTextKey;
use App\Enums\Salutation;
use App\Models\Customer;
use App\Models\PremadeText;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use App\Support\Text\Placeholders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Placeholders in the quote texts, so a greeting addresses the customer it is
 * actually going to.
 *
 * Not in the spec. It exists because the alternative was a hardcoded greeting,
 * and any hardcoded greeting is an opinion about how Xolution talks to its
 * clients.
 */
class PlaceholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_every_placeholder_there_is()
    {
        $customer = $this->customer();

        $filled = Placeholders::fill(
            '<p>[[[customer_salutation]]]|[[[customer_first_name]]]|[[[customer_last_name]]]'
            .'|[[[customer_full_name]]]|[[[customer_company_name]]]</p>',
            $customer,
        );

        $this->assertSame('<p>heer|Daan|Daansen|Daan Daansen|Daan Test BV</p>', $filled);
    }

    /**
     * A gap rather than the word "null". It closes up on its own, because
     * everything this feeds is HTML and HTML collapses runs of whitespace.
     */
    public function test_a_customer_with_no_salutation_leaves_a_gap()
    {
        $customer = $this->customer(['salutation' => null]);

        $this->assertSame(
            '<p>Geachte  Daansen,</p>',
            Placeholders::fill('<p>Geachte [[[customer_salutation]]] [[[customer_last_name]]],</p>', $customer),
        );
    }

    /**
     * The customer reading "[[[customer_frist_name]]]" in their quote is worse
     * than a missing word, and the editor lists every spelling that works.
     */
    public function test_a_misspelled_placeholder_leaves_nothing_behind()
    {
        $filled = Placeholders::fill('<p>Beste [[[customer_frist_name]]],</p>', $this->customer());

        $this->assertSame('<p>Beste ,</p>', $filled);
    }

    /**
     * These values reach the PDF and the portal as markup.
     */
    public function test_a_name_that_looks_like_markup_is_escaped()
    {
        $customer = $this->customer(['company_name' => 'Tom & Jerry <b>BV</b>']);

        $this->assertSame(
            '<p>Tom &amp; Jerry &lt;b&gt;BV&lt;/b&gt;</p>',
            Placeholders::fill('<p>[[[customer_company_name]]]</p>', $customer),
        );
    }

    public function test_text_without_placeholders_is_left_exactly_alone()
    {
        $text = '<p>Hierbij ontvangt u onze offerte. Kosten: [zie bijlage]</p>';

        $this->assertSame($text, Placeholders::fill($text, $this->customer()));
    }

    /**
     * Built from the enum, so the list someone reads in the editor and the
     * list that actually resolves cannot drift apart.
     */
    public function test_the_editor_is_offered_every_placeholder_that_resolves()
    {
        $offered = array_column(Placeholders::all(), 'token');

        foreach (Placeholder::cases() as $placeholder) {
            $this->assertContains($placeholder->token(), $offered);
        }

        $this->assertCount(count(Placeholder::cases()), $offered);
    }

    /**
     * SPEC §3 snapshots the texts onto the version so a quote someone has read
     * or signed cannot change wording later. A placeholder that resolved at
     * render time would be a hole straight through that.
     */
    public function test_a_saved_version_stores_the_name_rather_than_the_placeholder()
    {
        PremadeText::query()->updateOrCreate(
            ['key' => PremadeTextKey::Intro],
            ['content' => '<p>Beste [[[customer_first_name]]],</p>'],
        );

        $customer = $this->customer();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), ['customer_id' => $customer->id, 'line_items' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '<p>Beste Daan,</p>',
            QuoteVersion::sole()->intro_text_snapshot,
        );
    }

    public function test_renaming_the_customer_afterwards_leaves_the_quote_alone()
    {
        PremadeText::query()->updateOrCreate(
            ['key' => PremadeTextKey::Intro],
            ['content' => '<p>Beste [[[customer_first_name]]],</p>'],
        );

        $customer = $this->customer();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), ['customer_id' => $customer->id, 'line_items' => []]);

        $customer->update(['first_name' => 'Daniel']);

        $this->assertSame('<p>Beste Daan,</p>', QuoteVersion::sole()->intro_text_snapshot);
    }

    /**
     * Saving a quote can have just moved it to a different customer, and the
     * snapshot has to greet the one it is going to now.
     */
    public function test_moving_a_quote_to_another_customer_regreets_it()
    {
        PremadeText::query()->updateOrCreate(
            ['key' => PremadeTextKey::Intro],
            ['content' => '<p>Beste [[[customer_first_name]]],</p>'],
        );

        $customer = $this->customer();
        $other = $this->customer(['first_name' => 'Piet', 'company_name' => 'Andere BV']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('quotes.store'), ['customer_id' => $customer->id, 'line_items' => []]);

        $quote = Quote::sole();

        $this->actingAs($user)->put(route('quotes.update', $quote), [
            'customer_id' => $other->id,
            'line_items' => [],
        ]);

        $this->assertSame('<p>Beste Piet,</p>', QuoteVersion::sole()->intro_text_snapshot);
    }

    /**
     * The hazard the load() in SaveQuoteVersion exists for.
     *
     * Reaching for $quote->customer lazily happens to be correct today, only
     * because nothing loads that relation before saving. The moment something
     * does - a controller wanting the company name for a flash message, say -
     * a lazily reached relation would be the customer the quote used to belong
     * to, and the snapshot would greet the wrong person with no sign anything
     * was wrong. Driven through the action directly, since no screen can
     * currently produce the stale state.
     */
    public function test_a_stale_customer_relation_does_not_reach_the_snapshot()
    {
        PremadeText::query()->updateOrCreate(
            ['key' => PremadeTextKey::Intro],
            ['content' => '<p>Beste [[[customer_first_name]]],</p>'],
        );

        $customer = $this->customer();
        $other = $this->customer(['first_name' => 'Piet', 'company_name' => 'Andere BV']);

        $quote = Quote::factory()->for($customer)->create();
        $quote->load('customer');
        $quote->update(['customer_id' => $other->id]);

        app(SaveQuoteVersion::class)->handle(
            $quote,
            new QuoteVersion(['quote_id' => $quote->id, 'version_number' => 1]),
            ['line_items' => []],
        );

        $this->assertSame('<p>Beste Piet,</p>', QuoteVersion::sole()->intro_text_snapshot);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create([
            'company_name' => 'Daan Test BV',
            'salutation' => Salutation::Heer,
            'first_name' => 'Daan',
            'last_name' => 'Daansen',
            ...$attributes,
        ]);
    }
}
