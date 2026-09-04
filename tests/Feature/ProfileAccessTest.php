<?php

use Illuminate\Http\Request;
use Livewire\Livewire;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Mcp\Tools\GetDebugFindings;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Mcp\Tools\GetDebugProfileSection;
use NewDebugBar\Mcp\Tools\InspectDebugQueries;
use NewDebugBar\Mcp\Tools\ListDebugProfiles;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\ProfileAccess;

/** Provides a mutable decision to verify access is checked on every request. */
final class ProfileAccessTestCallback
{
    public static bool $allowed = true;

    public static int $calls = 0;

    public function __invoke(Request $request): bool
    {
        self::$calls++;

        return self::$allowed;
    }
}

beforeEach(function () {
    ProfileAccessTestCallback::$allowed = true;
    ProfileAccessTestCallback::$calls = 0;
});

it('allows saved profile reads by default and keeps mail responses private', function () {
    $id = $this->get('/profiled-mail-rich')->assertOk()->headers->get('X-NewDebugBar-Profile');

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('loadSection', 'request')
        ->assertSet('sectionLoaded', true);

    foreach (['html', 'text', 'eml', 'attachment/0'] as $format) {
        $response = $this->get('/__newdebugbar/mail/'.$id.'/0/'.$format)->assertOk();

        expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    }
});

it('denies exact saved profile and mail identifiers when the HTTP callback refuses access', function () {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    config()->set('newdebugbar.access', ProfileAccessTestCallback::class);
    ProfileAccessTestCallback::$allowed = false;

    Livewire::test(DebugBar::class, ['profileId' => $id])->assertNotFound();

    foreach (['html', 'text', 'eml', 'attachment/0'] as $format) {
        $response = $this->get('/__newdebugbar/mail/'.$id.'/0/'.$format)->assertNotFound();

        expect($response->getContent())->not->toContain('private body', 'private attachment');
    }

    expect(app(ProfileStore::class)->get($id))->toBeArray();
});

it('prevents caching profile data responses without changing host response caching', function () {
    $hostResponse = $this->get('/profiled-messages')->assertOk();
    $id = $hostResponse->headers->get('X-NewDebugBar-Profile');
    expect($hostResponse->headers->get('Cache-Control'))->not->toContain('no-store');

    $component = Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('loadSection', 'request')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
    config()->set('newdebugbar.access', fn (): bool => false);

    $component->call('loadSection', 'request')->assertNotFound()
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/__newdebugbar/mail/'.$id.'/0/text')->assertNotFound()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('checks fresh access before each hydrated profile action', function (string $action, array $arguments) {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    config()->set('newdebugbar.access', ProfileAccessTestCallback::class);
    $component = Livewire::test(DebugBar::class, ['profileId' => $id]);
    $previousCalls = ProfileAccessTestCallback::$calls;
    ProfileAccessTestCallback::$allowed = false;
    $arguments = array_map(fn ($argument) => $argument === 'profile-id' ? $id : $argument, $arguments);

    $component->call($action, ...$arguments)->assertNotFound();

    expect(ProfileAccessTestCallback::$calls)->toBeGreaterThan($previousCalls);
})->with([
    'section' => ['loadSection', ['request']],
    'timeline' => ['loadMoreTimeline', []],
    'view data' => ['loadViewData', [1]],
    'related activity' => ['refreshRelatedActivity', []],
    'query explain' => ['explainQuery', [1]],
    'profile switch' => ['switchProfile', ['profile-id']],
    'profile notice' => ['noticeProfile', ['profile-id']],
    'refresh' => ['$refresh', []],
]);

it('rechecks package state after a profile was mounted', function (string $key, mixed $value) {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    $component = Livewire::test(DebugBar::class, ['profileId' => $id]);
    config()->set($key, $value);

    $component->call('loadSection', 'request')->assertNotFound();
    $this->get('/__newdebugbar/mail/'.$id.'/0/text')->assertNotFound();
    $this->get('/__newdebugbar/mail/'.$id.'/0/attachment/0')->assertNotFound();
})->with([
    'disabled' => ['newdebugbar.enabled', false],
    'different environment' => ['newdebugbar.environments', ['local']],
    'invalid environments' => ['newdebugbar.environments', 'testing'],
]);

it('fails closed for invalid or throwing HTTP access callbacks', function (string $kind) {
    config()->set('newdebugbar.access', match ($kind) {
        'missing class' => 'MissingProfileAccessCallback',
        'not callable' => stdClass::class,
        'invalid value' => ['invalid'],
        'non boolean approval' => fn (Request $request) => 'yes',
        'exception' => fn (Request $request) => throw new RuntimeException('private callback failure'),
    });

    expect(app(ProfileAccess::class)->allows(Request::create('/dashboard')))->toBeFalse();
})->with(['missing class', 'not callable', 'invalid value', 'non boolean approval', 'exception']);

it('passes the current request to a callable access rule', function () {
    config()->set('newdebugbar.access', fn (Request $request): bool => $request->headers->get('X-Debug-Access') === 'allowed');
    $access = app(ProfileAccess::class);

    expect($access->allows(Request::create('/dashboard')))->toBeFalse()
        ->and($access->allows(Request::create('/dashboard', server: ['HTTP_X_DEBUG_ACCESS' => 'allowed'])))->toBeTrue();
});

it('provides session context to mail access rules', function () {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    config()->set('newdebugbar.access', fn (Request $request): bool => $request->hasSession()
        && $request->session()->get('debug_access') === true);

    $this->get('/__newdebugbar/mail/'.$id.'/0/text')->assertNotFound();
    $this->withSession(['debug_access' => true])->get('/__newdebugbar/mail/'.$id.'/0/text')->assertOk();
    $this->withSession(['debug_access' => false])->get('/__newdebugbar/mail/'.$id.'/0/attachment/0')->assertNotFound();
});

it('does not apply HTTP access callbacks to local MCP reads', function () {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    config()->set('newdebugbar.access', fn () => throw new RuntimeException('HTTP callback must not run'));

    NewDebugBarServer::tool(ListDebugProfiles::class)->assertOk();
    NewDebugBarServer::tool(GetDebugProfileData::class, ['profile_id' => $id])->assertOk();
});

it('returns a visible MCP error when package access changes', function (string $tool, array $arguments) {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    $arguments['profile_id'] = $id;

    foreach ([['newdebugbar.enabled', false], ['newdebugbar.environments', ['local']]] as [$key, $value]) {
        config()->set('newdebugbar.enabled', true);
        config()->set('newdebugbar.environments', ['testing']);
        config()->set($key, $value);

        NewDebugBarServer::tool($tool, $arguments)->assertHasErrors([
            'New Debug Bar is not enabled in this environment.',
        ]);
    }
})->with([
    'list' => [ListDebugProfiles::class, []],
    'data' => [GetDebugProfileData::class, []],
    'section' => [GetDebugProfileSection::class, ['section' => 'request']],
    'findings' => [GetDebugFindings::class, []],
    'queries' => [InspectDebugQueries::class, []],
]);
