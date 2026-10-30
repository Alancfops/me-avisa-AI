<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
