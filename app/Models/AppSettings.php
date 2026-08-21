<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * The single row of application-wide configuration (SPEC §3).
 *
 * @property int $id
 * @property string|null $logo_path
 * @property string|null $company_name
 * @property string|null $company_address
 * @property string|null $company_kvk
 * @property string|null $company_vat_number
 */
class AppSettings extends Model implements DescribesItselfForAudit
{
    use RecordsItsOwnChanges;

    protected $table = 'app_settings';

    protected $fillable = [
        'logo_path',
        'company_name',
        'company_address',
        'company_kvk',
        'company_vat_number',
    ];

    public function auditLabel(): string
    {
        return __('Application settings');
    }

    /**
     * The one row. Created by the migration, so this normally just reads it -
     * the fallback covers a database where that row was removed by hand.
     */
    public static function current(): self
    {
        return static::query()->firstOr(fn (): self => static::query()->create([]));
    }
}
