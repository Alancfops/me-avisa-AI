<?php

namespace App\Modules\Telegram\Application;

use RuntimeException;

/** O texto colado não pode ser usado como token. A mensagem vai para a pessoa. */
final class InvalidTokenInput extends RuntimeException {}
