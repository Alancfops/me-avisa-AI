<?php

namespace App\Modules\Fiis\Application;

/**
 * A B3 respondeu 404 para um arquivo anual. É erro, salvo para o ano corrente em
 * janeiro, quando o arquivo ainda não existe: quem importa decide.
 */
final class ExchangeFileMissing extends SourceUnavailable {}
