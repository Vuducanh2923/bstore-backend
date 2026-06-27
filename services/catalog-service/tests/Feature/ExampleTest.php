<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('swagger documentation is available', function () {
    $this->get('/docs')->assertOk();
    $this->getJson('/api/docs/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.3');
});

test('banner resource route is available', function () {
    $route = Route::getRoutes()->match(Request::create('/api/banners', 'GET'));

    expect($route->getActionName())->toContain('BannerController@index');

    $this->getJson('/api/docs/openapi.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.Banner.properties.image_source.enum.1', 'database');
});
