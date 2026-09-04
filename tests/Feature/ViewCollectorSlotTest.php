<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\InvokableComponentVariable;
use Illuminate\View\View;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\Components\ProfiledSlotHost;
use NewDebugBar\Tests\Fixtures\Components\ProfiledSlotIcon;
use NewDebugBar\Tests\Support\McpResponse;

it('profiles class components without rendering their view methods inside named slots', function () {
    $this->withoutExceptionHandling();

    Blade::component('profiled-slot-host', ProfiledSlotHost::class);
    Blade::component('profiled-slot-icon', ProfiledSlotIcon::class);

    Route::middleware(ProfileRequest::class)->get('/profiled-slot-component', fn () => response(
        '<!doctype html><html><body>'.Blade::render('<x-profiled-slot-host />').'</body></html>',
    ));

    $response = $this->get('/profiled-slot-component')->assertOk()
        ->assertSee('data-testid="profiled-slot-host"', false)
        ->assertSee('data-testid="profiled-slot-icon"', false)
        ->assertHeader('X-NewDebugBar-Profile');
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($profileId);
    $views = $profile['sections']['views']['payload']['items'];
    $iconIndex = collect($views)->search(fn (array $view): bool => $view['name'] === 'profiled-slot-icon');

    expect($iconIndex)->not->toBeFalse()
        ->and($views[$iconIndex]['data']['blade'])->toBe('['.InvokableComponentVariable::class.']')
        ->and(collect($views)->where('name', 'profiled-slot-icon'))->toHaveCount(1);

    $data = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/sections/views/payload/items/'.$iconIndex.'/data/blade',
    ])->assertOk());

    expect($data['data']['value'])->toBe('['.InvokableComponentVariable::class.']')
        ->and(app(GetDebugProfileData::class)->description())
        ->toContain('/sections/views/payload/items/{index}/data', 'class labels');
});

it('does not add renders for view objects supplied as data on repeated profiled requests', function () {
    $compositions = 0;
    view()->composer('context', function () use (&$compositions): void {
        $compositions++;
    });

    Route::middleware(ProfileRequest::class)->get('/profiled-lazy-view', fn () => view('original-response', [
        'label' => 'Original response',
        'preview' => view('context', ['label' => 'Unrendered preview']),
    ]));

    foreach (range(1, 2) as $request) {
        $response = $this->get('/profiled-lazy-view')->assertOk()->assertHeader('X-NewDebugBar-Profile');
        $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
        $views = collect($profile['sections']['views']['payload']['items']);

        // Laravel itself renders Renderable view data once when gathering template data.
        expect($compositions)->toBe($request)
            ->and($views->where('name', 'context'))->toHaveCount(1)
            ->and($views->firstWhere('name', 'original-response')['data'])->toMatchArray([
                'label' => 'Original response',
                'preview' => '['.View::class.']',
            ]);
    }
});
