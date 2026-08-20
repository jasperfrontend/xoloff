<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PremadeText;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Browsing, reading and removing the versions of a quote (SPEC §6). There is no
 * route that edits a past version, and that is the point of the feature.
 */
class QuoteVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_history_lists_every_version_newest_first()
    {
        $quote = $this->quoteWithVersions(3);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.index', $quote))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/versions/Index')
                ->has('versions', 3)
                ->where('versions.0.version_number', 3)
                ->where('versions.2.version_number', 1),
            );
    }

    public function test_the_history_marks_which_version_is_current()
    {
        $quote = $this->quoteWithVersions(2);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.index', $quote))
            ->assertInertia(fn (Assert $page) => $page
                ->where('versions.0.is_current', true)
                ->where('versions.1.is_current', false),
            );
    }

    public function test_the_history_totals_each_version_through_the_engine()
    {
        $quote = $this->quoteWithVersions(1);
        $taxClass = TaxClass::factory()->create(['percentage' => 21.00]);

        $quote->currentVersion->lineItems()->create([
            'name' => 'Managed hosting',
            'quantity' => 2,
            'unit_price_ex_vat' => 90.00,
            'tax_class_id' => $taxClass->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.index', $quote))
            ->assertInertia(fn (Assert $page) => $page
                ->where('versions.0.line_count', 1)
                ->where('versions.0.total', '217.80'),
            );
    }

    public function test_a_version_can_be_read()
    {
        $quote = $this->quoteWithVersions(2);
        $version = $quote->versions()->where('version_number', 1)->sole();

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.versions.show', [$quote, $version]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/versions/Show')
                ->where('version.version_number', 1)
                ->where('version.is_current', false)
                ->has('totals'),
            );
    }

    /**
     * The whole reason snapshots exist: reading an old version shows the
     * wording it was saved with, not whatever the texts say now.
     */
    public function test_a_version_shows_the_texts_it_was_saved_with()
    {
        PremadeText::factory()->intro()->create(['content' => '<p>Intro van toen</p>']);
        PremadeText::factory()->footer()->create(['content' => '<p>Footer van toen</p>']);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();

        $quote = Quote::sole();

        $this->actingAs($user)->put(route('premade-texts.update'), [
            'intro' => '<p>Intro van nu</p>',
            'footer' => '<p>Footer van nu</p>',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('quotes.versions.store', $quote), $this->quoteContent())
            ->assertRedirect();

        $first = $quote->versions()->where('version_number', 1)->sole();

        $this->actingAs($user)
            ->get(route('quotes.versions.show', [$quote, $first]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('version.intro_text_snapshot', '<p>Intro van toen</p>')
                ->where('version.footer_text_snapshot', '<p>Footer van toen</p>'),
            );
    }

    public function test_a_superseded_version_can_be_deleted()
    {
        $quote = $this->quoteWithVersions(2);
        $first = $quote->versions()->where('version_number', 1)->sole();

        $this->actingAs(User::factory()->create())
            ->delete(route('quotes.versions.destroy', [$quote, $first]))
            ->assertRedirect(route('quotes.versions.index', $quote));

        $this->assertDatabaseMissing('quote_versions', ['id' => $first->id]);
        $this->assertSame(2, $quote->fresh()->currentVersion->version_number);
    }

    /**
     * Deleting the current version would silently promote an older one into its
     * place, which reads as the quote changing by itself.
     */
    public function test_the_current_version_cannot_be_deleted()
    {
        $quote = $this->quoteWithVersions(2);
        $current = $quote->versions()->where('version_number', 2)->sole();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.versions.index', $quote))
            ->delete(route('quotes.versions.destroy', [$quote, $current]))
            ->assertSessionHasErrors('version');

        $this->assertDatabaseHas('quote_versions', ['id' => $current->id]);
    }

    public function test_the_only_version_of_a_quote_cannot_be_deleted()
    {
        $quote = $this->quoteWithVersions(1);
        $only = $quote->currentVersion;

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.versions.index', $quote))
            ->delete(route('quotes.versions.destroy', [$quote, $only]))
            ->assertSessionHasErrors('version');

        $this->assertDatabaseCount('quote_versions', 1);
    }

    public function test_deleting_a_version_takes_its_lines_with_it()
    {
        $quote = $this->quoteWithVersions(2);
        $first = $quote->versions()->where('version_number', 1)->sole();

        $first->lineItems()->create([
            'name' => 'Managed hosting',
            'quantity' => 1,
            'unit_price_ex_vat' => 90.00,
            'tax_class_id' => TaxClass::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->delete(route('quotes.versions.destroy', [$quote, $first]));

        $this->assertDatabaseMissing('quote_line_items', ['quote_version_id' => $first->id]);
    }

    /**
     * The quote in the url has to be the version's own, or the url becomes a
     * way to read and delete another quote's history.
     */
    public function test_a_version_belonging_to_another_quote_is_not_found()
    {
        $mine = $this->quoteWithVersions(1);
        $theirs = $this->quoteWithVersions(2);
        $theirVersion = $theirs->versions()->where('version_number', 1)->sole();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('quotes.versions.show', [$mine, $theirVersion]))
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('quotes.versions.destroy', [$mine, $theirVersion]))
            ->assertNotFound();

        $this->assertDatabaseHas('quote_versions', ['id' => $theirVersion->id]);
    }

    public function test_a_guest_cannot_read_or_delete_history()
    {
        $quote = $this->quoteWithVersions(2);
        $first = $quote->versions()->where('version_number', 1)->sole();

        $this->get(route('quotes.versions.index', $quote))->assertRedirect(route('login'));
        $this->get(route('quotes.versions.show', [$quote, $first]))->assertRedirect(route('login'));
        $this->delete(route('quotes.versions.destroy', [$quote, $first]))->assertRedirect(route('login'));
    }

    private function quoteWithVersions(int $count): Quote
    {
        $quote = Quote::factory()->for(Customer::factory())->create();

        foreach (range(1, $count) as $versionNumber) {
            QuoteVersion::factory()->for($quote)->create(['version_number' => $versionNumber]);
        }

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteContent(): array
    {
        return [
            'customer_id' => Customer::firstOr(fn () => Customer::factory()->create())->id,
            'line_items' => [
                [
                    'name' => 'Managed hosting',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 90.00,
                    'tax_class_id' => TaxClass::firstOr(fn () => TaxClass::factory()->create())->id,
                ],
            ],
        ];
    }
}
