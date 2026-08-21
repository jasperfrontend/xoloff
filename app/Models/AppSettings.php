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
 * @property string|null $logo_vector_url
 * @property string|null $logo_vector_mime
 * @property string|null $logo_vector_data
 * @property string|null $logo_raster_url
 * @property string|null $logo_raster_mime
 * @property string|null $logo_raster_data
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
        'logo_vector_url',
        'logo_vector_mime',
        'logo_vector_data',
        'logo_raster_url',
        'logo_raster_mime',
        'logo_raster_data',
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
        return ['logo_vector_data', 'logo_raster_data'];
    }

    /**
     * The logo for a screen or for print: the vector if there is one, and the
     * raster otherwise. Either on its own is enough.
     */
    public function webLogo(): ?FetchedLogo
    {
        return $this->logo('vector') ?? $this->logo('raster');
    }

    /**
     * The logo for an email, which can only be the raster.
     *
     * Gmail strips an SVG and Outlook will not draw one, so falling back to
     * the vector here would mean sending a broken image rather than none - and
     * a broken image is worse, because it leaves a placeholder box where a
     * missing one leaves nothing.
     */
    public function emailLogo(): ?FetchedLogo
    {
        return $this->logo('raster');
    }

    public function hasWebLogo(): bool
    {
        return $this->webLogo() !== null;
    }

    public function hasEmailLogo(): bool
    {
        return $this->emailLogo() !== null;
    }

    /**
     * Stored base64 rather than as raw bytes.
     *
     * Postgres would hold the bytes in a bytea perfectly well, but every
     * consumer here wants base64 in the end - a data uri for the PDF, and a
     * response body for the browser - so encoding once on the way in beats
     * decoding a stream on the way out of every read.
     */
    public function storeLogo(string $side, FetchedLogo $logo, string $url): void
    {
        $this->update([
            "logo_{$side}_url" => $url,
            "logo_{$side}_mime" => $logo->mime,
            "logo_{$side}_data" => base64_encode($logo->bytes),
        ]);
    }

    /**
     * Clearing the address is how a logo is removed, so there is no separate
     * delete to keep in step with the save.
     */
    public function forgetLogo(string $side): void
    {
        $this->update([
            "logo_{$side}_url" => null,
            "logo_{$side}_mime" => null,
            "logo_{$side}_data" => null,
        ]);
    }

    /**
     * @param  'vector'|'raster'  $side
     */
    private function logo(string $side): ?FetchedLogo
    {
        $mime = $this->{"logo_{$side}_mime"};
        $data = $this->{"logo_{$side}_data"};

        if ($mime === null || $data === null) {
            return null;
        }

        return new FetchedLogo($mime, (string) base64_decode((string) $data, strict: true));
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
