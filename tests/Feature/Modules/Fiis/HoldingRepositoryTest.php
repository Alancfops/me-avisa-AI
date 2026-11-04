<?php

use App\Modules\Fiis\Infrastructure\Persistence\EloquentHoldingRepository;
use App\Modules\Fiis\Infrastructure\Persistence\HoldingModel;

it('saves and updates one row per ticker', function () {
    $repository = new EloquentHoldingRepository;
    $repository->save('MXRF11', 50);
    $repository->save('KNRI11', 10);
    $repository->save('KNRI11', 100);

    expect($repository->all())->toBe(['KNRI11' => 100, 'MXRF11' => 50])
        ->and(HoldingModel::query()->count())->toBe(2)
        ->and($repository->shares('KNRI11'))->toBe(100)
        ->and($repository->shares('HGLG11'))->toBeNull();
});

it('tells whether the removed ticker was there', function () {
    $repository = new EloquentHoldingRepository;
    $repository->save('KNRI11', 10);

    expect($repository->remove('KNRI11'))->toBeTrue()
        ->and($repository->remove('KNRI11'))->toBeFalse()
        ->and($repository->all())->toBe([]);
});
