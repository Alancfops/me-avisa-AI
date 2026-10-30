<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $cnpj
 * @property string $ticker
 * @property string $month
 * @property int $version
 * @property string $delivered_on
 * @property string|null $book_value
 * @property string|null $dividend_yield
 */
final class FundReportModel extends Model
{
    protected $table = 'fund_reports';

    protected $fillable = ['cnpj', 'ticker', 'month', 'version', 'delivered_on', 'book_value', 'dividend_yield'];
}
