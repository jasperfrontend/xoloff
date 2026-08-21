<?php

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * The row is created by the migration rather than on first read, so no two
     * requests can race to create it.
     */
    public function test_the_single_row_exists_from_the_start()
    {
        $this->assertDatabaseCount('app_settings', 1);
        $this->assertNull(AppSettings::current()->logo_path);
    }

    public function test_the_screen_opens_before_anything_has_been_filled_in()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app-settings/Edit')
                ->where('settings.logo_path', null)
                ->where('settings.logo_url', null)
                ->where('settings.company_name', null)
                ->where('settings.company_address', null)
                ->where('settings.company_kvk', null)
                ->where('settings.company_vat_number', null),
            );
    }

    public function test_xolutions_own_details_can_be_saved()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.update'), [
                'company_name' => 'Xolution',
                'company_address' => "Voorbeeldstraat 1\n1234 AB Amsterdam",
                'company_kvk' => '01234567',
                'company_vat_number' => 'NL001234567B01',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app-settings.edit'));

        $settings = AppSettings::current();

        $this->assertSame('Xolution', $settings->company_name);
        $this->assertSame("Voorbeeldstraat 1\n1234 AB Amsterdam", $settings->company_address);
        $this->assertSame('01234567', $settings->company_kvk);
        $this->assertSame('NL001234567B01', $settings->company_vat_number);
    }

    /**
     * The real values are still being collected (SPEC §12). Refusing to save
     * until all four arrive would mean none of them ever get saved.
     */
    public function test_the_details_can_be_saved_a_field_at_a_time()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('app-settings.update'), ['company_name' => 'Xolution'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Xolution', AppSettings::current()->company_name);
        $this->assertNull(AppSettings::current()->company_kvk);

        $this->actingAs($user)
            ->put(route('app-settings.update'), ['company_kvk' => '01234567'])
            ->assertSessionHasNoErrors();

        $this->assertSame('01234567', AppSettings::current()->company_kvk);
    }

    /**
     * A KvK number keeps its leading zero and a BTW number is not arithmetic,
     * so both are stored exactly as typed.
     */
    public function test_a_leading_zero_survives()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.update'), ['company_kvk' => '00123456']);

        $this->assertSame('00123456', AppSettings::current()->company_kvk);
    }

    public function test_an_overlong_detail_is_refused()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.update'), [
                'company_name' => str_repeat('a', 256),
                'company_kvk' => str_repeat('1', 51),
            ])
            ->assertSessionHasErrors(['company_name', 'company_kvk']);

        $this->assertNull(AppSettings::current()->company_name);
    }

    /**
     * Saving the details must not disturb a logo that is already in place -
     * they are separate forms on one screen.
     */
    public function test_saving_the_details_leaves_the_logo_alone()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('app-settings.logo.store'), [
            'logo' => UploadedFile::fake()->image('xolution.png'),
        ]);

        $path = AppSettings::current()->logo_path;

        $this->actingAs($user)->put(route('app-settings.update'), ['company_name' => 'Xolution']);

        $this->assertSame($path, AppSettings::current()->logo_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_logo_can_be_uploaded()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), [
                'logo' => UploadedFile::fake()->image('xolution.png', 600, 200),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app-settings.edit'));

        $path = AppSettings::current()->logo_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_screen_offers_a_url_the_browser_can_load()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), [
                'logo' => UploadedFile::fake()->image('xolution.png'),
            ]);

        $this->actingAs(User::factory()->create())
            ->get(route('app-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.logo_url', fn (?string $url): bool => is_string($url) && str_contains($url, 'logos/')),
            );
    }

    /**
     * A replaced logo leaves its file behind otherwise, and nothing ever points
     * at it again: the PDF embeds whatever the logo is when it is generated.
     */
    public function test_replacing_the_logo_removes_the_file_it_replaced()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('app-settings.logo.store'), [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $first = AppSettings::current()->logo_path;

        $this->actingAs($user)->post(route('app-settings.logo.store'), [
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        $second = AppSettings::current()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_the_logo_can_be_removed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('app-settings.logo.store'), [
            'logo' => UploadedFile::fake()->image('xolution.png'),
        ]);

        $path = AppSettings::current()->logo_path;

        $this->actingAs($user)
            ->delete(route('app-settings.logo.destroy'))
            ->assertRedirect(route('app-settings.edit'));

        $this->assertNull(AppSettings::current()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_removing_a_logo_that_is_not_there_is_harmless()
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('app-settings.logo.destroy'))
            ->assertRedirect(route('app-settings.edit'));

        $this->assertNull(AppSettings::current()->logo_path);
    }

    public function test_submitting_with_no_file_says_so_rather_than_appearing_to_save()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), [])
            ->assertSessionHasErrors('logo');
    }

    /**
     * An SVG is a document that can carry script, and this file is handed to a
     * real Chromium while the PDF is rendered.
     */
    public function test_an_svg_is_refused()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), [
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull(AppSettings::current()->logo_path);
    }

    public function test_a_file_that_is_not_an_image_is_refused()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), [
                'logo' => UploadedFile::fake()->create('voorwaarden.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_an_oversized_image_is_refused()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), [
                'logo' => UploadedFile::fake()->image('huge.png')->size(3000),
            ])
            ->assertSessionHasErrors('logo');
    }

    /**
     * PHP can fail an upload before any rule of ours runs: the file is too big
     * for its limits, or it has nowhere writable to put the temporary file.
     * Laravel's default wording for that is "The logo failed to upload", which
     * names neither cause and sends whoever hit it looking in the wrong place.
     */
    public function test_an_upload_php_itself_refused_says_what_might_be_wrong()
    {
        $file = UploadedFile::fake()->image('xolution.png');

        $refused = new UploadedFile(
            $file->getPathname(),
            'xolution.png',
            'image/png',
            UPLOAD_ERR_CANT_WRITE,
            test: true,
        );

        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.logo.store'), ['logo' => $refused])
            ->assertSessionHasErrors([
                'logo' => 'The logo could not be uploaded. Either it is larger than the server accepts, or the server had nowhere to store it while it arrived.',
            ]);

        $this->assertNull(AppSettings::current()->logo_path);
    }

    public function test_a_guest_cannot_reach_any_of_it()
    {
        $this->get(route('app-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('app-settings.update'))->assertRedirect(route('login'));
        $this->post(route('app-settings.logo.store'))->assertRedirect(route('login'));
        $this->delete(route('app-settings.logo.destroy'))->assertRedirect(route('login'));
    }
}
