<?php

use Illuminate\Support\Facades\DB;

it('runs on Postgres', fn () => expect(DB::connection()->getDriverName())->toBe('pgsql'));

// RefreshDatabase apaga tudo: rodar no banco do app perderia os dados do usuário.
it('never runs tests on the application database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('testing');
});
