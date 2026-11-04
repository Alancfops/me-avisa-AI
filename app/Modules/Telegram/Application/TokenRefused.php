<?php

namespace App\Modules\Telegram\Application;

/** O Telegram respondeu, mas não aceitou o token (HTTP 401). */
final class TokenRefused extends TelegramUnavailable {}
