<?php

namespace App\Modules\Telegram\Infrastructure;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $chat_id
 * @property CarbonImmutable|null $connected_at
 * @property string|null $link_code
 * @property CarbonImmutable|null $link_code_expires_at
 * @property int|null $last_update_id
 */
final class TelegramStateModel extends Model
{
    protected $table = 'telegram_state';

    protected $fillable = ['chat_id', 'connected_at', 'link_code', 'link_code_expires_at', 'last_update_id'];

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:sP';
    }

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'connected_at' => 'immutable_datetime',
            'link_code_expires_at' => 'immutable_datetime',
            'last_update_id' => 'integer',
        ];
    }
}
