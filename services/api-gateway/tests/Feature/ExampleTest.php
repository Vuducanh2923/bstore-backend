<?php

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
