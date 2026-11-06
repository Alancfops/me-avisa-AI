<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $day
 */
final class ExchangeImportModel extends Model
{
    protected $table = 'exchange_imports';

    protected $primaryKey = 'day';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['day'];
}
