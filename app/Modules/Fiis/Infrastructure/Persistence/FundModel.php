<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class FundModel extends Model
{
    protected $table = 'funds';

    protected $primaryKey = 'ticker';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['ticker', 'name'];
}
