<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ticker
 * @property string $mode
 * @property string|null $target
 * @property string|null $range_min
 * @property string|null $range_max
 * @property bool $sharp_drop
 * @property CarbonImmutable|null $last_evaluated_quote_at
 */
final class FavoriteModel extends Model
{
    use StoresInstants;

    protected $table = 'favorites';

    protected $fillable = ['ticker', 'mode', 'target', 'range_min', 'range_max', 'sharp_drop', 'last_evaluated_quote_at'];

    protected function casts(): array
    {
        return [
            'sharp_drop' => 'boolean',
            'last_evaluated_quote_at' => 'immutable_datetime',
        ];
    }
}
