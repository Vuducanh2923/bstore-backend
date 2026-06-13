<?php

use App\Http\Controllers\Api\GatewayController;
use Illuminate\Support\Facades\Route;

Route::get('/gateway/health', [GatewayController::class, 'health']);

Route::any('/{path}', [GatewayController::class, 'forward'])
    ->where('path', '.*');
