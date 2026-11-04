<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ticker
 * @property int $shares
 */
final class HoldingModel extends Model
{
    protected $table = 'holdings';

    protected $fillable = ['ticker', 'shares'];

    protected function casts(): array
    {
        return ['shares' => 'integer'];
    }
}
