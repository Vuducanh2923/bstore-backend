<?php

use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs', [SwaggerController::class, 'ui'])->name('swagger.ui');

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'service' => config('app.name'),
        'docs' => url('/docs'),
    ]);
});
