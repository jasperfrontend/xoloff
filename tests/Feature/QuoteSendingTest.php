<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\QuoteStatus;
use App\Mail\QuoteSent;
use App\Models\AppSettings;
use App\Models\AuditLogEntry;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Sending a quote and the validity window it goes out with (SPEC §7).
 */
class QuoteSendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_sending_issues_a_link_and_marks_the_quote_sent()
    {
        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('quotes.edit', $quote));

        $quote->refresh();

        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertNotNull($quote->magic_link_token);
        $this->assertNotNull($quote->sent_at);
    }

    /**
     * The token is the customer's whole credential for this quote - no second
     * factor, no account behind it - so it is sized like a password rather
     * than like an identifier.
     */
    public function test_the_link_is_not_guessable()
    {
        $first = $this->quote();
        $second = $this->quote();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.send', $first));
        $this->actingAs($user)->post(route('quotes.send', $second));

        $this->assertSame(64, strlen((string) $first->fresh()->magic_link_token));
        $this->assertNotSame(
            $first->fresh()->magic_link_token,
            $second->fresh()->magic_link_token,
        );
    }

    public function test_the_window_follows_the_application_default()
    {
        AppSettings::current()->update(['default_validity_days' => 45]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        $this->assertSame(
            now()->addDays(45)->toDateString(),
            $quote->fresh()->valid_until?->toDateString(),
        );
    }

    public function test_a_quote_can_be_given_more_leeway_than_the_default()
    {
        AppSettings::current()->update(['default_validity_days' => 30]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote), ['validity_days' => 60]);

        $quote->refresh();

        $this->assertSame(60, $quote->validity_days_override);
        $this->assertSame(now()->addDays(60)->toDateString(), $quote->valid_until?->toDateString());
    }

    /**
     * Null means "follow the default", so a window that matches it is stored
     * as no override at all. Writing 30 where the default is 30 would quietly
     * detach the quote from a later change to that default.
     */
    public function test_a_window_matching_the_default_is_not_stored_as_an_override()
    {
        AppSettings::current()->update(['default_validity_days' => 30]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote), ['validity_days' => 30]);

        $this->assertNull($quote->fresh()->validity_days_override);
    }

    public function test_an_absurd_window_is_refused()
    {
        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote), ['validity_days' => 4000])
            ->assertSessionHasErrors('validity_days');

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    /**
     * Both links lead to the same place, so rotating the token would only
     * break the one already in the customer's inbox.
     */
    public function test_sending_again_keeps_the_link_and_moves_the_expiry()
    {
        $quote = $this->quote();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.send', $quote), ['validity_days' => 10]);

        $token = $quote->fresh()->magic_link_token;

        $this->actingAs($user)->post(route('quotes.send', $quote), ['validity_days' => 60]);

        $quote->refresh();

        $this->assertSame($token, $quote->magic_link_token);
        $this->assertSame(now()->addDays(60)->toDateString(), $quote->valid_until?->toDateString());
    }

    /**
     * A re-send is a new offer to look at, and whether the customer has opened
     * this one is exactly what the status is for.
     */
    public function test_sending_again_puts_an_opened_quote_back_to_sent()
    {
        $quote = $this->quote();
        $quote->forceFill(['status' => QuoteStatus::Opened])->save();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }

    public function test_a_quote_with_no_saved_version_cannot_be_sent()
    {
        $quote = Quote::factory()->for(Customer::factory())->create();

        $this->actingAs(User::factory()->create())
            ->from(route('quotes.edit', $quote))
            ->post(route('quotes.send', $quote))
            ->assertSessionHasErrors('send');

        $quote->refresh();

        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertNull($quote->magic_link_token);
    }

    public function test_a_guest_cannot_send_anything()
    {
        $quote = $this->quote();

        $this->post(route('quotes.send', $quote))->assertRedirect(route('login'));

        $this->assertNull($quote->fresh()->magic_link_token);
    }

    /**
     * SPEC §3 makes a status change its own action rather than an "updated"
     * entry naming a column, and one send is one event however many columns
     * it writes.
     */
    public function test_a_send_is_recorded_once_as_a_status_change()
    {
        $quote = $this->quote();

        AuditLogEntry::query()->delete();

        $this->actingAs($user = User::factory()->create())
            ->post(route('quotes.send', $quote), ['validity_days' => 14]);

        // Exactly two: the status moving, and the message going out. Not a
        // third naming the columns sending wrote.
        $this->assertDatabaseCount('audit_log', 2);

        $entry = AuditLogEntry::query()->where('action', AuditAction::StatusChanged)->sole();

        $this->assertSame('quote', $entry->entity_type);
        $this->assertSame($quote->id, $entry->payload['quote_id']);
        $this->assertSame($user->id, $entry->user_id);
        // Compared key by key: jsonb does not preserve the order they were
        // written in.
        $this->assertSame('draft', $entry->payload['changes']['status']['from']);
        $this->assertSame('sent', $entry->payload['changes']['status']['to']);
        $this->assertSame(14, $entry->payload['attributes']['validity_days']);
    }

    /**
     * The audit log is browsable by both users. A credential that reaches the
     * customer's inbox has no business being readable there as well.
     */
    public function test_the_link_never_reaches_the_audit_log()
    {
        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        $token = (string) $quote->fresh()->magic_link_token;

        foreach (AuditLogEntry::all() as $entry) {
            $this->assertStringNotContainsString($token, json_encode($entry->payload) ?: '');
        }
    }

    public function test_the_edit_screen_shows_the_link_once_the_quote_is_sent()
    {
        $quote = $this->quote();

        $this->actingAs($user = User::factory()->create())
            ->get(route('quotes.edit', $quote))
            ->assertInertia(fn ($page) => $page->where('quote.magic_link', null));

        $this->actingAs($user)->post(route('quotes.send', $quote));

        $this->actingAs($user)
            ->get(route('quotes.edit', $quote))
            ->assertInertia(fn ($page) => $page
                ->where('quote.magic_link', route('portal.quote', $quote->fresh()->magic_link_token))
                ->where('quote.valid_until', $quote->fresh()->valid_until?->toDateString()),
            );
    }

    public function test_the_edit_screen_offers_the_window_the_quote_would_go_out_with()
    {
        AppSettings::current()->update(['default_validity_days' => 21]);

        $quote = $this->quote();

        $this->actingAs($user = User::factory()->create())
            ->get(route('quotes.edit', $quote))
            ->assertInertia(fn ($page) => $page
                ->where('quote.validity_days', 21)
                ->where('quote.follows_the_default', true),
            );

        $this->actingAs($user)->post(route('quotes.send', $quote), ['validity_days' => 90]);

        $this->actingAs($user)
            ->get(route('quotes.edit', $quote))
            ->assertInertia(fn ($page) => $page
                ->where('quote.validity_days', 90)
                ->where('quote.follows_the_default', false),
            );
    }

    public function test_the_customer_is_emailed_the_link()
    {
        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        Mail::assertSent(QuoteSent::class, fn (QuoteSent $mail): bool => $mail->hasTo($quote->customer->email)
            && $mail->quote->is($quote));
    }

    public function test_the_message_carries_the_link_and_the_expiry_in_dutch()
    {
        AppSettings::current()->update(['company_name' => 'Xolution']);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote), ['validity_days' => 30]);

        $quote->refresh();

        Mail::assertSent(QuoteSent::class, function (QuoteSent $mail) use ($quote): bool {
            $rendered = $mail->render();

            return str_contains($rendered, route('portal.quote', $quote->magic_link_token))
                && str_contains($rendered, $quote->valid_until->format('d-m-Y'))
                && str_contains($rendered, 'Bekijk de offerte')
                // Escaped, because this half is HTML and the name has an
                // apostrophe in it. The text half must not be - see below.
                && str_contains($rendered, e($quote->customer->contact_person));
        });
    }

    /**
     * The plain-text alternative is text/plain, where escaping has nothing to
     * escape for and everything to spoil: "Anna O'Brien" arriving as "Anna
     * O&#039;Brien" is what a customer would actually read.
     *
     * Rendered through the mailable's own content definition rather than by
     * naming the view here, so renaming or dropping the text half fails this
     * rather than quietly passing.
     */
    public function test_the_plain_text_half_is_not_html_escaped()
    {
        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        Mail::assertSent(QuoteSent::class, function (QuoteSent $mail): bool {
            $content = $mail->content();

            $text = view((string) $content->text, [
                ...$content->with,
                'quote' => $mail->quote,
            ])->render();

            return str_contains($text, "Anna O'Brien")
                && ! str_contains($text, '&#039;');
        });
    }

    /**
     * Email clients mostly will not draw an SVG, so the message links the
     * raster. A linked image rather than embedded bytes: a data uri is
     * stripped by most clients and an attachment shows as a paperclip.
     */
    public function test_the_message_links_the_raster_logo()
    {
        AppSettings::current()->update([
            'logo_raster_url' => 'https://xolution.test/logo.png',
            'logo_raster_mime' => 'image/png',
            'logo_raster_data' => base64_encode('bytes'),
            'company_name' => 'Xolution',
        ]);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        Mail::assertSent(QuoteSent::class, function (QuoteSent $mail): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'src="'.route('logo.email').'"')
                // The name survives as alt text, for the many people who read
                // mail with images turned off.
                && str_contains($rendered, 'alt="Xolution"');
        });
    }

    /**
     * A vector alone leaves the message with no image rather than a broken
     * one, and a broken image is worse: it leaves a placeholder box where a
     * missing one leaves nothing.
     */
    public function test_the_message_falls_back_to_the_name_with_no_raster()
    {
        AppSettings::current()->update([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_vector_mime' => 'image/svg+xml',
            'logo_vector_data' => base64_encode('bytes'),
            'company_name' => 'Xolution',
        ]);

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $this->quote()));

        Mail::assertSent(QuoteSent::class, function (QuoteSent $mail): bool {
            $rendered = $mail->render();

            return ! str_contains($rendered, '<img')
                && str_contains($rendered, 'Xolution');
        });
    }

    /**
     * The inbox should show the company, not the tool that produced this.
     */
    public function test_the_message_comes_from_the_company_rather_than_the_app()
    {
        AppSettings::current()->update(['company_name' => 'Xolution']);

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        Mail::assertSent(QuoteSent::class, function (QuoteSent $mail): bool {
            $envelope = $mail->envelope();

            return $envelope->from?->name === 'Xolution'
                && str_contains($envelope->subject, 'van Xolution');
        });
    }

    /**
     * SPEC §7 tracks reading by portal visit. A customer holding the document
     * has no reason to follow the link, which would leave every quote looking
     * unread.
     */
    public function test_the_pdf_is_not_attached()
    {
        $quote = $this->quote();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        Mail::assertSent(QuoteSent::class, fn (QuoteSent $mail): bool => $mail->attachments() === []);
    }

    public function test_every_send_is_logged_whether_or_not_it_arrived()
    {
        $quote = $this->quote();

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())->post(route('quotes.send', $quote));

        $entry = AuditLogEntry::query()->where('action', AuditAction::NotificationSent)->sole();

        $this->assertSame('email', $entry->payload['attributes']['channel']);
        $this->assertSame($quote->customer->email, $entry->payload['attributes']['recipient']);
        $this->assertTrue($entry->payload['attributes']['delivered']);
    }

    /**
     * The link is live and the status has moved, so the quote really was sent.
     * Rolling that back would leave a quote reading draft with a working link
     * behind it.
     */
    public function test_a_refused_message_does_not_undo_the_send()
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('relay refused'));

        $quote = $this->quote();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote))
            ->assertRedirect(route('quotes.edit', $quote));

        $quote->refresh();

        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertNotNull($quote->magic_link_token);
    }

    /**
     * A success message here would leave someone waiting for a reply to
     * something the customer never received.
     */
    public function test_a_refused_message_says_so_and_is_logged_as_undelivered()
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('relay refused'));

        $quote = $this->quote();

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.send', $quote))
            ->assertSessionHasErrors('send');

        $entry = AuditLogEntry::query()->where('action', AuditAction::NotificationSent)->sole();

        $this->assertFalse($entry->payload['attributes']['delivered']);
    }

    /**
     * The contact name is pinned rather than left to the factory, and pinned to
     * one with an apostrophe in it.
     *
     * A faker name is a coin toss over whether it contains a character HTML
     * escapes, and these assertions turn on exactly that: CI failed on an
     * O'-name that the local run had never produced. Choosing the awkward case
     * on purpose is both deterministic and the more useful thing to cover.
     */
    private function quote(): Quote
    {
        $quote = Quote::factory()
            ->for(Customer::factory()->state(['first_name' => 'Anna', 'last_name' => "O'Brien"]))
            ->create();

        QuoteVersion::factory()->for($quote)->create(['version_number' => 1]);

        return $quote->fresh();
    }
}
