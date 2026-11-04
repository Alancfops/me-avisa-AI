<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Chaves coladas pelo site, uma linha por nome.
 *
 * @property string $name
 * @property string $value
 */
final class AppSecretModel extends Model
{
    protected $table = 'app_secrets';

    protected $fillable = ['name', 'value'];

    protected function casts(): array
    {
        // Criptografado com a APP_KEY: o banco nunca guarda a chave em texto puro.
        return ['value' => 'encrypted'];
    }
}
