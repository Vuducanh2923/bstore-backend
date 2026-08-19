<?php

use App\Http\Controllers\Api\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\HomeBannerController;
use App\Http\Controllers\Api\InternalInventoryReservationController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

// Consumer-facing catalog reads are deliberately enumerated. There is no generic resource route.
Route::get('/home/banners', [HomeBannerController::class, 'index']);
Route::get('/banners/home', [HomeBannerController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/banners/{id}', [BannerController::class, 'show'])->whereNumber('id');
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/sale', [ProductController::class, 'sale']);
Route::get('/products/new', [ProductController::class, 'newProducts']);
Route::get('/products/{id}', [ProductController::class, 'showById'])->whereNumber('id');
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::middleware('customer.token')->group(function (): void {
    Route::post('/uploads/avatars', [UploadController::class, 'avatar']);
});

Route::middleware('admin.token')->group(function (): void {
    Route::post('/uploads/images', [UploadController::class, 'image']);

    Route::get('/admin/brands', [AdminBrandController::class, 'index']);
    Route::post('/admin/brands', [AdminBrandController::class, 'store']);
    Route::put('/admin/brands/{id}', [AdminBrandController::class, 'update'])->whereNumber('id');
    Route::delete('/admin/brands/{id}', [AdminBrandController::class, 'destroy'])->whereNumber('id');
    Route::patch('/admin/brands/{id}/toggle-status', [AdminBrandController::class, 'toggleStatus'])->whereNumber('id');

    Route::post('/banners', [BannerController::class, 'store']);
    Route::post('/banners/{id}', [BannerController::class, 'update'])->whereNumber('id');
    Route::put('/banners/{id}', [BannerController::class, 'update'])->whereNumber('id');
    Route::patch('/banners/{id}', [BannerController::class, 'update'])->whereNumber('id');
    Route::delete('/banners/{id}', [BannerController::class, 'destroy'])->whereNumber('id');

    Route::get('/categories/{id}', [ResourceController::class, 'show'])
        ->defaults('resource', 'categories')
        ->whereNumber('id');
    Route::post('/categories', [ResourceController::class, 'store'])->defaults('resource', 'categories');
    Route::put('/categories/{id}', [ResourceController::class, 'update'])
        ->defaults('resource', 'categories')
        ->whereNumber('id');
    Route::patch('/categories/{id}', [ResourceController::class, 'update'])
        ->defaults('resource', 'categories')
        ->whereNumber('id');
    Route::delete('/categories/{id}', [ResourceController::class, 'destroy'])
        ->defaults('resource', 'categories')
        ->whereNumber('id');

    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
    Route::put('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
    Route::patch('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');

    Route::get('/inventories', [InventoryController::class, 'index']);
    Route::get('/inventories/{id}', [InventoryController::class, 'show'])->whereNumber('id');
    Route::post('/inventories', [InventoryController::class, 'store']);
    Route::put('/inventories/{id}', [InventoryController::class, 'update'])->whereNumber('id');
    Route::patch('/inventories/{id}', [InventoryController::class, 'update'])->whereNumber('id');
    Route::delete('/inventories/{id}', [InventoryController::class, 'destroy'])->whereNumber('id');

    Route::get('/inventory-transactions', [ResourceController::class, 'index'])
        ->defaults('resource', 'inventory-transactions');
    Route::get('/inventory-transactions/{id}', [ResourceController::class, 'show'])
        ->defaults('resource', 'inventory-transactions')
        ->whereNumber('id');
    Route::get('/product-variants', [ResourceController::class, 'index'])
        ->defaults('resource', 'product-variants');
    Route::get('/product-variants/{id}', [ResourceController::class, 'show'])
        ->defaults('resource', 'product-variants')
        ->whereNumber('id');
    Route::get('/warranty-policies', [ResourceController::class, 'index'])
        ->defaults('resource', 'warranty-policies');
    Route::get('/warranty-policies/{id}', [ResourceController::class, 'show'])
        ->defaults('resource', 'warranty-policies')
        ->whereNumber('id');
    Route::post('/warranty-policies', [ResourceController::class, 'store'])
        ->defaults('resource', 'warranty-policies');
    Route::put('/warranty-policies/{id}', [ResourceController::class, 'update'])
        ->defaults('resource', 'warranty-policies')
        ->whereNumber('id');
    Route::patch('/warranty-policies/{id}', [ResourceController::class, 'update'])
        ->defaults('resource', 'warranty-policies')
        ->whereNumber('id');
    Route::delete('/warranty-policies/{id}', [ResourceController::class, 'destroy'])
        ->defaults('resource', 'warranty-policies')
        ->whereNumber('id');
});

Route::prefix('internal/inventory/reservations')
    ->middleware('internal.service')
    ->group(function (): void {
        Route::post('/', [InternalInventoryReservationController::class, 'store']);
        Route::post('/{reference}/commit', [InternalInventoryReservationController::class, 'commit']);
        Route::post('/{reference}/release', [InternalInventoryReservationController::class, 'release']);
        Route::post('/{reference}/restore', [InternalInventoryReservationController::class, 'restore']);
    });
