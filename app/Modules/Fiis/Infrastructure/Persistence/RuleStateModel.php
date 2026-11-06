<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ticker
 * @property string $rule
 * @property bool $active
 * @property bool $pending
 * @property string|null $event_key
 * @property string|null $last_alert_price
 * @property CarbonImmutable|null $last_alert_at
 */
final class RuleStateModel extends Model
{
    use StoresInstants;

    protected $table = 'rule_states';

    protected $fillable = ['ticker', 'rule', 'active', 'pending', 'event_key', 'last_alert_price', 'last_alert_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'pending' => 'boolean',
            'last_alert_at' => 'immutable_datetime',
        ];
    }
}
