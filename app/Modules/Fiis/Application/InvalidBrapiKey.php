<?php

namespace App\Modules\Fiis\Application;

use RuntimeException;

/** O texto colado não pode ser usado como chave. A mensagem vai para a pessoa. */
final class InvalidBrapiKey extends RuntimeException {}
