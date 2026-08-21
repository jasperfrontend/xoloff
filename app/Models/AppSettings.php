<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use App\Support\Logo\FetchedLogo;
use Illuminate\Database\Eloquent\Model;

/**
 * The single row of application-wide configuration (SPEC §3).
 *
 * @property int $id
 * @property string|null $logo_url
 * @property string|null $logo_mime
 * @property string|null $logo_data
 * @property string|null $company_name
 * @property string|null $company_address
 * @property string|null $company_kvk
 * @property string|null $company_vat_number
 * @property int $default_validity_days
 */
class AppSettings extends Model implements DescribesItselfForAudit
{
    use RecordsItsOwnChanges;

    protected $table = 'app_settings';

    protected $fillable = [
        'logo_url',
        'logo_mime',
        'logo_data',
        'company_name',
        'company_address',
        'company_kvk',
        'company_vat_number',
        'default_validity_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_validity_days' => 'integer',
        ];
    }

    public function auditLabel(): string
    {
        return __('Application settings');
    }

    /**
     * The logo's bytes never reach the audit log.
     *
     * This model records its own changes, and a payload people browse is no
     * place for fifty kilobytes of base64 - it would bury every real change
     * around it. The address is kept, which is the part worth reading.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['logo_data'];
    }

    /**
     * Whether there is a logo to print. The address alone is not enough: it is
     * the bytes fetched from it that reach a quote.
     */
    public function hasLogo(): bool
    {
        return $this->logo_data !== null && $this->logo_mime !== null;
    }

    /**
     * The stored logo, or null if none has been fetched.
     */
    public function logo(): ?FetchedLogo
    {
        if (! $this->hasLogo()) {
            return null;
        }

        return new FetchedLogo(
            (string) $this->logo_mime,
            (string) base64_decode((string) $this->logo_data, strict: true),
        );
    }

    /**
     * Stored base64 rather than as raw bytes.
     *
     * Postgres would hold the bytes in a bytea perfectly well, but every
     * consumer here wants base64 in the end - a data uri for the PDF, and a
     * response body for the browser - so encoding once on the way in beats
     * decoding a stream on the way out of every read.
     */
    public function storeLogo(FetchedLogo $logo, string $url): void
    {
        $this->update([
            'logo_url' => $url,
            'logo_mime' => $logo->mime,
            'logo_data' => base64_encode($logo->bytes),
        ]);
    }

    public function forgetLogo(): void
    {
        $this->update(['logo_url' => null, 'logo_mime' => null, 'logo_data' => null]);
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
