<?php

it('has no authentication routes', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/login', '/register', '/forgot-password', '/settings/profile']);

it('opens the app without login', function () {
    $this->get('/')->assertOk();
});
