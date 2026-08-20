<?php

namespace App\Providers;

use App\Models\AppSettings;
use App\Models\Customer;
use App\Models\PremadeText;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->nameEntitiesForTheAuditLog();
    }

    /**
     * The names the audit log stores in entity_type (SPEC §3).
     *
     * Written down rather than derived from class names, so that an entry
     * written today still reads the same after a model is renamed or moved -
     * the log outlives the row it describes.
     *
     * Enforced, so auditing a model that was never named here fails loudly
     * instead of quietly writing a fully qualified class name into the column.
     * User is absent on purpose: nothing audits people, which keeps password
     * hashes out of payloads that get browsed.
     */
    protected function nameEntitiesForTheAuditLog(): void
    {
        Relation::enforceMorphMap([
            'app_settings' => AppSettings::class,
            'customer' => Customer::class,
            'premade_text' => PremadeText::class,
            'product' => Product::class,
            'product_category' => ProductCategory::class,
            'quote' => Quote::class,
            'quote_version' => QuoteVersion::class,
            'tax_class' => TaxClass::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
