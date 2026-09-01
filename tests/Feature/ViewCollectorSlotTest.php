<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\Components\ProfiledSlotHost;
use NewDebugBar\Tests\Fixtures\Components\ProfiledSlotIcon;

it('renders class components that collect named slots while views are profiled', function () {
    Blade::component('profiled-slot-host', ProfiledSlotHost::class);
    Blade::component('profiled-slot-icon', ProfiledSlotIcon::class);

    Route::middleware(ProfileRequest::class)->get('/profiled-slot-component', function () {
        return response(
            '<!doctype html><html><body>'.Blade::render('<x-profiled-slot-host />').'</body></html>',
        );
    });

    $response = $this->get('/profiled-slot-component')->assertOk();

    expect($response->getContent())
        ->toContain('data-testid="profiled-slot-host"')
        ->toContain('data-testid="profiled-slot-icon"')
        ->and(app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'))['sections']['views']['summary']['count'])
        ->toBeGreaterThan(0);
});
