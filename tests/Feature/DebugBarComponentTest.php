<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;

it('loads only the selected profile section after the inspector asks', function () {
    $this->get('/profiled', ['Accept' => 'text/html'])->assertOk();

    $file = File::files(config('newdebugbar.storage.path'))[0];
    $profile = json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);

    $component = Livewire::test(DebugBar::class, ['profileId' => $profile['id']])
        ->assertSet('sectionLoaded', false)
        ->assertDontSeeHtml('data-ndb-section-panel="request"')
        ->call('loadSection', 'request')
        ->assertSet('sectionLoaded', true)
        ->assertSet('selectedSection', 'request')
        ->assertDispatched('newdebugbar-section-loaded', section: 'request')
        ->assertDispatched('newdebugbar-content-updated')
        ->assertSeeHtml('data-ndb-section-panel="request"')
        ->assertDontSeeHtml('data-ndb-section-panel="queries"')
        ->assertSeeHtml('data-ndb-request-trace');
});

it('presents captured request and response evidence in their lifecycle stages', function (int $size, int $status, string $statusLabel) {
    $id = (string) Str::uuid();
    $middleware = ['App\\Http\\Middleware\\Authenticate', 'Illuminate\\Routing\\Middleware\\SubstituteBindings'];

    app(ProfileStore::class)->put([
        'id' => $id,
        'profile_type' => 'http',
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 12.5, 'peak_memory_mb' => 8],
        'sections' => [
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'POST', 'status' => $status],
                'payload' => [
                    'method' => 'POST',
                    'status' => $status,
                    'url' => 'https://example.test/trips/kyoto?season=autumn',
                    'path' => '/trips/kyoto',
                    'route' => 'trips.show',
                    'action' => 'App\\Http\\Controllers\\TripWorkspaceController@show',
                    'middleware' => $middleware,
                    'headers' => ['content-type' => ['application/x-www-form-urlencoded']],
                    'content_type' => 'application/json; charset=utf-8',
                    'request_size_bytes' => $size,
                    'response_size_bytes' => 4096,
                    'query' => ['season' => 'autumn'],
                ],
            ],
        ],
    ]);

    $component = Livewire::test(DebugBar::class, ['profileId' => $id])->call('loadSection', 'request');
    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $xpath = new DOMXPath($document);
    $text = static fn (string $expression): string => trim(preg_replace('/\s+/', ' ', (string) $xpath->evaluate('string('.$expression.')')));

    expect($xpath->query('//*[@data-ndb-request-step]')->length)->toBe(3)
        ->and($text('//*[@data-ndb-request-path]'))->toBe('/trips/kyoto')
        ->and($text('//*[@data-ndb-request-step="responded"]//*[@data-ndb-request-status]'))->toBe($statusLabel)
        ->and($xpath->query('//*[@data-ndb-request-step="received"]//*[@data-ndb-request-size]')->length)->toBe($size > 0 ? 1 : 0)
        ->and($text('//*[@data-ndb-request-step="responded"]'))->toContain('application/json; charset=utf-8')
        ->and($text('//*[@data-ndb-request-step="received"]'))->not->toContain('application/json; charset=utf-8')
        ->and($text('//*[@data-ndb-request-detail-panel="headers"]'))->toContain('application/x-www-form-urlencoded')
        ->and($text('//*[@data-ndb-request-middleware]'))->toContain(...$middleware)
        ->and($xpath->query('//*[@data-ndb-request-middleware and @open]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-request-details and @open]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-request-copy]')->length)->toBe(1);
})->with([
    'empty successful request' => [0, 200, '200 OK'],
    'populated validation response' => [2048, 422, '422 Unprocessable Content'],
    'populated redirect response' => [321, 302, '302 Found'],
    'custom response status' => [0, 599, '599'],
]);

it('keeps a data-heavy profile out of the initial Livewire shell', function () {
    $id = (string) Str::uuid();
    $privateValue = 'heavy-view-private-'.Str::random(24);
    $views = array_map(
        fn (int $index): array => [
            'name' => 'reports.row-'.$index,
            'path' => '/views/reports/row-'.$index.'.blade.php',
            'duration_ms' => 1.5,
            'data' => [
                'private_value' => $privateValue,
                'payload' => str_repeat('x', 20_000),
            ],
        ],
        range(1, 50),
    );

    app(ProfileStore::class)->put([
        'id' => $id,
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 100, 'peak_memory_mb' => 12],
        'sections' => [
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'GET', 'status' => 200],
                'payload' => ['path' => '/heavy', 'method' => 'GET', 'status' => 200],
            ],
            'views' => [
                'label' => 'Views',
                'summary' => ['count' => count($views)],
                'payload' => ['items' => $views],
            ],
            'exceptions' => ['label' => 'Exceptions', 'summary' => ['count' => 0], 'payload' => ['items' => []]],
        ],
    ]);

    $component = Livewire::test(DebugBar::class, ['profileId' => $id]);

    expect(strlen($component->html()))
        ->toBeLessThan(250_000)
        ->and($component->html())->not->toContain($privateValue);
});

it('locks server-owned profile state', function () {
    $this->get('/profiled', ['Accept' => 'text/html'])->assertOk();

    $file = File::files(config('newdebugbar.storage.path'))[0];
    $profile = json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);

    expect(fn () => Livewire::test(DebugBar::class, ['profileId' => $profile['id']])
        ->set('profileId', 'changed'))
        ->toThrow(Exception::class);

    expect(fn () => Livewire::test(DebugBar::class, ['profileId' => $profile['id']])
        ->set('summary.status', 500))
        ->toThrow(Exception::class);

    expect(fn () => Livewire::test(DebugBar::class, ['profileId' => $profile['id']])
        ->set('sectionLoaded', true))
        ->toThrow(Exception::class);

    expect(fn () => Livewire::test(DebugBar::class, ['profileId' => $profile['id']])
        ->set('selectedSection', 'queries'))
        ->toThrow(Exception::class);
});

it('returns not found when deferred profile details have expired', function () {
    Livewire::test(DebugBar::class, ['profileId' => '00000000-0000-4000-8000-000000000000'])
        ->call('loadSection', 'request')
        ->assertNotFound();
});

it('keeps captured overview diagnostics out of the inspector UI', function () {
    $id = $this->get('/profiled', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->assertSet('selectedSection', 'request')
        ->assertSet('summary.sections', fn (array $sections): bool => collect($sections)->doesntContain('key', 'overview'))
        ->call('loadSection', 'overview')
        ->assertStatus(422);
});

it('renders retained exception causes with a truthful current profile action', function (string $profileType, string $actionLabel) {
    $id = (string) Str::uuid();
    $runtime = $profileType === 'http' ? 'full_page' : $profileType;

    app(ProfileStore::class)->put([
        'id' => $id,
        'profile_type' => $profileType,
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 12.5, 'peak_memory_mb' => 8],
        'sections' => [
            'request' => [
                'label' => $profileType === 'http' ? 'Request' : 'Runtime',
                'summary' => ['method' => $profileType === 'http' ? 'GET' : 'CLI', 'status' => 200],
                'payload' => ['path' => '/exceptions', 'runtime_type' => $runtime],
            ],
            'exceptions' => [
                'label' => 'Exceptions',
                'summary' => ['count' => 1],
                'payload' => ['items' => [[
                    'class' => RuntimeException::class,
                    'message' => 'Top-level failure.',
                    'file' => 'app/Actions/Run.php',
                    'line' => 42,
                    'frames' => ['application' => [], 'vendor' => []],
                    'source' => null,
                    'causes' => [[
                        'class' => LogicException::class,
                        'message' => 'Underlying failure.',
                        'file' => 'app/Services/Dependency.php',
                        'line' => 17,
                        'frames' => ['application' => [], 'vendor' => []],
                        'source' => null,
                    ]],
                    'chain_truncated' => true,
                ]]],
            ],
        ],
    ]);

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('loadSection', 'exceptions')
        ->assertSeeHtml('data-ndb-exception-context-action')
        ->assertSeeHtml('data-ndb-exception-detail-tab="causes"')
        ->assertSeeHtml('data-ndb-exception-cause="0"')
        ->assertSee($actionLabel)
        ->assertSee('Underlying failure.')
        ->assertSee('More causes exist, but only the first five were retained.');
})->with([
    'HTTP request' => ['http', 'Open request'],
    'queue worker' => ['queue', 'Open worker'],
    'Artisan command' => ['artisan', 'Open command'],
    'test run' => ['test', 'Open test run'],
    'other runtime' => ['runtime', 'Open runtime'],
]);

it('summarizes warnings, slow queries, and duplicate sql', function () {
    $id = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'id' => $id,
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 15.2, 'peak_memory_mb' => 8.5],
        'sections' => [
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'POST', 'status' => 500],
                'payload' => [
                    'method' => 'POST',
                    'status' => 500,
                    'path' => '/organizations',
                    'route' => 'organizations.store',
                    'action' => 'OrganizationController@store',
                ],
            ],
            'queries' => [
                'label' => 'Queries',
                'summary' => ['count' => 3, 'duration_ms' => 130.5],
                'payload' => ['items' => [
                    ['sql' => 'select * from users', 'duration_ms' => 120],
                    ['sql' => "select  *  from\nusers", 'duration_ms' => 5],
                    ['sql' => 'select * from clinics', 'duration_ms' => 5.5],
                ]],
            ],
            'exceptions' => [
                'label' => 'Exceptions',
                'summary' => ['count' => 1],
                'payload' => ['items' => []],
            ],
        ],
    ]);

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->assertSet('summary.id', $id)
        ->assertSet('summary.environment', 'testing')
        ->assertSet('summary.method', 'POST')
        ->assertSet('summary.path', '/organizations')
        ->assertSet('summary.status', 500)
        ->assertSet('summary.warning', true)
        ->assertSet('summary.peak_memory_mb', 8.5)
        ->assertSet('summary.duration_label', '15.2 ms')
        ->assertSet('summary.query_time_ms', 130.5)
        ->assertSet('summary.query_time_label', '130.5 ms')
        ->assertSet('summary.slow_query_count', 1)
        ->assertSet('summary.repeated_pattern_count', 1)
        ->assertSet('summary.exception_count', 1)
        ->assertSet('sectionLoaded', false)
        ->assertDontSeeHtml('data-ndb-section-panel="exceptions"')
        ->call('loadSection', 'exceptions')
        ->assertSet('sectionLoaded', true)
        ->assertSet('profile.findings.0.summary', 'The request returned HTTP 500.');
});

it('marks active, quiet, truncated, and incomplete sections for disclosure', function () {
    $id = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'id' => $id,
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 15.2, 'peak_memory_mb' => 8.5],
        'sections' => [
            'overview' => ['label' => 'Overview', 'summary' => [], 'payload' => []],
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'GET', 'status' => 200],
                'payload' => [
                    'method' => 'GET',
                    'status' => 200,
                    'path' => '/organizations',
                    'route' => null,
                    'action' => null,
                ],
            ],
            'queries' => [
                'label' => 'Queries',
                'summary' => ['count' => 0, 'duration_ms' => 0],
                'payload' => ['items' => []],
            ],
            'views' => [
                'label' => 'Views',
                'summary' => ['count' => 2, 'retained_count' => 0, 'dropped_count' => 2],
                'payload' => ['items' => []],
            ],
            'logs' => [
                'label' => 'Logs',
                'summary' => ['count' => 0],
                'payload' => ['items' => []],
            ],
            'exceptions' => [
                'label' => 'Exceptions',
                'summary' => ['count' => 0],
                'payload' => ['items' => []],
            ],
        ],
    ]);

    $component = Livewire::test(DebugBar::class, ['profileId' => $id])
        ->assertSet('summary.warning', true)
        ->assertSet('summary.sections', function (array $sections): bool {
            $sections = collect($sections)->keyBy('key');

            return $sections->every(fn (array $section): bool => filled($section['description'] ?? null))
                && ! isset($sections['overview'])
                && $sections['request']['active'] === true
                && $sections['queries']['active'] === false
                && $sections['logs']['active'] === false
                && $sections['exceptions']['active'] === false
                && $sections['views']['active'] === true
                && $sections['views']['attention'] === true
                && $sections['views']['truncated'] === true
                && $sections['views']['finding_count'] === 1
                && $sections['timeline']['active'] === true
                && $sections['timeline']['attention'] === true
                && $sections['timeline']['incomplete'] === true;
        })
        ->call('loadSection', 'views')
        ->assertDontSeeHtml('data-ndb-findings')
        ->assertSeeHtml('data-ndb-collection-status="views"')
        ->assertSee('Showing 0 of 2 views.')
        ->call('loadSection', 'timeline')
        ->assertSeeHtml('data-ndb-timeline-incomplete');

    expect(preg_replace('/\s+/', ' ', $component->html()))
        ->toContain('Timeline incomplete: 2 source events were omitted.');
});

it('marks secondary query transaction omissions as truncated', function () {
    $id = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'id' => $id,
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 10, 'peak_memory_mb' => 8],
        'sections' => [
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'GET', 'status' => 200],
                'payload' => ['method' => 'GET', 'status' => 200, 'path' => '/', 'route' => null, 'action' => null],
            ],
            'queries' => [
                'label' => 'Queries',
                'summary' => [
                    'count' => 0,
                    'duration_ms' => 0,
                    'transaction_count' => 3,
                    'transaction_retained_count' => 1,
                    'transaction_dropped_count' => 2,
                    'truncated' => true,
                ],
                'payload' => [
                    'items' => [],
                    'transactions' => [['kind' => 'begin']],
                ],
            ],
            'exceptions' => ['label' => 'Exceptions', 'summary' => ['count' => 0], 'payload' => ['items' => []]],
        ],
    ]);

    $component = Livewire::test(DebugBar::class, ['profileId' => $id])
        ->assertSet('summary.sections', function (array $sections): bool {
            $queries = collect($sections)->firstWhere('key', 'queries');

            return $queries['active'] === true
                && $queries['attention'] === true
                && $queries['truncated'] === true;
        })
        ->call('loadSection', 'queries')
        ->assertSeeHtml('data-ndb-collection-status="query-transactions"');

    expect(preg_replace('/\s+/', ' ', $component->html()))
        ->toContain('Showing 1 of 3 query transaction events.');
});

it('uses the shared presenter for deferred query details and findings', function () {
    $id = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'id' => $id,
        'metrics' => ['duration_ms' => 100],
        'sections' => [
            'request' => ['label' => 'Request', 'summary' => ['method' => 'GET', 'status' => 200], 'payload' => [
                'method' => 'GET',
                'status' => 200,
                'path' => '/',
                'route' => null,
                'action' => null,
            ]],
            'queries' => ['label' => 'Queries', 'summary' => ['count' => 2, 'duration_ms' => 10], 'payload' => ['items' => [
                ['sql' => 'select ?', 'bindings' => [1], 'duration_ms' => 5, 'connection' => 'testing'],
                ['sql' => 'select ?', 'bindings' => [2], 'duration_ms' => 5, 'connection' => 'testing'],
            ]]],
            'exceptions' => ['label' => 'Exceptions', 'summary' => ['count' => 0], 'payload' => ['items' => []]],
        ],
    ]);

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('loadSection', 'queries')
        ->assertSet('profile.sections.queries.summary.repeated_pattern_count', 1)
        ->assertSet('profile.sections.queries.payload.items.0.repeated_count', 2)
        ->assertSet('profile.findings.0.rule_id', 'query.repeated')
        ->assertSeeHtml('data-ndb-query-group')
        ->assertSeeHtml('data-ndb-query-item');
});

it('paginates long timelines in deterministic batches', function () {
    $id = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'id' => $id,
        'metrics' => ['duration_ms' => 121],
        'sections' => [
            'request' => ['label' => 'Request', 'summary' => ['method' => 'GET', 'status' => 200], 'payload' => [
                'method' => 'GET',
                'status' => 200,
                'path' => '/long-timeline',
                'route' => null,
                'action' => null,
            ]],
            'logs' => ['label' => 'Logs', 'summary' => ['count' => 120], 'payload' => ['items' => array_map(
                fn (int $index): array => [
                    'level' => 'info',
                    'message' => 'Timeline event '.$index,
                    'at_ms' => (float) $index,
                ],
                range(1, 120),
            )]],
            'exceptions' => ['label' => 'Exceptions', 'summary' => ['count' => 0], 'payload' => ['items' => []]],
        ],
    ]);

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('loadSection', 'timeline')
        ->assertSet('timelineLimit', 50)
        ->assertSet('profile.sections.timeline.payload.items', fn (array $items): bool => count($items) === 50)
        ->assertSet('profile.sections.timeline.payload.total_item_count', 122)
        ->assertSet('profile.sections.timeline.payload.has_more', true)
        ->assertSee('Showing 50 of 122 timeline')
        ->assertSee('More activity loads as you scroll.')
        ->assertSeeHtml('data-ndb-timeline-page-sentinel')
        ->assertDontSeeHtml('data-ndb-timeline-load-more')
        ->call('loadMoreTimeline')
        ->assertSet('timelineLimit', 100)
        ->assertSet('profile.sections.timeline.payload.items', fn (array $items): bool => count($items) === 100)
        ->call('loadMoreTimeline')
        ->assertSet('timelineLimit', 122)
        ->assertSet('profile.sections.timeline.payload.items', fn (array $items): bool => count($items) === 122)
        ->assertSet('profile.sections.timeline.payload.has_more', false)
        ->assertDontSeeHtml('data-ndb-timeline-page-sentinel')
        ->assertSee('All 122 timeline events are loaded.');
});

it('keeps view data out of section html until its exact render asks', function () {
    $profileId = $this->get('/profiled-views', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');

    $component = Livewire::test(DebugBar::class, ['profileId' => $profileId])
        ->call('loadSection', 'views')
        ->assertSet('profile.sections.views.payload.groups.0.items.0', fn (array $view): bool => ! array_key_exists('data', $view))
        ->assertSeeHtml('data-ndb-view-data-panel')
        ->assertSeeHtml('data-ndb-view-data-loading')
        ->assertDontSee('view-data-value');

    $data = $component->instance()->loadViewData(
        1,
        app(ProfileStore::class),
        app(ProfilePresenter::class),
    );

    expect($data)
        ->label->toBe('Context view')
        ->private_value->toBe('view-data-value');

    $component->call('loadViewData', 1);

    expect($component->effects)->not->toHaveKey('html');
});

it('switches to an exact foreground application profile', function () {
    $firstId = $this->get('/profiled', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $nextId = $this->get('/profiled-next', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');

    Livewire::test(DebugBar::class, ['profileId' => $firstId])
        ->call('loadSection', 'request')
        ->assertSet('sectionLoaded', true)
        ->call('switchProfile', $nextId)
        ->assertSet('profileId', $nextId)
        ->assertSet('summary.path', '/profiled-next')
        ->assertSet('sectionLoaded', false)
        ->assertSet('selectedSection', 'request')
        ->assertDispatched('newdebugbar-profile-switched');
});

it('announces later requests without changing the selected profile', function () {
    $firstId = $this->get('/profiled', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $nextId = $this->get('/profiled-next', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');

    Livewire::test(DebugBar::class, ['profileId' => $firstId])
        ->assertSet('profileLimit', 20)
        ->assertSeeHtml('data-ndb-request-picker-trigger="toolbar"')
        ->assertSeeHtml('data-ndb-request-picker-trigger="header-mobile"')
        ->assertSeeHtml('data-ndb-request-picker-trigger="header"')
        ->assertSeeHtml('aria-haspopup="listbox"')
        ->assertSet('summary.sections', fn (array $sections): bool => collect($sections)->firstWhere('key', 'request')['label'] === 'Requests')
        ->call('noticeProfile', $nextId)
        ->assertSet('profileId', $firstId)
        ->assertDispatched('newdebugbar-profile-noticed', function (string $name, array $params) use ($nextId): bool {
            return $name === 'newdebugbar-profile-noticed'
                && $params['summary']['id'] === $nextId
                && $params['summary']['path'] === '/profiled-next';
        });
});

it('refreshes bounded background activity and announces completed worker profiles', function () {
    $originId = $this->get('/profiled-queued-communications', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $store = app(ProfileStore::class);
    $origin = $store->get($originId);
    $presented = app(ProfilePresenter::class)->present($origin);
    $correlationKeys = collect($presented['background_activity']['items'])->pluck('key')->all();
    $workerId = (string) Str::uuid();
    $worker = $origin;
    $worker['id'] = $workerId;
    $worker['profile_type'] = 'queue';
    $worker['sections']['request']['label'] = 'Runtime';
    $worker['sections']['request']['summary'] = ['method' => 'CLI', 'status' => 0, 'exit_code' => 0];
    $worker['sections']['request']['payload'] = [
        'path' => 'queue:SendQueuedMailable',
        'runtime_type' => 'queue',
        'name' => 'SendQueuedMailable',
        'context' => ['correlation_key' => $correlationKeys[0], 'origin_profile_id' => $originId],
    ];
    $store->put($worker);

    $component = Livewire::test(DebugBar::class, ['profileId' => $originId])
        ->assertSet('summary.background_pending', true)
        ->call('loadSection', 'queue');

    app(BackgroundActivityStore::class)->recordOutcome($correlationKeys[0], 'sent', $workerId, 1);
    app(BackgroundActivityStore::class)->recordOutcome($correlationKeys[1], 'failed', $workerId, 1, RuntimeException::class);

    $component
        ->call('refreshRelatedActivity')
        ->assertSet('summary.background_pending', false)
        ->assertSet('summary.related_profile_ids', [$workerId])
        ->assertDispatched('newdebugbar-profile-refreshed', function (string $name, array $params) use ($workerId): bool {
            return $name === 'newdebugbar-profile-refreshed'
                && $params['summary']['background_pending'] === false
                && $params['relatedProfiles'][0]['id'] === $workerId
                && $params['relatedProfiles'][0]['request_type'] === 'queue';
        })
        ->assertNotDispatched('newdebugbar-content-updated');

    expect($component->effects)->not->toHaveKey('html');
});

it('returns the selected section when an activity refresh shares its message', function (string $path, string $section, bool $refreshFirst) {
    $profileId = $this->get($path, ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $metadata = ['island' => ['name' => 'section-details', 'mode' => 'morph']];
    $load = ['method' => 'loadSection', 'params' => [$section], 'metadata' => $metadata];
    $refresh = ['method' => 'refreshRelatedActivity', 'params' => [], 'metadata' => $metadata];

    $component = Livewire::test(DebugBar::class, ['profileId' => $profileId])
        ->update(calls: $refreshFirst ? [$refresh, $load] : [$load, $refresh])
        ->assertSet('selectedSection', $section)
        ->assertSet('sectionLoaded', true)
        ->assertDispatched('newdebugbar-section-loaded', section: $section)
        ->assertDispatched('newdebugbar-profile-refreshed');

    expect($component->effects)->not->toHaveKey('html')
        ->and($component->effects['islandFragments'] ?? [])->toHaveCount(1);

    $document = new DOMDocument;
    $document->loadHTML($component->effects['islandFragments'][0], LIBXML_NOERROR | LIBXML_NOWARNING);
    $fragment = new DOMXPath($document);

    expect($fragment->query('//*[@data-ndb-section-panel]')->length)->toBe(1)
        ->and($fragment->evaluate('string(//*[@data-ndb-section-panel]/@data-ndb-section-panel)'))->toBe($section)
        ->and($fragment->query('//*[@id="newdebugbar"]')->length)->toBe(0);

    $component->update(calls: [$refresh])
        ->assertSet('selectedSection', $section)
        ->assertSet('sectionLoaded', true)
        ->assertDispatched('newdebugbar-profile-refreshed')
        ->assertNotDispatched('newdebugbar-section-loaded');

    expect($component->effects)->not->toHaveKey('html')
        ->not->toHaveKey('islandFragments');
})->with([
    'request' => ['/profiled', 'request'],
    'queue' => ['/profiled-queue-attempts', 'queue'],
    'redis' => ['/profiled-redis', 'redis'],
])->with([
    'refresh first' => true,
    'refresh last' => false,
]);

it('returns the next timeline page when an activity refresh shares its message', function (bool $refreshFirst) {
    $profileId = $this->get('/profiled-timeline-long', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $metadata = ['island' => ['name' => 'section-details', 'mode' => 'morph']];
    $load = ['method' => 'loadMoreTimeline', 'params' => [], 'metadata' => $metadata];
    $refresh = ['method' => 'refreshRelatedActivity', 'params' => [], 'metadata' => $metadata];
    $component = Livewire::test(DebugBar::class, ['profileId' => $profileId])
        ->update(calls: [['method' => 'loadSection', 'params' => ['timeline'], 'metadata' => $metadata]])
        ->assertSet('timelineLimit', 50);

    $component->update(calls: $refreshFirst ? [$refresh, $load] : [$load, $refresh])
        ->assertSet('selectedSection', 'timeline')
        ->assertSet('timelineLimit', 100)
        ->assertDispatched('newdebugbar-section-loaded', section: 'timeline')
        ->assertDispatched('newdebugbar-profile-refreshed');

    expect($component->effects)->not->toHaveKey('html')
        ->and($component->effects['islandFragments'] ?? [])->toHaveCount(1);

    $document = new DOMDocument;
    $document->loadHTML($component->effects['islandFragments'][0], LIBXML_NOERROR | LIBXML_NOWARNING);
    $fragment = new DOMXPath($document);

    expect($fragment->query('//*[@data-ndb-timeline-item]')->length)->toBe(100)
        ->and($fragment->query('//*[@data-ndb-section-panel]')->length)->toBe(1)
        ->and($fragment->evaluate('string(//*[@data-ndb-section-panel]/@data-ndb-section-panel)'))->toBe('timeline');
})->with([
    'refresh first' => true,
    'refresh last' => false,
]);

it('rejects unavailable request summaries', function () {
    $id = $this->get('/profiled', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('noticeProfile', 'not-a-profile')
        ->assertStatus(422);

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('noticeProfile', '00000000-0000-4000-8000-000000000000')
        ->assertNotFound();
});
