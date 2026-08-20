<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One log covering both CRUD operations and status and notification
        // events, not separate tables (SPEC §3). Singular table name, as the
        // spec names it.
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            // Nullable for system-generated events: a seeder, a console
            // command, or anything else that happens without a person.
            // nullOnDelete rather than cascade, because deleting a user must
            // not erase the history of what they did.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // A polymorphic reference, but deliberately not a real morphTo
            // relation: the entity it names is usually gone by the time anyone
            // reads the entry. Types come from the morph map in
            // AppServiceProvider, so they stay readable and survive a class
            // being renamed or moved.
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            $table->string('action');

            // The diff or event detail. Also carries quote_id for anything
            // belonging to a quote, which is what makes filtering by quote
            // possible without a second column the spec does not define.
            $table->jsonb('payload');

            $table->timestamps();

            // The three ways the UI is required to filter (SPEC §3).
            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
