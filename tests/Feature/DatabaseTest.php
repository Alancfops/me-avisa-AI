<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

it('runs on Postgres', fn () => expect(DB::connection()->getDriverName())->toBe('pgsql'));

it('never runs tests on the application database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('testing');
});

it('aborts on any database other than testing', function () {
    expect(fn () => TestCase::abortUnlessTestingDatabase('me_avisa_ai'))
        ->toThrow(RuntimeException::class, 'me_avisa_ai');
});
