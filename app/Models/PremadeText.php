<?php

namespace App\Models;

use App\Concerns\RecordsItsOwnChanges;
use App\Contracts\DescribesItselfForAudit;
use App\Enums\PremadeTextKey;
use Database\Factories\PremadeTextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property PremadeTextKey $key
 * @property string $content
 */
class PremadeText extends Model implements DescribesItselfForAudit
{
    /** @use HasFactory<PremadeTextFactory> */
    use HasFactory, RecordsItsOwnChanges;

    protected $fillable = [
        'key',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => PremadeTextKey::class,
        ];
    }

    public function auditLabel(): string
    {
        return match ($this->key) {
            PremadeTextKey::Intro => __('Intro text'),
            PremadeTextKey::Footer => __('Footer text'),
        };
    }

    /**
     * The content to snapshot onto a quote version. Returns an empty string
     * rather than null when the row is missing, so a quote saved on a database
     * that was never seeded still gets a usable snapshot instead of failing.
     */
    public static function contentFor(PremadeTextKey $key): string
    {
        return static::query()->where('key', $key)->value('content') ?? '';
    }
}
