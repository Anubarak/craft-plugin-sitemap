<?php

use Anubarak\Sitemap\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['craft.web'])->group(function() {
    Route::get(
        'sitemap{suffix?}.xml',
        [SitemapController::class, 'index']
    );
    Route::get(
        'sitemap.xml',
        [SitemapController::class, 'index']
    )
        ->name('sitemap-index');

    // <:[a-zA-Z_-].*>
});

