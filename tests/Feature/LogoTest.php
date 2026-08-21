<?php

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\AuditLogEntry;
use App\Models\User;
use App\Support\Logo\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Two logos, fetched from addresses Xolution already hosts, kept in the
 * database rather than on disk.
 *
 * Two because no single file works everywhere. An SVG is what the portal and
 * the PDF want - sharp at any size, and a quarter the file - but email clients
 * mostly refuse to render one: Gmail strips it and Outlook will not draw it.
 * Either alone is enough; both is better.
 *
 * Not what SPEC §6 describes, which says "logo upload UI ... stored via
 * app_settings.logo_path". Moving off disk was a deployment decision: an
 * uploaded file makes the filesystem a second stateful thing, needing a mount
 * that exists before the container starts and backups covering two places.
 */
class LogoTest extends TestCase
{
    use RefreshDatabase;

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 30"></svg>';

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

    public function test_each_field_keeps_its_own_kind()
    {
        $this->fakeBothKinds();

        $this->save([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_raster_url' => 'https://xolution.test/logo.png',
        ])->assertSessionHasNoErrors();

        $settings = AppSettings::current();

        $this->assertSame('image/svg+xml', $settings->logo_vector_mime);
        $this->assertSame('image/png', $settings->logo_raster_mime);
        $this->assertSame(self::SVG, $settings->webLogo()?->bytes);
    }

    /**
     * The whole reason there are two. Falling back to the vector here would
     * mean sending a broken image rather than none, and a broken image is
     * worse: it leaves a placeholder box where a missing one leaves nothing.
     */
    public function test_email_gets_no_logo_when_only_a_vector_was_given()
    {
        $this->fakeBothKinds();

        $this->save(['logo_vector_url' => 'https://xolution.test/logo.svg']);

        $settings = AppSettings::current();

        $this->assertNotNull($settings->webLogo());
        $this->assertNull($settings->emailLogo());

        $this->get(route('logo.show'))->assertOk();
        $this->get(route('logo.email'))->assertNotFound();
    }

    /**
     * Someone with only a PNG should not have to find an SVG to use this.
     */
    public function test_a_raster_alone_serves_every_purpose()
    {
        $this->fakeBothKinds();

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png']);

        $settings = AppSettings::current();

        $this->assertSame('image/png', $settings->webLogo()?->mime);
        $this->assertSame('image/png', $settings->emailLogo()?->mime);

        $this->get(route('logo.show'))->assertOk();
        $this->get(route('logo.email'))->assertOk();
    }

    public function test_the_vector_is_preferred_for_screens_and_print()
    {
        $this->fakeBothKinds();

        $this->save([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_raster_url' => 'https://xolution.test/logo.png',
        ]);

        $this->assertSame('image/svg+xml', AppSettings::current()->webLogo()?->mime);
    }

    /**
     * A perfectly good image in the wrong box is a mistake worth naming
     * precisely, rather than answering "not an image".
     */
    public function test_a_raster_in_the_vector_field_is_refused()
    {
        $this->fakeBothKinds();

        $this->save(['logo_vector_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasErrors(['logo_vector_url' => 'That address returned image/png, and this field takes an SVG.']);
    }

    public function test_a_vector_in_the_raster_field_is_refused()
    {
        $this->fakeBothKinds();

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.svg'])
            ->assertSessionHasErrors(['logo_raster_url' => 'That address returned image/svg+xml, and this field takes a PNG, JPG or WebP.']);
    }

    /**
     * Clearing an address is how a logo is removed, so there is no separate
     * delete that could fall out of step with the save.
     */
    public function test_clearing_an_address_removes_that_logo()
    {
        $this->fakeBothKinds();

        $this->save([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_raster_url' => 'https://xolution.test/logo.png',
        ]);

        $this->save([
            'logo_vector_url' => '',
            'logo_raster_url' => 'https://xolution.test/logo.png',
        ])->assertSessionHasNoErrors();

        $settings = AppSettings::current();

        $this->assertNull($settings->logo_vector_mime);
        $this->assertSame('image/png', $settings->logo_raster_mime);
    }

    /**
     * Saving one field must not refetch the other, or a logo could be lost to
     * a web server that happened to be down while an unrelated edit was made.
     */
    public function test_an_unchanged_address_is_not_fetched_again()
    {
        $this->fakeBothKinds();

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png']);
        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasNoErrors();

        // Counted rather than faked into failing on the second call: a second
        // Http::fake appends its stub rather than replacing the first, so the
        // original would keep answering and this would pass either way.
        Http::assertSentCount(1);

        $this->assertSame('image/png', AppSettings::current()->logo_raster_mime);
    }

    /**
     * Saved anyway. A logo smaller than ideal is still a logo, and refusing it
     * would be this application having an opinion about someone's artwork.
     */
    public function test_a_narrow_raster_warns_but_is_still_kept()
    {
        Http::fake(['xolution.test/*' => Http::response(
            $this->png(200, 60),
            200,
            ['Content-Type' => 'image/png'],
        )]);

        $this->save(['logo_raster_url' => 'https://xolution.test/small.png'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(AppSettings::current()->hasEmailLogo());
    }

    public function test_the_stored_logos_are_served_to_a_browser()
    {
        $this->fakeBothKinds();

        $this->save([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_raster_url' => 'https://xolution.test/logo.png',
        ]);

        $web = $this->get(route('logo.show'))->assertOk();
        $web->assertHeader('content-type', 'image/svg+xml');
        $this->assertSame(self::SVG, $web->getContent());

        $this->get(route('logo.email'))->assertOk()->assertHeader('content-type', 'image/png');
    }

    /**
     * The customer's portal shows one and a quote email links the other, and
     * both are images Xolution already publishes on their own website.
     */
    public function test_the_logos_need_no_sign_in()
    {
        $this->fakeBothKinds();

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png']);

        $this->get(route('logo.show'))->assertOk();
        $this->get(route('logo.email'))->assertOk();
    }

    public function test_there_is_nothing_to_serve_before_a_logo_is_saved()
    {
        $this->get(route('logo.show'))->assertNotFound();
        $this->get(route('logo.email'))->assertNotFound();
    }

    /**
     * A wrong address has to be a message in front of the person who typed it,
     * not a logo missing from a document a client already has.
     */
    public function test_an_address_that_is_not_there_says_so_and_keeps_the_old_logo()
    {
        Http::fake(['xolution.test/*' => Http::sequence()
            ->push($this->png(600, 180), 200, ['Content-Type' => 'image/png'])
            ->push('nope', 404)]);

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasNoErrors();

        $this->save(['logo_raster_url' => 'https://xolution.test/gone.png'])
            ->assertSessionHasErrors('logo_raster_url');

        $this->assertSame('https://xolution.test/logo.png', AppSettings::current()->logo_raster_url);
    }

    public function test_something_that_is_not_an_image_at_all_is_refused()
    {
        Http::fake(['xolution.test/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->save(['logo_raster_url' => 'https://xolution.test/oops.html'])
            ->assertSessionHasErrors('logo_raster_url');

        $this->assertFalse(AppSettings::current()->hasEmailLogo());
    }

    public function test_an_enormous_image_is_refused()
    {
        Http::fake(['xolution.test/*' => Http::response(
            str_repeat('a', 3 * 1024 * 1024),
            200,
            ['Content-Type' => 'image/png'],
        )]);

        $this->save(['logo_raster_url' => 'https://xolution.test/huge.png'])
            ->assertSessionHasErrors('logo_raster_url');
    }

    public function test_plain_http_is_refused()
    {
        Http::fake();

        $this->save(['logo_raster_url' => 'http://xolution.test/logo.png'])
            ->assertSessionHasErrors('logo_raster_url');

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

        foreach (['https://169.254.169.254/meta', 'https://127.0.0.1/logo.png', 'https://10.0.0.5/logo.png'] as $url) {
            $this->save(['logo_raster_url' => $url])->assertSessionHasErrors('logo_raster_url');
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

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasErrors('logo_raster_url');

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

        $this->save(['logo_raster_url' => 'https://xolution.test/logo.png'])
            ->assertSessionHasErrors(['logo_raster_url' => 'That address redirects somewhere else. Paste the address it ends up at.']);

        $this->assertFalse(AppSettings::current()->hasEmailLogo());
    }

    /**
     * This model records its own changes, and a payload people browse is no
     * place for kilobytes of base64.
     */
    public function test_the_bytes_never_reach_the_audit_log()
    {
        $this->fakeBothKinds();

        $this->save([
            'logo_vector_url' => 'https://xolution.test/logo.svg',
            'logo_raster_url' => 'https://xolution.test/logo.png',
        ]);

        foreach (AuditLogEntry::all() as $entry) {
            $payload = (string) json_encode($entry->payload);

            $this->assertStringNotContainsString(base64_encode(self::SVG), $payload);
            $this->assertStringNotContainsString('logo_vector_data', $payload);
            $this->assertStringNotContainsString('logo_raster_data', $payload);
        }
    }

    public function test_a_guest_cannot_change_the_logos()
    {
        $this->put(route('app-settings.logo.store'))->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function save(array $fields): TestResponse
    {
        return $this->actingAs(User::factory()->create())
            ->from(route('app-settings.edit'))
            ->put(route('app-settings.logo.store'), $fields);
    }

    /**
     * Answers a .svg address with an SVG and anything else with a PNG, so one
     * fake covers both fields.
     */
    private function fakeBothKinds(): void
    {
        $png = $this->png(600, 180);

        Http::fake(fn ($request) => str_ends_with($request->url(), '.svg')
            ? Http::response(self::SVG, 200, ['Content-Type' => 'image/svg+xml'])
            : Http::response($png, 200, ['Content-Type' => 'image/png']));
    }

    /** A real PNG, so the width check has something true to measure. */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
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
}
