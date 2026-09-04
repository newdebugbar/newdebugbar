<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Mcp\Tools\GetDebugProfileSection;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\ProfileSanitizer;
use NewDebugBar\Support\ProfileSizeLimiter;
use NewDebugBar\Support\Redactor;
use NewDebugBar\Support\RuntimeProfiler;
use NewDebugBar\Tests\Support\McpResponse;

uses(ConfiguresProfileProtection::class);

it('keeps unrelated profile evidence readable when complete records are redacted', function () {
    $profile = [
        'id' => (string) Str::uuid(),
        'sections' => [
            'queries' => ['summary' => ['count' => 1], 'payload' => ['items' => [['sql' => 'QUERY-RECORD-SENTINEL']]]],
            'http_client' => ['summary' => ['count' => 1], 'payload' => ['items' => [['url' => 'https://example.test/HTTP-RECORD-SENTINEL']]]],
            'request' => ['summary' => [], 'payload' => ['path' => '/ordinary']],
        ],
    ];
    $sanitizer = new ProfileSanitizer(new Redactor(maskedPaths: ['queries.items.*', 'http_client.items.*']));
    $store = new ProfileStore(app('files'), config('newdebugbar.storage.path'), sanitizer: $sanitizer);
    $store->put($profile);
    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profile['id'], 'path' => '/sections/request/payload/path',
    ])->assertOk());
    expect($content['data']['value'])->toBe('/ordinary')
        ->and($store->get($profile['id'])['sections']['queries']['payload']['items'])->toBe([['redacted' => true]])
        ->and(File::get(config('newdebugbar.storage.path').'/'.$profile['id'].'.json'))
        ->not->toContain('QUERY-RECORD-SENTINEL', 'HTTP-RECORD-SENTINEL');
});

/** Uses real provider wiring for the application's additional redaction paths. */
trait ConfiguresProfileProtection
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('newdebugbar.redact', [
            'request.query.patient',
            'private_note',
            'background_activity.channels.*',
        ]);
    }
}

it('redacts real HTTP checkpoint and after-response files before MCP can read them', function () {
    Route::get('/profile-protection/{token}', function () {
        Log::info('captured', ['private_note' => 'HTTP-SECRET', 'apiKey' => 'CAMEL-SECRET', 'token_count' => 7]);
        app()->terminating(fn () => Log::info('later', ['private_note' => 'LATE-SECRET']));

        return response('<!doctype html><html><body>Protected request</body></html>');
    });
    $kernel = app(Kernel::class);
    $request = Request::create('/profile-protection/PATH-SECRET?patient=QUERY-SECRET&sort=name', 'GET');
    $response = $kernel->handle($request);
    $id = $response->headers->get('X-NewDebugBar-Profile');
    expect($id)->toBeString();
    $filename = config('newdebugbar.storage.path').'/'.$id.'.json';

    foreach ([false, true] as $terminate) {
        if ($terminate) {
            $kernel->terminate($request, $response);
        }
        $json = File::get($filename);
        expect($json)->not->toContain('HTTP-SECRET', 'CAMEL-SECRET', 'QUERY-SECRET', 'PATH-SECRET', 'LATE-SECRET');
        $profile = app(ProfileStore::class)->get($id);
        expect($profile['sections']['request']['payload']['query'])->toBe(['patient' => '[redacted]', 'sort' => 'name'])
            ->and($profile['sections']['request']['payload']['input']['patient'])->toBe('[redacted]')
            ->and($profile['sections']['request']['payload']['path'])->toBe('[redacted]');
    }

    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $id, 'path' => '/sections/request/payload/query/patient',
    ])->assertOk());
    expect($content['data']['value'])->toBe('[redacted]')
        ->and($profile['completion_state'])->toBe('complete')
        ->and($profile['sections']['logs']['payload']['items'][0]['context']['token_count'])->toBe(7);
});

it('applies the same final storage redaction to runtime profiles and correlation files', function () {
    $runtime = app(RuntimeProfiler::class);
    expect($runtime->start('artisan', 'example:run', ['private_note' => 'RUNTIME-SECRET']))->toBeTrue();
    app(ProfileManager::class)->record('logs', ['message' => 'normal message', 'context' => ['privateKey' => 'RUNTIME-KEY']]);
    $id = $runtime->finish();
    expect($id)->toBeString();
    $json = File::get(config('newdebugbar.storage.path').'/'.$id.'.json');
    expect($json)->not->toContain('RUNTIME-SECRET', 'RUNTIME-KEY')
        ->and($json)->toContain('normal message');

    $background = app(BackgroundActivityStore::class);
    $activity = $background->recordDispatch([
        'origin_profile_id' => $id, 'connection' => 'database', 'queue' => 'default',
        'job_id' => 'ordinary-job', 'job' => 'ExampleJob', 'channels' => ['CHANNEL-SECRET'],
    ]);
    $raw = File::get(config('newdebugbar.storage.path').'/background/'.$activity['key'].'.json');
    expect($raw)->not->toContain('CHANNEL-SECRET')
        ->and($background->get($activity['key'])['channels'])->toBe(['[redacted]'])
        ->and($background->get($activity['key'])['job_id'])->toBe('ordinary-job');
});

it('does not let any stored collector bypass configured rules', function () {
    $id = (string) Str::uuid();
    $sections = [];
    foreach (['overview', 'request', 'queries', 'http_client', 'queue', 'mail', 'notifications', 'redis', 'models', 'cache', 'views', 'events', 'authorization', 'validation', 'logs', 'exceptions', 'livewire'] as $section) {
        $sections[$section] = ['summary' => [], 'payload' => ['evidence' => ['private_note' => 'SECTION-SECRET-'.$section, 'safe' => 'keep me']]];
    }
    app(ProfileStore::class)->put(['id' => $id, 'sections' => $sections]);
    expect(File::get(config('newdebugbar.storage.path').'/'.$id.'.json'))->not->toContain('SECTION-SECRET');
    foreach (array_keys($sections) as $section) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $id, 'path' => '/sections/'.$section.'/payload/evidence/private_note',
        ])->assertOk());
        expect($content['data']['value'])->toBe('[redacted]');
    }
});

it('removes redacted mail copies and exposes the exact omission reasons through MCP', function () {
    $id = $this->get('/profiled-messages')->assertOk()->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($id);
    $id = $profile['id'] = (string) Str::uuid();
    $sanitizer = new ProfileSanitizer(new Redactor(maskedPaths: ['mail.items.*.preview.to', 'mail.items.*.preview.attachments.*.body_base64']));
    $store = new ProfileStore(app('files'), config('newdebugbar.storage.path'), sanitizer: $sanitizer);
    $store->put($profile);

    foreach ([
        '/sections/mail/payload/items/0/preview/eml' => null,
        '/sections/mail/payload/items/0/preview/eml_omitted_reason' => 'redacted_fields',
        '/sections/mail/payload/items/0/preview/attachments/0/body_base64' => null,
        '/sections/mail/payload/items/0/preview/attachments/0/body_omitted_reason' => 'redacted',
    ] as $path => $expected) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $id, 'path' => $path,
        ])->assertOk());
        expect($content['data']['value'])->toBe($expected);
    }
    $this->get('/__newdebugbar/mail/'.$id.'/0/eml')->assertNotFound();
    $this->get('/__newdebugbar/mail/'.$id.'/0/attachment/0')->assertNotFound();
    expect(File::get(config('newdebugbar.storage.path').'/'.$id.'.json'))
        ->not->toContain('private-recipient@example.test', base64_encode('private attachment'));
});

it('enforces actual stored profile bytes and makes omitted data discoverable through MCP', function () {
    $id = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'id' => $id,
        'sections' => ['mail' => ['summary' => ['count' => 1], 'payload' => ['items' => [[
            'subject' => 'Large message',
            'preview' => ['text' => 'Retained text', 'eml' => str_repeat('large body', 1_100_000)],
        ]]]]],
    ]);
    $raw = File::get(config('newdebugbar.storage.path').'/'.$id.'.json');
    expect(strlen($raw))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES);
    $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($id));
    expect($profile['storage']['truncated'])->toBeTrue()
        ->and(array_column($profile['findings'], 'rule_id'))->toContain('profile.truncated');

    foreach (['/storage/max_bytes' => ProfileSizeLimiter::MAX_BYTES, '/sections/mail/payload/items/0/preview/eml_omitted_reason' => 'profile_budget'] as $path => $expected) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $id, 'path' => $path,
        ])->assertOk());
        expect($content['data']['value'])->toBe($expected);
    }
});

it('exposes matching retained counts and capture totals in stored profiles and bounded MCP responses', function (int $dropped) {
    $id = (string) Str::uuid();
    $rows = array_map(fn (int $index): array => [
        'message' => 'Stored log '.$index,
        'level' => 'info',
        'context' => ['details' => str_repeat('é', 2_000)],
    ], range(0, 999));
    app(ProfileStore::class)->put([
        'id' => $id,
        'sections' => ['logs' => [
            'label' => 'Logs',
            'summary' => ['count' => 1_000 + $dropped, 'retained_count' => 1_000, 'dropped_count' => $dropped],
            'payload' => ['items' => $rows],
        ]],
    ]);
    $stored = app(ProfileStore::class)->get($id);
    $retained = count($stored['sections']['logs']['payload']['items']);
    $summary = [
        'count' => 1_000 + $dropped, 'retained_count' => $retained, 'dropped_count' => $dropped,
        'storage_omitted_items' => 1_000 - $retained,
    ];
    expect($retained)->toBeGreaterThan(0)->toBeLessThan(1_000)
        ->and($stored['sections']['logs']['summary'])->toMatchArray($summary);

    $focused = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $id, 'section' => 'logs', 'limit' => 1,
    ])->assertOk());
    expect($focused['data']['summary'])->toMatchArray($summary)
        ->and($focused['data']['pagination']['total'])->toBe($retained);

    foreach ($summary as $key => $expected) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $id, 'path' => '/sections/logs/summary/'.$key,
        ])->assertOk());
        expect($content['data']['value'])->toBe($expected);
    }

    $presented = app(ProfilePresenter::class)->present($stored);
    $findings = array_filter($presented['findings'], fn (array $finding): bool => $finding['rule_id'] === 'collector.truncated');
    expect($findings)->toHaveCount($dropped > 0 ? 1 : 0);

    foreach ($findings as $index => $finding) {
        expect($finding['evidence'])->toBe(['collector' => 'logs', 'retained' => $retained, 'total' => 1_000 + $dropped, 'dropped' => $dropped]);
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $id, 'path' => '/findings/'.$index.'/evidence/retained',
        ])->assertOk());
        expect($content['data']['value'])->toBe($retained);
    }
})->with(['storage only' => 0, 'capture and storage' => 100]);
