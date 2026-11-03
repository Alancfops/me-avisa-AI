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
 * @property string|null $segment
 * @property string|null $administrator
 * @property string|null $started_on
 * @property string|null $net_assets
 * @property int|null $holders
 */
final class FundReportModel extends Model
{
    protected $table = 'fund_reports';

    protected $fillable = ['cnpj', 'ticker', 'month', 'version', 'delivered_on', 'book_value', 'dividend_yield', 'segment', 'administrator', 'started_on', 'net_assets', 'holders'];
}
