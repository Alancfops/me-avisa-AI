<?php

namespace App\Modules\Fiis\Application;

use RuntimeException;

/**
 * A fonte externa não entregou dado válido. A mensagem vai para log e para a tela
 * de Saúde: precisa ser legível e nunca carregar token ou corpo de resposta.
 */
final class SourceUnavailable extends RuntimeException {}
