<?php

use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;

it('shows saved favorites on the dashboard', function () {
    FavoriteModel::create(['ticker' => 'MXRF11']);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('favorites.0.ticker', 'MXRF11'));
});

it('shows an empty dashboard without favorites', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('favorites', []));
});

it('lists favorites in a stable order', function () {
    FavoriteModel::create(['ticker' => 'VISC11']);
    FavoriteModel::create(['ticker' => 'KNRI11']);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('favorites.0.ticker', 'KNRI11')
        ->where('favorites.1.ticker', 'VISC11'));
});
