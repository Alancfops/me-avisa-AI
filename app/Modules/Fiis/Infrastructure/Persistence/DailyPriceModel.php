<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ticker
 * @property string $day
 * @property string $close
 */
final class DailyPriceModel extends Model
{
    protected $table = 'daily_prices';

    protected $fillable = ['ticker', 'day', 'close'];
}
