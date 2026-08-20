<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The intro and footer shown on every quote (SPEC §3). Exactly two rows,
        // seeded, edited in place - never created or deleted through the UI,
        // because the PDF template places each key somewhere specific.
        Schema::create('premade_texts', function (Blueprint $table) {
            $table->id();

            // Unique because a second row under the same key would make
            // "the intro text" ambiguous.
            $table->string('key')->unique();

            // Basic HTML from the editor, allowlisted on the way in. The
            // footer holds the mandatory legal disclaimer (algemene
            // voorwaarden), which is a legal requirement rather than optional
            // copy, so it is not nullable.
            $table->text('content');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premade_texts');
    }
};
