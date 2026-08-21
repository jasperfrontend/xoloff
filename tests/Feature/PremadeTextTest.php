<?php

namespace Tests\Feature;

use App\Enums\Placeholder;
use App\Enums\PremadeTextKey;
use App\Models\Customer;
use App\Models\PremadeText;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PremadeTextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Built from the enum that resolves them, so the list the editor offers
     * and the list that actually fills in cannot drift apart.
     */
    public function test_the_editor_is_told_which_placeholders_exist()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('premade-texts.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('placeholders', count(Placeholder::cases()))
                ->where('placeholders.0.token', '[[[customer_salutation]]]')
                ->where('placeholders.0.label', 'Salutation')
                ->where('placeholders.0.example', 'heer'),
            );
    }

    public function test_the_editor_shows_both_texts()
    {
        PremadeText::factory()->intro()->create(['content' => '<p>Beste klant</p>']);
        PremadeText::factory()->footer()->create(['content' => '<p>Algemene voorwaarden</p>']);

        $this->actingAs(User::factory()->create())
            ->get(route('premade-texts.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('premade-texts/Edit')
                ->where('texts.intro', '<p>Beste klant</p>')
                ->where('texts.footer', '<p>Algemene voorwaarden</p>'),
            );
    }

    /**
     * The rows are seeded, but a database that has not been seeded must still
     * open the editor rather than fail on a missing row.
     */
    public function test_the_editor_opens_before_either_text_exists()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('premade-texts.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('texts.intro', '')
                ->where('texts.footer', ''),
            );
    }

    public function test_both_texts_are_saved_together()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('premade-texts.update'), [
                'intro' => '<p>Hierbij onze offerte.</p>',
                'footer' => '<p>Algemene voorwaarden van toepassing.</p>',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('premade-texts.edit'));

        $this->assertDatabaseHas('premade_texts', [
            'key' => PremadeTextKey::Intro->value,
            'content' => '<p>Hierbij onze offerte.</p>',
        ]);

        $this->assertDatabaseHas('premade_texts', [
            'key' => PremadeTextKey::Footer->value,
            'content' => '<p>Algemene voorwaarden van toepassing.</p>',
        ]);
    }

    public function test_saving_twice_updates_rather_than_duplicating()
    {
        $user = User::factory()->create();

        foreach (['<p>Eerst</p>', '<p>Daarna</p>'] as $footer) {
            $this->actingAs($user)
                ->put(route('premade-texts.update'), ['intro' => '', 'footer' => $footer])
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('premade_texts', 2);
        $this->assertSame('<p>Daarna</p>', PremadeText::contentFor(PremadeTextKey::Footer));
    }

    /**
     * An empty intro is a legitimate choice: some quotes open straight into the
     * pricing.
     */
    public function test_the_intro_may_be_left_empty()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('premade-texts.update'), ['intro' => '', 'footer' => '<p>Voorwaarden</p>'])
            ->assertSessionHasNoErrors();

        $this->assertSame('', PremadeText::contentFor(PremadeTextKey::Intro));
    }

    /**
     * The footer is where the mandatory legal disclaimer lives, which is a legal
     * requirement rather than optional copy (SPEC §3).
     */
    public function test_the_footer_may_not_be_left_empty()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('premade-texts.update'), ['intro' => '<p>Hoi</p>', 'footer' => ''])
            ->assertSessionHasErrors('footer');

        $this->assertDatabaseCount('premade_texts', 0);
    }

    /**
     * Clearing the editor leaves one empty paragraph behind. Storing that would
     * mean an apparently filled-in footer that prints nothing on the PDF.
     */
    public function test_an_emptied_editor_does_not_pass_for_a_footer()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('premade-texts.update'), ['intro' => '', 'footer' => '<p></p>'])
            ->assertSessionHasErrors('footer');
    }

    public function test_pasted_markup_is_cleaned_before_it_is_stored()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('premade-texts.update'), [
                'intro' => '<div style="font-family: Calibri">Van elders<script>alert(1)</script></div>',
                'footer' => '<p>Voorwaarden</p>',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Van elders', PremadeText::contentFor(PremadeTextKey::Intro));
    }

    public function test_a_guest_cannot_read_or_change_the_texts()
    {
        $this->get(route('premade-texts.edit'))->assertRedirect(route('login'));
        $this->put(route('premade-texts.update'), ['footer' => '<p>x</p>'])->assertRedirect(route('login'));
    }

    /**
     * SPEC §3: snapshotted, not live-referenced, so a quote a customer already
     * viewed or signed stays accurate even if the global footer text is edited
     * later.
     */
    public function test_saving_a_quote_copies_both_texts_onto_the_version()
    {
        PremadeText::factory()->intro()->create(['content' => '<p>Intro van toen</p>']);
        PremadeText::factory()->footer()->create(['content' => '<p>Footer van toen</p>']);

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), $this->quoteContent())
            ->assertRedirect();

        $version = Quote::sole()->currentVersion;

        $this->assertSame('<p>Intro van toen</p>', $version->intro_text_snapshot);
        $this->assertSame('<p>Footer van toen</p>', $version->footer_text_snapshot);
    }

    public function test_editing_the_texts_leaves_a_superseded_version_untouched()
    {
        PremadeText::factory()->intro()->create(['content' => '<p>Oude intro</p>']);
        PremadeText::factory()->footer()->create(['content' => '<p>Oude footer</p>']);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();

        $quote = Quote::sole();

        // The wording changes, and then the quote is superseded rather than
        // saved over, which is what freezes version 1 for good.
        $this->actingAs($user)->put(route('premade-texts.update'), [
            'intro' => '<p>Nieuwe intro</p>',
            'footer' => '<p>Nieuwe footer</p>',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('quotes.versions.store', $quote), $this->quoteContent())
            ->assertRedirect();

        $first = $quote->versions()->where('version_number', 1)->sole();
        $second = $quote->versions()->where('version_number', 2)->sole();

        $this->assertSame('<p>Oude intro</p>', $first->intro_text_snapshot);
        $this->assertSame('<p>Oude footer</p>', $first->footer_text_snapshot);
        $this->assertSame('<p>Nieuwe intro</p>', $second->intro_text_snapshot);
        $this->assertSame('<p>Nieuwe footer</p>', $second->footer_text_snapshot);
    }

    /**
     * A version that is still being drafted is saved over rather than
     * superseded, and "at save time" means exactly that: the draft picks up the
     * current wording. Only superseded versions are frozen.
     */
    public function test_saving_over_the_current_version_takes_a_fresh_snapshot()
    {
        PremadeText::factory()->footer()->create(['content' => '<p>Oude footer</p>']);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();

        $quote = Quote::sole();

        $this->actingAs($user)->put(route('premade-texts.update'), [
            'intro' => '',
            'footer' => '<p>Nieuwe footer</p>',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(route('quotes.update', $quote), $this->quoteContent())
            ->assertRedirect();

        $this->assertSame(1, $quote->versions()->count());
        $this->assertSame('<p>Nieuwe footer</p>', QuoteVersion::sole()->footer_text_snapshot);
    }

    /**
     * A database with no texts at all must not block saving a quote.
     */
    public function test_a_quote_saves_when_no_texts_have_been_written_yet()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), $this->quoteContent())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('', Quote::sole()->currentVersion->footer_text_snapshot);
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
