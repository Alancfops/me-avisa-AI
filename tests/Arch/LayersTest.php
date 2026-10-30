<?php

arch('domain has no framework')
    ->expect('App\Modules\Fiis\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Application', 'App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http']);

// O momento chega ao Domain como valor: relógio e configuração ficam de fora.
arch('domain has no clock or configuration')
    ->expect('App\Modules\Fiis\Domain')
    ->not->toUse(['Carbon', 'DateTime', 'DateTimeImmutable', 'now', 'today', 'time', 'date', 'config', 'env']);

arch('application does not know outer layers')
    ->expect('App\Modules\Fiis\Application')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http']);

arch('http does not know infrastructure')
    ->expect('App\Modules\Fiis\Http')
    ->not->toUse(['App\Modules\Fiis\Infrastructure', 'Illuminate\Database']);

// O toUse ignora classes internas do PHP, então estas duas precisam de uma varredura própria.
it('domain does not use native date classes', function () {
    $forbidden = ['DateTime', 'DateTimeImmutable'];
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../app/Modules/Fiis/Domain', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        foreach (PhpToken::tokenize((string) file_get_contents($file->getPathname())) as $token) {
            if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && in_array(ltrim($token->text, '\\'), $forbidden, true)) {
                $found[] = "{$file->getFilename()}: {$token->text}";
            }
        }
    }

    expect($found)->toBe([]);
});
