<?php

namespace App\Modules\Fiis\Application;

final readonly class RefreshResult
{
    /**
     * @param  list<string>  $updated
     * @param  list<string>  $failed
     */
    public function __construct(
        public array $updated,
        public array $failed,
    ) {}
}
