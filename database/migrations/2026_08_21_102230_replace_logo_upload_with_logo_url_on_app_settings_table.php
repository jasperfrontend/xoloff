<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The logo is fetched from an address Xolution already hosts rather
        // than uploaded, and its bytes are kept here rather than on disk.
        //
        // That is a deployment decision as much as a product one. An uploaded
        // file makes the filesystem a second stateful thing: a mount that has
        // to exist before the container starts, a storage:link inside it, and
        // backups that have to cover two places. For one file of a few dozen
        // kilobytes, the database is the better home - it is already
        // persistent and already backed up.
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('logo_url', 2048)->nullable();

            // Fetched once when the address is saved, not each time a PDF is
            // rendered. A wrong address is then a message under the field
            // rather than a logo missing from a quote the customer already
            // has, and rendering never depends on someone else's web server
            // being up.
            $table->string('logo_mime')->nullable();
            $table->text('logo_data')->nullable();
        });

        // Uploaded logos do not survive this. There is exactly one row and it
        // is a single field to fill in again, which is cheaper than a
        // conversion path that would then need a state for "stored, but from
        // no address".
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
            $table->dropColumn(['logo_url', 'logo_mime', 'logo_data']);
        });
    }
};
