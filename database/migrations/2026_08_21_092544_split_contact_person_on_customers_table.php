<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // One name field cannot be greeted with. "Beste Daan Daansen" is not
        // how anyone writes, so the quote texts need the parts separately.
        Schema::table('customers', function (Blueprint $table) {
            // Nullable, because leaving it off is a real choice: "Beste Daan"
            // wants no salutation at all. Stored as the bare word, so the copy
            // around it stays Stephan's - see App\Enums\Salutation.
            $table->string('salutation')->nullable()->after('company_name');

            // Not nullable, and required by the form. Defaulted only so the
            // backfill below has somewhere to put a name it cannot split.
            $table->string('first_name')->default('')->after('salutation');
            $table->string('last_name')->default('')->after('first_name');
        });

        $this->splitTheExistingNames();

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_person')->default('');
        });

        foreach (DB::table('customers')->get(['id', 'first_name', 'last_name']) as $customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'contact_person' => trim("{$customer->first_name} {$customer->last_name}"),
            ]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['salutation', 'first_name', 'last_name']);
        });
    }

    /**
     * Split on the first space, so a Dutch tussenvoegsel stays with the
     * surname it belongs to: "Jasper van der Meer" becomes Jasper and
     * "van der Meer", not "Jasper van der" and "Meer".
     *
     * A name with no space at all goes in first_name, which is the more likely
     * reading of a single word and the more visible place for it to be wrong -
     * an empty last name is refused the moment anyone opens that customer.
     */
    private function splitTheExistingNames(): void
    {
        foreach (DB::table('customers')->get(['id', 'contact_person']) as $customer) {
            $name = trim((string) $customer->contact_person);

            DB::table('customers')->where('id', $customer->id)->update([
                'first_name' => Str::before($name, ' '),
                'last_name' => trim(Str::contains($name, ' ') ? Str::after($name, ' ') : ''),
            ]);
        }
    }
};
