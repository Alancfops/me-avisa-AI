<?php

namespace App\Modules\Telegram\Application;

use RuntimeException;

/** O Telegram não respondeu como devia. A mensagem vai para a Saúde: sem token. */
class TelegramUnavailable extends RuntimeException {}
