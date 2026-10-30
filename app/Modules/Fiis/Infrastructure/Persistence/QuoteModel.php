<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ticker
 * @property string $price
 * @property string|null $change_percent
 * @property int|null $volume
 * @property CarbonImmutable $quoted_at
 */
final class QuoteModel extends Model
{
    protected $table = 'quotes';

    protected $primaryKey = 'ticker';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['ticker', 'price', 'change_percent', 'volume', 'quoted_at'];

    protected function casts(): array
    {
        return ['quoted_at' => 'immutable_datetime'];
    }
}
