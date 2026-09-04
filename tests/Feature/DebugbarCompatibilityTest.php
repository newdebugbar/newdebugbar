<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Drawer\Utils;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\ProfileStore;

it('stops the request feedback loop when Laravel Debugbar is also active', function (string $prefix) {
    config(['debugbar.route_prefix' => $prefix]);

    // Laravel Debugbar v4.4.2 names its storage endpoint debugbar.openhandler.
    Route::prefix($prefix)->group(function () {
        Route::get('open', fn (Request $request) => response()->json([
            '__meta' => ['id' => $request->query('id')],
        ]))->name('debugbar.openhandler');
    });

    // Its response protocol adds phpdebugbar-id to application responses,
    // including our Livewire toolbar updates, but not its own storage responses.
    Event::listen(RequestHandled::class, function (RequestHandled $event) {
        if (! $event->request->is(config('debugbar.route_prefix').'*')) {
            $event->response->headers->set('phpdebugbar-id', (string) Str::ulid());
        }
    });

    $host = $this->get('/profiled')->assertOk();
    $toolbar = (string) app('livewire')->mount('newdebugbar.toolbar', [
        'profileId' => $host->headers->get('X-NewDebugBar-Profile'),
    ]);
    $snapshot = Utils::extractAttributeDataFromHtml($toolbar, 'wire:snapshot');
    $storedBefore = count(File::files(config('newdebugbar.storage.path')));
    $updateUri = app('livewire')->getUpdateUri();
    $openUri = '/'.ltrim(trim($prefix, '/').'/open', '/');
    $requests = [];
    $call = ['method' => 'loadSection', 'params' => ['request']];

    // Replay both clients' header-driven discovery, capped so a regression
    // records the repeating requests instead of hanging the test runner.
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $requests[] = 'update';
        $update = $this->postJson($updateUri, [
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [$call],
            ]],
        ], ['X-Livewire' => '1'])->assertOk()->assertHeader('phpdebugbar-id');
        $snapshot = json_decode($update->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

        // PHP Debugbar v3.8.0 AjaxHandler.loadFromId -> OpenHandler.load.
        $requests[] = 'open';
        $legacyId = $update->headers->get('phpdebugbar-id');
        $open = $this->getJson($openUri.'?'.http_build_query(['op' => 'get', 'id' => $legacyId]))
            ->assertOk()
            ->assertExactJson(['__meta' => ['id' => $legacyId]]);
        $profileId = $open->headers->get('X-NewDebugBar-Profile');

        if ($profileId === null) {
            break;
        }

        // New Debug Bar's discovery bridge calls noticeProfile for each new ID.
        $call = ['method' => 'noticeProfile', 'params' => [$profileId]];
    }

    expect($requests)->toBe(['update', 'open'])
        ->and(count(File::files(config('newdebugbar.storage.path'))))->toBe($storedBefore)
        ->and(app(ProfileManager::class)->isCollecting())->toBeFalse();

    $next = $this->getJson('/plain-json')->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($next->headers->get('X-NewDebugBar-Profile'));

    expect($profile['sections']['request']['payload']['path'])->toBe('/plain-json')
        ->and(count(File::files(config('newdebugbar.storage.path'))))->toBe($storedBefore + 1);
})->with([
    'default prefix' => ['_debugbar'],
    'custom nested prefix' => ['tools/debug'],
    'trailing slash' => ['tools/debug/'],
]);

it('keeps application routes that resemble Laravel Debugbar endpoints', function (string $path) {
    config(['debugbar.route_prefix' => '_debugbar']);
    Route::get($path, fn () => response()->json(['application' => true]))->name('application.debug-report');

    $this->getJson($path)
        ->assertOk()
        ->assertExactJson(['application' => true])
        ->assertHeader('X-NewDebugBar-Profile');
})->with([
    'similar segment' => ['/_debugbar-reports'],
    'inside the same prefix' => ['/_debugbar/reports'],
    'storage-shaped application route' => ['/_debugbar/open'],
]);
