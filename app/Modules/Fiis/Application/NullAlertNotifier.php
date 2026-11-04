<?php

namespace App\Modules\Fiis\Application;

final class NullAlertNotifier implements AlertNotifier
{
    public function notify(Alert $alert): void {}
}
