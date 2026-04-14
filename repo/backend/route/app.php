<?php

use think\facade\Route;

// API v1 routes
Route::group('api/v1', function () {

    // Health check - no auth required
    Route::get('ping', 'Index/ping');

})->allowCrossDomain();
