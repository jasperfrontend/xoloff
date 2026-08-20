<?php

namespace App\Models;

use App\Enums\PremadeTextKey;
use Database\Factories\PremadeTextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property PremadeTextKey $key
 * @property string $content
 */
class PremadeText extends Model
{
    /** @use HasFactory<PremadeTextFactory> */
    use HasFactory;

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
