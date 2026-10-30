<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $source
 * @property string $kind
 * @property string|null $message
 * @property CarbonImmutable $occurred_at
 */
final class SourceEventModel extends Model
{
    use StoresInstants;

    protected $table = 'source_events';

    protected $fillable = ['source', 'kind', 'message', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
