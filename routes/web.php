<?php

use Anubarak\Sitemap\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:craft', 'craft.web'])->group(function() {
    Route::get(
        'sitemap{suffix?}.xml',
        [SitemapController::class, 'index']
    );
    // <:[a-zA-Z_-].*>
});

