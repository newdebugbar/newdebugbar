<?php

use Illuminate\Support\Str;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Mcp\Tools\GetDebugProfileSection;
use NewDebugBar\Presentation\BackgroundActivityPresenter;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Support\McpResponse;

uses(ConfiguresBackgroundRedaction::class);

/** Boots selected file-level rules before creating the stores and event listeners. */
trait ConfiguresBackgroundRedaction
{
    protected array $backgroundRedactionPaths = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('newdebugbar.redact', $this->backgroundRedactionPaths);
    }

    protected function storedBackgroundFixture(): array
    {
        $id = (string) Str::uuid();
        $worker = (string) Str::uuid();
        $activities = app(BackgroundActivityStore::class);
        $facts = [
            'origin_profile_id' => $id,
            'job_id' => 'first-job',
            'job' => 'App\\Jobs\\CorrelatedJob',
            'connection' => 'redis',
            'queue' => 'default',
            'communication_type' => 'notification',
            'communication_class' => 'App\\Notifications\\CorrelatedNotice',
            'channels' => ['private-channel-sentinel', 'public-channel'],
            'notifiable_types' => ['App\\Models\\User', 'private-type-sentinel'],
            'notifiable_count' => 2,
            'recipient_count' => 2,
        ];
        $activity = $activities->recordDispatch($facts);
        $firstAttempt = $activities->recordOutcome($activity['key'], 'waiting', $worker, 1, 'private-exception-sentinel');
        $other = $activities->recordDispatch([...$facts, 'job_id' => 'unrelated-job']);
        $item = [
            ...$facts,
            'correlation_key' => $activity['key'],
            'status' => 'queued',
            'attempts' => $firstAttempt['attempts'],
            'activity_attempt' => 1,
        ];
        $sections = [
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'GET', 'status' => 200],
                'payload' => ['method' => 'GET', 'path' => '/background', 'status' => 200],
            ],
        ];

        foreach (['queue', 'mail', 'notifications'] as $section) {
            $sections[$section] = [
                'label' => ucfirst($section),
                'summary' => ['count' => 2],
                'payload' => ['items' => [$item, [...$item, 'correlation_key' => $other['key'], 'job_id' => 'unrelated-job']]],
            ];
        }

        $sections['logs'] = [
            'label' => 'Logs',
            'summary' => ['count' => 1],
            'payload' => ['items' => [['message' => 'private-channel-sentinel']]],
        ];
        app(ProfileStore::class)->put([
            'id' => $id,
            'profile_type' => 'http',
            'environment' => 'testing',
            'metrics' => ['duration_ms' => 12.5],
            'sections' => $sections,
        ]);
        $activities->recordOutcome($activity['key'], 'completed', $worker, 2);

        return ['id' => $id, 'key' => $activity['key'], 'other_key' => $other['key'], 'worker' => $worker];
    }
}

it('keeps selected profile masks in all correlated presentation and MCP copies', function (string $section) {
    $this->backgroundRedactionPaths = ['sections.'.$section.'.payload.items.0.channels'];
    $this->refreshApplication();
    $fixture = $this->storedBackgroundFixture();
    $stored = app(ProfileStore::class)->get($fixture['id']);
    $activity = app(BackgroundActivityStore::class)->get($fixture['key']);

    expect($stored['sections'][$section]['payload']['items'][0]['channels'])->toBe('[redacted]')
        // This rule intentionally selects the profile file, not the background file.
        ->and($activity['channels'])->toBe(['private-channel-sentinel', 'public-channel']);

    $presented = app(BackgroundActivityPresenter::class)->present($stored);

    foreach (['queue', 'mail', 'notifications'] as $correlatedSection) {
        $item = $presented['sections'][$correlatedSection]['payload']['items'][0];

        expect($item['channels'])->toBe('[redacted]')
            ->and($item['status'])->toBe('completed')
            ->and($item['activity_attempt'])->toBe(2)
            ->and($item['worker_profile_id'])->toBe($fixture['worker'])
            ->and($presented['sections'][$correlatedSection]['payload']['items'][1]['channels'])
            ->toBe(['private-channel-sentinel', 'public-channel']);

        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $fixture['id'],
            'path' => '/sections/'.$correlatedSection.'/payload/items/0/channels',
        ])->assertOk());
        expect($content['data']['value'])->toBe('[redacted]');
    }

    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $fixture['id'],
        'path' => '/background_activity/items/0/channels',
    ])->assertOk());

    expect($content['data']['value'])->toBe('[redacted]')
        ->and($presented['background_activity']['items'][0]['channels'])->toBe('[redacted]')
        ->and($presented['background_activity']['items'][0]['status'])->toBe('completed')
        ->and($presented['sections']['logs']['payload']['items'][0]['message'])->toBe('private-channel-sentinel')
        ->and(app(BackgroundActivityStore::class)->get($fixture['key'])['channels'])->toBe($activity['channels']);
})->with(['queue', 'mail', 'notifications']);

it('combines partial masks from different sections while retaining new worker evidence', function () {
    $this->backgroundRedactionPaths = [
        'queue.items.0.channels.0',
        'mail.items.0.notifiable_types.1',
        'notifications.items.0.attempts.0.exception_class',
    ];
    $this->refreshApplication();
    $fixture = $this->storedBackgroundFixture();
    $presented = app(BackgroundActivityPresenter::class)->present(app(ProfileStore::class)->get($fixture['id']));

    foreach (['queue', 'mail', 'notifications'] as $section) {
        $item = $presented['sections'][$section]['payload']['items'][0];

        expect($item['channels'])->toBe(['[redacted]', 'public-channel'])
            ->and($item['notifiable_types'])->toBe(['App\\Models\\User', '[redacted]'])
            ->and($item['attempts'][0]['exception_class'])->toBe('[redacted]')
            ->and($item['attempts'][1]['status'])->toBe('completed')
            ->and($item['attempts'][1]['attempt'])->toBe(2)
            ->and($item['status'])->toBe('completed');
    }

    $activity = $presented['background_activity']['items'][0];
    expect($activity['channels'])->toBe(['[redacted]', 'public-channel'])
        ->and($activity['notifiable_types'])->toBe(['App\\Models\\User', '[redacted]'])
        ->and($activity['attempts'][0]['exception_class'])->toBe('[redacted]')
        ->and($activity['attempts'])->toHaveCount(2);

    foreach (['channels/0', 'notifiable_types/1', 'attempts/0/exception_class'] as $path) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $fixture['id'],
            'path' => '/background_activity/items/0/'.$path,
        ])->assertOk());

        expect($content['data']['value'])->toBe('[redacted]');
    }
});

it('applies masks from background storage to raw saved item fields without changing unrelated profiles', function () {
    $this->backgroundRedactionPaths = ['background_activity.channels.0', 'background_activity.job'];
    $this->refreshApplication();
    $fixture = $this->storedBackgroundFixture();
    $stored = app(ProfileStore::class)->get($fixture['id']);

    expect($stored['sections']['queue']['payload']['items'][0]['channels'][0])->toBe('private-channel-sentinel')
        ->and($stored['sections']['queue']['payload']['items'][0]['job'])->toBe('App\\Jobs\\CorrelatedJob');

    $presented = app(BackgroundActivityPresenter::class)->present($stored);

    foreach (['queue', 'mail', 'notifications'] as $section) {
        $item = $presented['sections'][$section]['payload']['items'][0];

        expect($item['channels'])->toBe(['[redacted]', 'public-channel'])
            ->and($item['job'])->toBe('[redacted]')
            ->and($item['status'])->toBe('completed');
    }

    expect($presented['sections']['logs']['payload']['items'][0]['message'])->toBe('private-channel-sentinel')
        ->and(app(ProfileStore::class)->get($fixture['id']))->toBe($stored);
});

it('preserves status precedence when origin identity is masked and maps attempt aliases', function () {
    $this->backgroundRedactionPaths = ['queue.items.0.origin_profile_id', 'mail.items.0.activity_attempt'];
    $this->refreshApplication();
    $fixture = $this->storedBackgroundFixture();
    $presented = app(BackgroundActivityPresenter::class)->present(app(ProfileStore::class)->get($fixture['id']));

    foreach (['queue', 'mail', 'notifications'] as $section) {
        $item = $presented['sections'][$section]['payload']['items'][0];

        expect($item['origin_profile_id'])->toBe('[redacted]')
            ->and($item['activity_attempt'])->toBe('[redacted]')
            ->and($item['status'])->toBe('completed')
            ->and($item['is_origin'])->toBeTrue();
    }

    expect($presented['background_activity']['items'][0]['origin_profile_id'])->toBe('[redacted]')
        ->and($presented['background_activity']['items'][0]['attempt'])->toBe('[redacted]');
});

it('shares masks with runtime context without replacing its captured worker facts', function (array $rules, mixed $channels) {
    $this->backgroundRedactionPaths = $rules;
    $this->refreshApplication();
    $fixture = $this->storedBackgroundFixture();
    $store = app(ProfileStore::class);
    $origin = $store->get($fixture['id']);
    $profile = $origin;
    $profile['id'] = $fixture['worker'];
    $profile['profile_type'] = 'queue';
    $profile['sections']['request'] = [
        'summary' => ['method' => 'CLI', 'status' => 0],
        'payload' => [
            'method' => 'CLI',
            'path' => 'queue:App\\Jobs\\CorrelatedJob',
            'runtime_type' => 'queue',
            'context' => [
                'correlation_key' => $fixture['key'],
                'origin_profile_id' => $fixture['id'],
                'connection' => 'redis',
                'queue' => 'default',
                'job_id' => 'first-job',
                'attempt' => 1,
                'communication_type' => 'notification',
                'communication_class' => 'App\\Notifications\\CorrelatedNotice',
                'channels' => ['private-channel-sentinel', 'public-channel'],
                'notifiable_types' => ['App\\Models\\User', 'private-type-sentinel'],
            ],
        ],
    ];

    foreach (['queue', 'mail', 'notifications'] as $section) {
        $profile['sections'][$section]['payload']['items'][0]['status'] = 'waiting';
    }

    $store->put($profile);
    $stored = $store->get($profile['id']);
    $presented = app(BackgroundActivityPresenter::class)->present($stored);

    $paths = [
        '/sections/request/payload/context/channels',
        '/background_activity/items/0/channels',
    ];

    foreach (['queue', 'mail', 'notifications'] as $section) {
        $paths[] = '/sections/'.$section.'/payload/items/0/channels';
        $item = $presented['sections'][$section]['payload']['items'][0];

        expect($item['channels'])->toBe($channels)
            ->and($item['status'])->toBe('waiting')
            ->and($item['activity_attempt'])->toBe(2)
            ->and($item['worker_profile_id'])->toBe($fixture['worker'])
            ->and($presented['sections'][$section]['payload']['items'][1]['channels'])
            ->toBe(in_array('background_activity.channels.0', $rules, true) ? ['[redacted]', 'public-channel'] : ['private-channel-sentinel', 'public-channel']);
    }

    foreach ($paths as $pointer) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profile['id'], 'path' => $pointer,
        ])->assertOk());
        $data = $content['data'];

        expect($data['type'] === 'list' ? array_column($data['entries'], 'value') : $data['value'])->toBe($channels);
    }

    $request = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profile['id'], 'section' => 'request',
    ])->assertOk());

    expect($request['data']['payload']['runtime_context']['channels'])->toBe($channels)
        ->and($request['data']['payload']['runtime_context']['attempt'])->toBe(1)
        ->and($presented['sections']['request']['payload']['context']['channels'])->toBe($channels)
        ->and($presented['sections']['request']['payload']['context']['attempt'])->toBe(1)
        ->and($presented['background_activity']['items'][0]['channels'])->toBe($channels)
        ->and($presented['background_activity']['items'][0]['status'])->toBe('completed')
        ->and($presented['sections']['logs']['payload']['items'][0]['message'])->toBe('private-channel-sentinel')
        ->and($store->get($fixture['id']))->toBe($origin)
        ->and($store->get($profile['id']))->toBe($stored);
})->with([
    'whole runtime field' => [['request.context.channels'], '[redacted]'],
    'nested runtime field' => [['sections.request.payload.context.channels.0'], ['[redacted]', 'public-channel']],
    'queue field' => [['queue.items.0.channels'], '[redacted]'],
    'mail field' => [['mail.items.0.channels'], '[redacted]'],
    'notification field' => [['notifications.items.0.channels'], '[redacted]'],
    'background field' => [['background_activity.channels.0'], ['[redacted]', 'public-channel']],
    'combined peer masks' => [['request.context.channels.0', 'queue.items.0.channels.1'], ['[redacted]', '[redacted]']],
    'ordinary worker' => [[], ['private-channel-sentinel', 'public-channel']],
]);
