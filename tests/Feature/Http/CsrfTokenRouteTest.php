<?php

use Illuminate\Support\Facades\Route;

it('hands back the token the session currently holds', function (): void {
    $this->startSession();

    $response = $this->get('/__newdebugbar/csrf');

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['token' => session()->token()]);
});

it('reports the rotated token after the session regenerates', function (): void {
    $this->startSession();
    $before = session()->token();

    session()->regenerateToken();

    expect(session()->token())->not->toBe($before);

    $this->get('/__newdebugbar/csrf')->assertExactJson(['token' => session()->token()]);
});

it('runs the route through the web middleware so a session exists', function (): void {
    $route = Route::getRoutes()->getByName('newdebugbar.csrf');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('GET')
        ->and($route->gatherMiddleware())->toContain('web');
});
