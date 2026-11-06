<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\AlertNotifier;
use RuntimeException;

final class RecordingNotifier implements AlertNotifier
{
    /** @var list<Alert> */
    public array $notified = [];

    public function __construct(public bool $fails = false) {}

    public function notify(Alert $alert): void
    {
        if ($this->fails) {
            throw new RuntimeException('Telegram fora do ar');
        }

        $this->notified[] = $alert;
    }
}
