<?php


use Anubarak\Sitemap\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:craft', 'craft.web', 'can:accessCp'])->group(function() {
    Route::get(
        'secondred-sitemap',
        [SettingsController::class, 'index']
    );

    Route::post(
        'secondred-sitemap',
        [SettingsController::class, 'save']
    );
});

