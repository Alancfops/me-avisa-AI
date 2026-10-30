<?php

use App\Modules\Fiis\Http\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('home');

Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
