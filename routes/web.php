<?php

use App\Modules\Fiis\Http\DashboardController;
use App\Modules\Fiis\Http\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('home');

Route::post('favoritos', [FavoriteController::class, 'store'])->name('favorites.store');
Route::delete('favoritos/{ticker}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
