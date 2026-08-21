<?php

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\AuditLogEntry;
use App\Models\User;
use App\Support\Logo\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The logo is fetched from an address Xolution already hosts it at, and its
 * bytes are kept in the database rather than on disk.
 *
 * Not what SPEC §6 describes, which says "logo upload UI ... stored via
 * app_settings.logo_path". The reason for the change is deployment: an
 * uploaded file makes the filesystem a second stateful thing, needing a mount
 * that exists before the container starts and backups that cover two places.
 * For one file of a few dozen kilobytes the database is the better home.
 */
class LogoTest extends TestCase
{
    use RefreshDatabase;

    /** A PNG's magic bytes, so what these tests move around is really an image. */
    private const PNG = "\x89PNG\r\n\x1a\n";

    /**
     * xolution.test does not resolve, and should not: a test that depends on
     * DNS is a test that fails on a train. The resolver is the seam that lets
     * the guard around it be exercised deterministically, including the case
     * DNS itself would never show - a public-looking name pointing somewhere
     * private.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resolveTestHostsTo('203.0.113.10');
    }

    private function resolveTestHostsTo(string $address): void
    {
        $this->swap(HostResolver::class, new class($address) extends HostResolver
        {
            public function __construct(private readonly string $address) {}

            public function resolve(string $host): array
            {
                return [$this->address];
            }
        });
    }

    public function test_a_logo_is_fetched_and_kept()
    {
        Http::fake(['xolution.test/*' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app-settings.edit'));

        $settings = AppSettings::current();

        $this->assertSame('https://xolution.test/logo.png', $settings->logo_url);
        $this->assertSame('image/png', $settings->logo_mime);
        $this->assertSame(self::PNG, $settings->logo()?->bytes);
    }

    /**
     * The whole point of storing the bytes: printing a quote must not depend
     * on someone else's web server still answering.
     */
    public function test_printing_a_quote_never_refetches_the_logo()
    {
        $this->storeALogo();

        Http::fake();

        $this->assertNotNull(AppSettings::current()->logo());

        Http::assertNothingSent();
    }

    /**
     * Gotenberg renders elsewhere and cannot necessarily reach this
     * application, and M6 hashes the rendered document at signing - a linked
     * image would leave that hash covering an address rather than what the
     * signer saw.
     */
    public function test_the_pdf_embeds_the_stored_bytes()
    {
        $this->storeALogo();

        $this->assertSame(
            'data:image/png;base64,'.base64_encode(self::PNG),
            AppSettings::current()->logo()?->toDataUri(),
        );
    }

    public function test_the_screen_shows_the_stored_copy_rather_than_the_remote_one()
    {
        $this->storeALogo();

        $this->actingAs(User::factory()->create())
            ->get(route('app-settings.edit'))
            ->assertInertia(fn ($page) => $page
                // The address, so the field keeps it; the preview, so what is
                // shown is what will actually print.
                ->where('settings.logo_url', 'https://xolution.test/logo.png')
                ->where('settings.logo_preview_url', route('logo.show')),
            );
    }

    public function test_the_stored_logo_is_served_to_a_browser()
    {
        $this->storeALogo();

        $response = $this->get(route('logo.show'))->assertOk();

        $response->assertHeader('content-type', 'image/png');
        $this->assertSame(self::PNG, $response->getContent());
    }

    /**
     * The customer's portal shows it, and it is the same image Xolution
     * already publishes on their own website.
     */
    public function test_the_logo_needs_no_sign_in()
    {
        $this->storeALogo();

        $this->get(route('logo.show'))->assertOk();
    }

    public function test_there_is_no_logo_to_serve_before_one_is_saved()
    {
        $this->get(route('logo.show'))->assertNotFound();
    }

    public function test_a_logo_can_be_removed()
    {
        $this->storeALogo();

        $this->actingAs(User::factory()->create())
            ->delete(route('app-settings.logo.destroy'))
            ->assertRedirect(route('app-settings.edit'));

        $settings = AppSettings::current();

        $this->assertNull($settings->logo_url);
        $this->assertNull($settings->logo_mime);
        $this->assertNull($settings->logo_data);
    }

    public function test_removing_a_logo_that_is_not_there_is_harmless()
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('app-settings.logo.destroy'))
            ->assertRedirect(route('app-settings.edit'));

        $this->assertFalse(AppSettings::current()->hasLogo());
    }

    /**
     * A wrong address has to be a message in front of the person who typed it,
     * not a logo missing from a document a client already has.
     */
    public function test_an_address_that_is_not_there_says_so_and_keeps_the_old_logo()
    {
        // A sequence rather than two Http::fake calls: the second call appends
        // its stub rather than replacing the first, so the original would keep
        // matching and this would quietly pass.
        Http::fake(['xolution.test/*' => Http::sequence()
            ->push(self::PNG, 200, ['Content-Type' => 'image/png'])
            ->push('nope', 404)]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/gone.png'])
            ->assertSessionHasErrors('logo_url');

        // The one that worked is still there rather than cleared by a failed
        // attempt to replace it.
        $this->assertSame('https://xolution.test/logo.png', AppSettings::current()->logo_url);
    }

    public function test_something_that_is_not_an_image_is_refused()
    {
        Http::fake(['xolution.test/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/oops.html'])
            ->assertSessionHasErrors('logo_url');

        $this->assertFalse(AppSettings::current()->hasLogo());
    }

    /**
     * SVG was refused as an upload because it is a document that can carry
     * script and would have been rendered by a Chromium. Fetched here it only
     * ever reaches an img element, where it is treated as an image and nothing
     * in it runs.
     */
    public function test_an_svg_is_accepted_now_that_it_is_only_ever_an_image()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        Http::fake(['xolution.test/*' => Http::response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=utf-8'])]);

        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/logo.svg'])
            ->assertSessionHasNoErrors();

        // The charset is not part of the type, and it would break a data uri.
        $this->assertSame('image/svg+xml', AppSettings::current()->logo_mime);
    }

    public function test_an_enormous_image_is_refused()
    {
        Http::fake(['xolution.test/*' => Http::response(
            str_repeat('a', 3 * 1024 * 1024),
            200,
            ['Content-Type' => 'image/png'],
        )]);

        $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/huge.png'])
            ->assertSessionHasErrors('logo_url');
    }

    public function test_plain_http_is_refused()
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), ['logo_url' => 'http://xolution.test/logo.png'])
            ->assertSessionHasErrors('logo_url');

        Http::assertNothingSent();
    }

    /**
     * This application fetches an address a person typed, which is the shape
     * of a server-side request forgery whether or not anyone means it that
     * way. The host it runs on can reach its own cloud metadata service.
     */
    public function test_an_address_inside_the_network_is_refused()
    {
        Http::fake();

        $user = User::factory()->create();

        foreach (['https://169.254.169.254/latest/meta-data/', 'https://127.0.0.1/logo.png', 'https://10.0.0.5/logo.png'] as $url) {
            $this->actingAs($user)
                ->from(route('app-settings.edit'))
                ->put(route('app-settings.logo.store'), ['logo_url' => $url])
                ->assertSessionHasErrors('logo_url');
        }

        Http::assertNothingSent();
    }

    /**
     * The case a literal address never shows: a name that looks perfectly
     * ordinary and resolves somewhere it has no business pointing.
     */
    public function test_a_public_looking_name_pointing_inside_the_network_is_refused()
    {
        Http::fake();

        $this->resolveTestHostsTo('127.0.0.1');

        $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasErrors('logo_url');

        Http::assertNothingSent();
    }

    /**
     * Whatever was validated a moment ago is not necessarily where a redirect
     * leads, and re-checking every hop is a lot of machinery for an address
     * someone can paste in its final form.
     */
    public function test_an_address_that_redirects_is_refused()
    {
        Http::fake(['xolution.test/*' => Http::response('', 302, ['Location' => 'https://elsewhere.test/logo.png'])]);

        $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/logo.png'])
            // The wording, not just the presence of an error. A redirect with
            // no content type is refused as "not an image" whether or not
            // anything looks at redirects, so only the message distinguishes
            // the check that is actually meant to catch this from an accident.
            ->assertSessionHasErrors(['logo_url' => 'That address redirects somewhere else. Paste the address it ends up at.']);

        $this->assertFalse(AppSettings::current()->hasLogo());
    }

    public function test_saving_with_no_address_says_so()
    {
        $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), [])
            ->assertSessionHasErrors('logo_url');
    }

    /**
     * This model records its own changes, and a payload people browse is no
     * place for fifty kilobytes of base64.
     */
    public function test_the_bytes_never_reach_the_audit_log()
    {
        $this->storeALogo();

        foreach (AuditLogEntry::all() as $entry) {
            $this->assertStringNotContainsString(
                base64_encode(self::PNG),
                (string) json_encode($entry->payload),
            );
        }
    }

    public function test_a_guest_cannot_change_the_logo()
    {
        $this->put(route('app-settings.logo.store'))->assertRedirect(route('login'));
        $this->delete(route('app-settings.logo.destroy'))->assertRedirect(route('login'));
    }

    private function storeALogo(): void
    {
        Http::fake(['xolution.test/*' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.logo.store'), ['logo_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasNoErrors();
    }
}
