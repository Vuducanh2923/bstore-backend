<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\HomeBannerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

Route::post('/uploads/images', [UploadController::class, 'image']);

Route::get('/admin/brands', [AdminBrandController::class, 'index']);
Route::post('/admin/brands', [AdminBrandController::class, 'store']);
Route::put('/admin/brands/{id}', [AdminBrandController::class, 'update'])->whereNumber('id');
Route::delete('/admin/brands/{id}', [AdminBrandController::class, 'destroy'])->whereNumber('id');
Route::patch('/admin/brands/{id}/toggle-status', [AdminBrandController::class, 'toggleStatus'])->whereNumber('id');

Route::get('/home/banners', [HomeBannerController::class, 'index']);
Route::get('/banners/home', [HomeBannerController::class, 'index']);

Route::get('/banners', [BannerController::class, 'index']);
Route::get('/banners/{id}', [BannerController::class, 'show'])->whereNumber('id');
Route::post('/banners', [BannerController::class, 'store']);
Route::post('/banners/{id}', [BannerController::class, 'update'])->whereNumber('id');
Route::put('/banners/{id}', [BannerController::class, 'update'])->whereNumber('id');
Route::patch('/banners/{id}', [BannerController::class, 'update'])->whereNumber('id');
Route::delete('/banners/{id}', [BannerController::class, 'destroy'])->whereNumber('id');

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/brands', [BrandController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/sale', [ProductController::class, 'sale']);
Route::get('/products/new', [ProductController::class, 'newProducts']);
Route::get('/products/{id}', [ProductController::class, 'showById'])->whereNumber('id');
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
Route::patch('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
Route::delete('/products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
