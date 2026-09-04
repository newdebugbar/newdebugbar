<?php

use Illuminate\Http\Request;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\RequestEligibility;

$livewireEligibilityMessage = function (string $name): array {
    return [
        'snapshot' => json_encode([
            'data' => [],
            'memo' => ['id' => 'component-id', 'name' => $name],
            'checksum' => 'not-used-by-eligibility',
        ], JSON_THROW_ON_ERROR),
        'updates' => [],
        'calls' => [],
    ];
};

it('profiles application requests and excludes package owned traffic', function (Request $request, bool $allowed) {
    expect(app(RequestEligibility::class)->allows($request))->toBe($allowed);
})->with([
    'html page' => [fn () => Request::create('/dashboard', server: ['HTTP_ACCEPT' => 'text/html']), true],
    'json response' => [fn () => Request::create('/dashboard', server: ['HTTP_ACCEPT' => 'application/json']), true],
    'host Livewire update' => [fn () => Request::create(
        '/livewire/update',
        'POST',
        ['components' => [$livewireEligibilityMessage('appointments')]],
        server: ['HTTP_X_LIVEWIRE' => 'true'],
    ), true],
    'package toolbar update' => [fn () => Request::create(
        '/livewire/update',
        'POST',
        ['components' => [$livewireEligibilityMessage('newdebugbar.toolbar')]],
        server: ['HTTP_X_LIVEWIRE' => 'true'],
    ), false],
    'mixed host and toolbar update' => [fn () => Request::create(
        '/livewire/update',
        'POST',
        ['components' => [
            $livewireEligibilityMessage('appointments'),
            $livewireEligibilityMessage('newdebugbar.toolbar'),
        ]],
        server: ['HTTP_X_LIVEWIRE' => 'true'],
    ), true],
    'partly malformed Livewire update' => [fn () => Request::create(
        '/livewire/update',
        'POST',
        ['components' => [
            $livewireEligibilityMessage('appointments'),
            ['snapshot' => 'not-json'],
        ]],
        server: ['HTTP_X_LIVEWIRE' => 'true'],
    ), false],
    'malformed Livewire update' => [fn () => Request::create(
        '/livewire/update',
        'POST',
        ['components' => [['snapshot' => 'not-json']]],
        server: ['HTTP_X_LIVEWIRE' => 'true'],
    ), false],
    'package asset' => [fn () => Request::create('/__newdebugbar/assets/newdebugbar.js', server: ['HTTP_ACCEPT' => 'text/html']), false],
    'package Livewire runtime asset' => [fn () => Request::create('/livewire-95508dcc/livewire.js', server: ['HTTP_ACCEPT' => 'text/javascript']), false],
    'ordinary route named like Livewire' => [fn () => Request::create('/livewire/update', server: ['HTTP_ACCEPT' => 'text/html']), true],
    'ordinary similarly named script' => [fn () => Request::create('/livewire-example/app.js', server: ['HTTP_ACCEPT' => 'text/javascript']), true],
]);

it('stops profiling when the package is disabled', function () {
    config()->set('newdebugbar.enabled', false);

    $request = Request::create('/dashboard', server: ['HTTP_ACCEPT' => 'text/html']);

    expect(app(RequestEligibility::class)->allows($request))->toBeFalse();
});

it('excludes only configured request path patterns', function (string $path, bool $allowed) {
    config()->set('newdebugbar.except', ['account/reset/*', '/billing/private', '/', '', null]);

    expect(app(RequestEligibility::class)->allows(Request::create($path)))->toBe($allowed);
})->with([
    'wildcard' => ['/account/reset/private-value', false],
    'root' => ['/', false],
    'leading slash' => ['/billing/private', false],
    'similar route remains captured' => ['/billing/private-preview', true],
    'unrelated route' => ['/dashboard', true],
]);

it('does not save or identify an excluded request', function () {
    config()->set('newdebugbar.except', ['plain-json']);

    $this->getJson('/plain-json')->assertOk()->assertExactJson(['ready' => true])
        ->assertHeaderMissing('X-NewDebugBar-Profile');

    expect(app(ProfileStore::class)->recent())->toBe([]);
});

it('stops collection when the allowed environment changes', function () {
    config()->set('newdebugbar.environments', ['local']);

    expect(app(RequestEligibility::class)->allows(Request::create('/dashboard')))->toBeFalse();
});

it('leaves excluded HTML untouched while profiling an allowed sibling', function () {
    config()->set('newdebugbar.except', ['profiled']);

    $this->get('/profiled', ['Accept' => 'text/html'])->assertOk()
        ->assertHeaderMissing('X-NewDebugBar-Profile')
        ->assertDontSee('id="newdebugbar"', false);
    expect(app(ProfileStore::class)->recent())->toBe([]);

    $id = $this->get('/profiled-next', ['Accept' => 'text/html'])->assertOk()
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertSee('id="newdebugbar"', false)
        ->headers->get('X-NewDebugBar-Profile');

    expect(app(ProfileStore::class)->get($id)['sections']['request']['payload']['path'])->toBe('/profiled-next');
});
