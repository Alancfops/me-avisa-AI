<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ticker
 * @property string $rule
 * @property string $price
 * @property string $reference
 * @property string $reason
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $quoted_at
 */
final class AlertModel extends Model
{
    use StoresInstants;

    protected $table = 'alerts';

    protected $fillable = ['ticker', 'rule', 'price', 'reference', 'reason', 'occurred_at', 'quoted_at'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'quoted_at' => 'immutable_datetime',
        ];
    }
}
