<?php

use App\Modules\Fiis\Http\AlertController;
use App\Modules\Fiis\Http\DashboardController;
use App\Modules\Fiis\Http\FavoriteController;
use App\Modules\Fiis\Http\FundController;
use App\Modules\Fiis\Http\FundListController;
use App\Modules\Fiis\Http\HealthController;
use App\Modules\Fiis\Http\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('home');

Route::post('favoritos', [FavoriteController::class, 'store'])->name('favorites.store');
Route::delete('favoritos/{ticker}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

Route::get('fundos', FundListController::class)->name('funds.index');
Route::get('fundos/{ticker}', [FundController::class, 'show'])->name('funds.show');
Route::put('fundos/{ticker}/regras', [FundController::class, 'update'])->name('funds.settings');

Route::get('carteira', [PortfolioController::class, 'index'])->name('portfolio');
Route::put('carteira/{ticker}', [PortfolioController::class, 'save'])->name('portfolio.save');
Route::delete('carteira/{ticker}', [PortfolioController::class, 'remove'])->name('portfolio.remove');

Route::get('avisos', AlertController::class)->name('alerts');

Route::get('saude', HealthController::class)->name('health');

Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
