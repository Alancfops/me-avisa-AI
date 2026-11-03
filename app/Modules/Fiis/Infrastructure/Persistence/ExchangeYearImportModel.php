<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $year
 */
final class ExchangeYearImportModel extends Model
{
    protected $table = 'exchange_year_imports';

    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $fillable = ['year'];
}
