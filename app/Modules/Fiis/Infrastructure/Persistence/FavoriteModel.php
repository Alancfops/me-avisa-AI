<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class FavoriteModel extends Model
{
    protected $table = 'favorites';

    protected $fillable = ['ticker'];
}
