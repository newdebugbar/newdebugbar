<?php

use Illuminate\Contracts\Http\Kernel;
use Laravel\Mcp\Facades\Mcp;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Storage\ProfileStore;

it('applies the debug default, explicit override, and environment restriction to the whole package', function (
    bool $debug,
    ?bool $override,
    bool $allowedEnvironment,
    bool $enabled,
) {
    $this->environmentOverrides = [
        'app.debug' => $debug,
        'newdebugbar.enabled' => $override,
        'newdebugbar.environments' => $allowedEnvironment ? ['testing'] : ['local'],
    ];
    $this->refreshApplication();

    app('router')->middleware('web')->get('/package-enablement', fn () => response(
        '<!doctype html><html><body>Application response</body></html>',
    ));

    $mcp = class_exists(Mcp::class) ? Mcp::class : Laravel\Mcp\Server\Facades\Mcp::class;
    $response = $this->get('/package-enablement')->assertOk();
    $id = $response->headers->get('X-NewDebugBar-Profile');

    expect(config('newdebugbar.enabled'))->toBe($override ?? $debug)
        ->and(app(Kernel::class)->hasMiddleware(ProfileRequest::class))->toBe($enabled)
        ->and(app('router')->getRoutes()->getByName('newdebugbar.asset') !== null)->toBe($enabled)
        ->and($mcp::getLocalServer('newdebugbar') !== null)->toBe($enabled)
        ->and($id !== null)->toBe($enabled)
        ->and(substr_count($response->getContent(), 'id="newdebugbar"'))->toBe($enabled ? 1 : 0);

    if ($enabled) {
        expect(app(ProfileStore::class)->get($id))->not->toBeNull();
    } else {
        expect(app(ProfileStore::class)->recent())->toBeEmpty();
    }
})->with([
    'debug on, automatic' => [true, null, true, true],
    'debug off, automatic' => [false, null, true, false],
    'debug on, forced on' => [true, true, true, true],
    'debug off, forced on' => [false, true, true, true],
    'debug on, forced off' => [true, false, true, false],
    'debug off, forced off' => [false, false, true, false],
    'outside allowed environments, debug on, automatic' => [true, null, false, false],
    'outside allowed environments, debug off, automatic' => [false, null, false, false],
    'outside allowed environments, debug on, forced on' => [true, true, false, false],
    'outside allowed environments, debug off, forced on' => [false, true, false, false],
    'outside allowed environments, debug on, forced off' => [true, false, false, false],
    'outside allowed environments, debug off, forced off' => [false, false, false, false],
]);
