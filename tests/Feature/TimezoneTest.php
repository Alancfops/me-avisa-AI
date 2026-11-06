<?php

it('uses the Sao Paulo timezone', fn () => expect(config('app.timezone'))->toBe('America/Sao_Paulo'));
