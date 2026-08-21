<?php

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The row is created by the migration rather than on first read, so no two
     * requests can race to create it.
     */
    public function test_the_single_row_exists_from_the_start()
    {
        $this->assertDatabaseCount('app_settings', 1);
        $this->assertNull(AppSettings::current()->logo_vector_url);
    }

    public function test_the_screen_opens_before_anything_has_been_filled_in()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app-settings/Edit')
                ->where('settings.logo_vector_url', null)
                ->where('settings.logo_raster_url', null)
                ->where('settings.logo_vector_preview_url', null)
                ->where('settings.logo_raster_preview_url', null)
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
     * they are separate forms on one screen, and fetching one can fail for
     * reasons the other has nothing to do with.
     */
    public function test_saving_the_details_leaves_the_logo_alone()
    {
        AppSettings::current()->update([
            'logo_raster_url' => 'https://xolution.test/logo.png',
            'logo_raster_mime' => 'image/png',
            'logo_raster_data' => base64_encode('bytes'),
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('app-settings.update'), ['company_name' => 'Xolution']);

        $settings = AppSettings::current();

        $this->assertSame('Xolution', $settings->company_name);
        $this->assertSame('https://xolution.test/logo.png', $settings->logo_raster_url);
        $this->assertTrue($settings->hasWebLogo());
    }

    public function test_a_guest_cannot_reach_any_of_it()
    {
        $this->get(route('app-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('app-settings.update'))->assertRedirect(route('login'));
        $this->put(route('app-settings.logo.store'))->assertRedirect(route('login'));
    }
}
