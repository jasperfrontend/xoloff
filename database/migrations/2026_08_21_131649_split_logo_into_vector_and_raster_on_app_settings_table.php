<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two logos rather than one, because no single file works everywhere.
        //
        // An SVG is what the portal and the PDF want - sharp at any size, and
        // a quarter the file - but email clients mostly refuse to render one:
        // Gmail strips it and Outlook will not draw it. So email needs a
        // raster, and the two are kept as separate fields rather than one
        // being derived from the other.
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('logo_vector_url', 2048)->nullable();
            $table->string('logo_vector_mime')->nullable();
            $table->text('logo_vector_data')->nullable();

            $table->string('logo_raster_url', 2048)->nullable();
            $table->string('logo_raster_mime')->nullable();
            $table->text('logo_raster_data')->nullable();
        });

        // Whatever is already stored moves to the side it belongs on, by what
        // it actually is rather than by which column it happened to sit in.
        $settings = DB::table('app_settings')->first(['id', 'logo_url', 'logo_mime', 'logo_data']);

        if ($settings !== null && $settings->logo_mime !== null) {
            $side = $settings->logo_mime === 'image/svg+xml' ? 'vector' : 'raster';

            DB::table('app_settings')->where('id', $settings->id)->update([
                "logo_{$side}_url" => $settings->logo_url,
                "logo_{$side}_mime" => $settings->logo_mime,
                "logo_{$side}_data" => $settings->logo_data,
            ]);
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'logo_mime', 'logo_data']);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('logo_url', 2048)->nullable();
            $table->string('logo_mime')->nullable();
            $table->text('logo_data')->nullable();
        });

        // The vector wins on the way back, since that is what the screens that
        // survive a rollback are built around.
        $settings = DB::table('app_settings')->first();

        if ($settings !== null) {
            $side = $settings->logo_vector_mime !== null ? 'vector' : 'raster';

            DB::table('app_settings')->where('id', $settings->id)->update([
                'logo_url' => $settings->{"logo_{$side}_url"},
                'logo_mime' => $settings->{"logo_{$side}_mime"},
                'logo_data' => $settings->{"logo_{$side}_data"},
            ]);
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_vector_url', 'logo_vector_mime', 'logo_vector_data',
                'logo_raster_url', 'logo_raster_mime', 'logo_raster_data',
            ]);
        });
    }
};
