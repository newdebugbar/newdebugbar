<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Facades\Mcp;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Mcp\Tools\GetDebugFindings;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Mcp\Tools\GetDebugProfileSection;
use NewDebugBar\Mcp\Tools\InspectDebugQueries;
use NewDebugBar\Mcp\Tools\ListDebugProfiles;
use NewDebugBar\Presentation\McpProfilePresenter;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\DurationFormatter;
use NewDebugBar\Tests\Fixtures\Models\Client;
use NewDebugBar\Tests\Fixtures\Models\JobActivity;
use NewDebugBar\Tests\Fixtures\Models\ProofVersion;
use NewDebugBar\Tests\Support\McpResponse;

function profilePointerToValue(mixed $value, mixed $target, string $path = ''): ?string
{
    if ($value === $target) {
        return $path;
    }

    if (! is_array($value)) {
        return null;
    }

    foreach ($value as $key => $item) {
        $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
        $pointer = profilePointerToValue($item, $target, $path.'/'.$segment);

        if ($pointer !== null) {
            return $pointer;
        }
    }

    return null;
}

it('registers one local read only server with five schema backed tools', function () {
    $version = (new ReflectionClass(NewDebugBarServer::class))->getDefaultProperties()['version'];

    expect(Mcp::getLocalServer('newdebugbar'))->toBeCallable()
        ->and(Mcp::getWebServer('newdebugbar'))->toBeNull()
        ->and(Mcp::servers())->toHaveKey('newdebugbar')
        ->and($version)->toBe('1.1.0');

    foreach ([
        ListDebugProfiles::class => 'list-debug-profiles',
        GetDebugProfileSection::class => 'get-debug-profile-section',
        GetDebugProfileData::class => 'get-debug-profile-data',
        InspectDebugQueries::class => 'inspect-debug-queries',
        GetDebugFindings::class => 'get-debug-findings',
    ] as $toolClass => $name) {
        $tool = app($toolClass)->toArray();

        expect($tool['name'])->toBe($name)
            ->and($tool['inputSchema']['type'])->toBe('object')
            ->and($tool['outputSchema']['required'])->toContain('version', 'status', 'data')
            ->and($tool['annotations'])->toBe([
                'readOnlyHint' => true,
                'openWorldHint' => false,
            ]);
    }

    $dataSchema = app(GetDebugProfileData::class)->toArray();
    $serverDefaults = (new ReflectionClass(NewDebugBarServer::class))->getDefaultProperties();

    expect($dataSchema['inputSchema']['properties']['path']['default'])->toBe('/sections')
        ->and($dataSchema['inputSchema']['properties']['limit']['default'])->toBe(10)
        ->and($dataSchema['outputSchema']['properties']['data']['properties'])
        ->toHaveKeys(['profile_id', 'path', 'type', 'entries', 'value', 'pagination'])
        ->and(app(GetDebugProfileData::class)->description())
        ->toContain('/sections/models/payload/model_groups', 'folded model operations', 'query correlation', 'guidance', '/sections/redis/payload/items/{index}/callsite', '/sections/exceptions/payload/items/{index}/causes')
        ->and(app(GetDebugProfileSection::class)->description())
        ->toContain('Redis items', 'application call sites', 'bounded cause locations', 'retained exception causes')
        ->and($serverDefaults['instructions'])
        ->toContain('/sections/models/payload/model_groups', 'identifiers', 'changed attributes', 'related queries', '/sections/redis/payload/items/{index}/callsite', '/sections/exceptions/payload/items/{index}/causes')
        ->and(app(InspectDebugQueries::class)->description())
        ->toContain('database driver')
        ->and(app(ListDebugProfiles::class)->description())
        ->toContain('adaptive duration labels');
});

it('correlates the exact response profile while unrelated profiles exist', function () {
    $first = $this->get('/profiled?patient=private-marker', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $this->get('/profiled-next', ['Accept' => 'text/html'])->assertOk();
    $profileCount = count(File::files(config('newdebugbar.storage.path')));

    $queries = McpResponse::structuredContent(NewDebugBarServer::tool(InspectDebugQueries::class, [
        'profile_id' => $first,
        'filter' => 'repeated',
        'limit' => 1,
    ])->assertOk());
    $driverSearch = McpResponse::structuredContent(NewDebugBarServer::tool(InspectDebugQueries::class, [
        'profile_id' => $first,
        'filter' => 'repeated',
        'search' => 'sqlite',
        'limit' => 1,
    ])->assertOk());
    $findings = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugFindings::class, [
        'profile_id' => $first,
    ])->assertOk());

    expect($queries)
        ->version->toBe(1)
        ->status->toBe('ok')
        ->data->profile_id->toBe($first)
        ->data->summary->repeated_pattern_count->toBe(1)
        ->data->repeated_groups->toHaveCount(1)
        ->data->repeated_groups->{0}->count->toBe(3)
        ->data->repeated_groups->{0}->driver->toBe('sqlite')
        ->data->repeated_groups->{0}->executions->{0}->driver->toBe('sqlite')
        ->and($driverSearch)
        ->data->repeated_groups->toHaveCount(1)
        ->and(array_column($findings['data']['findings'], 'rule_id'))
        ->not->toContain('query.repeated', 'query.n_plus_one')
        ->and(count(File::files(config('newdebugbar.storage.path'))))->toBe($profileCount);
});

it('lists and filters bounded profile summaries', function () {
    $this->get('/profiled', ['Accept' => 'text/html'])->assertOk();
    $this->get('/failed-html', ['Accept' => 'text/html'])->assertUnprocessable();

    $all = McpResponse::structuredContent(NewDebugBarServer::tool(ListDebugProfiles::class, ['limit' => 1])->assertOk());
    $failed = McpResponse::structuredContent(NewDebugBarServer::tool(ListDebugProfiles::class, [
        'method' => 'get',
        'path' => 'failed',
        'status' => 422,
        'warning' => true,
    ])->assertOk());

    expect($all['data']['profiles'])->toHaveCount(1)
        ->and($all['data'])
        ->count->toBe(1)
        ->total->toBe(2)
        ->truncated->toBeTrue()
        ->and($failed['data']['profiles'])->toHaveCount(1)
        ->and($failed['data']['profiles'][0])
        ->path->toBe('/failed-html')
        ->status->toBe(422)
        ->warning->toBeTrue()
        ->duration_label->toBe(DurationFormatter::format($failed['data']['profiles'][0]['duration_ms']))
        ->query_time_label->toBe(DurationFormatter::format($failed['data']['profiles'][0]['query_time_ms']))
        ->and($failed['data']['profiles'][0]['available_sections'])
        ->toContain('overview', 'request', 'timeline', 'livewire')
        ->and($failed['data']['profiles'][0]['data_path'])->toBe('');
});

it('keeps every presented section reachable through MCP', function () {
    $response = $this->get('/profiled-livewire', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($profileId));
    $presentedSections = array_keys($profile['sections']);
    $mcpSections = app(McpProfilePresenter::class)->sectionNames();

    sort($presentedSections);
    sort($mcpSections);

    expect($mcpSections)->toBe($presentedSections);

    foreach ($presentedSections as $section) {
        $focused = app(McpProfilePresenter::class)->section($profileId, $section, 0, 1);
        $complete = app(McpProfilePresenter::class)->data(
            $profileId,
            '/sections/'.str_replace(['~', '/'], ['~0', '~1'], $section),
            0,
            1,
        );

        expect($focused['status'])->toBe('ok')
            ->and($complete['status'])->toBe('ok');
    }
});

it('walks exact retained values that focused MCP responses intentionally omit', function () {
    $profiles = [
        [$this->get('/profiled-private-query', ['Accept' => 'text/html'])->assertOk(), 'private-alpha'],
        [$this->get('/profiled-context', ['Accept' => 'text/html'])->assertOk(), 'view-data-value'],
        [$this->get('/profiled-messages', ['Accept' => 'text/html'])->assertOk(), 'private body'],
        [$this->get('/profiled-livewire', ['Accept' => 'text/html'])->assertOk(), 'Host counter'],
    ];

    foreach ($profiles as [$response, $expected]) {
        $profileId = $response->headers->get('X-NewDebugBar-Profile');
        $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($profileId));
        $path = profilePointerToValue($profile, $expected);

        expect($path)->not->toBeNull();

        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profileId,
            'path' => $path,
        ])->assertOk());

        expect($content['status'])->toBe('ok')
            ->and($content['data']['path'])->toBe($path)
            ->and($content['data']['value'])->toBe($expected);
    }
});

it('keeps complete Models evidence reachable through bounded generic MCP paths', function () {
    $response = $this->get('/profiled-models?changes=1&queries=1&missing=1&sources=1', ['Accept' => 'text/html'])
        ->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($profileId));
    $groups = $profile['sections']['models']['payload']['model_groups'];
    $clientIndex = array_search(Client::class, array_column($groups, 'model'), true);
    $jobIndex = array_search(JobActivity::class, array_column($groups, 'model'), true);
    $proofIndex = array_search(ProofVersion::class, array_column($groups, 'model'), true);

    expect($clientIndex)->not->toBeFalse()
        ->and($jobIndex)->not->toBeFalse()
        ->and($proofIndex)->not->toBeFalse()
        ->and($groups[$proofIndex]['records'][0]['sources'])->toHaveCount(2);

    $jobSourceIndex = collect($groups[$jobIndex]['sources'])
        ->search(fn (array $source): bool => (int) ($source['query_count'] ?? 0) > 0);
    $queryGuidanceIndex = collect($groups[$jobIndex]['guidance'])
        ->search(fn (array $guidance): bool => ($guidance['type'] ?? null) === 'query_correlation');

    expect($jobSourceIndex)->not->toBeFalse()
        ->and($queryGuidanceIndex)->not->toBeFalse();

    $paths = [
        '/sections/models/summary/model_change_count' => $profile['sections']['models']['summary']['model_change_count'],
        '/sections/models/summary/retrieval_count' => $profile['sections']['models']['summary']['retrieval_count'],
        "/sections/models/payload/model_groups/{$clientIndex}/model" => Client::class,
        "/sections/models/payload/model_groups/{$clientIndex}/connection" => 'testing',
        "/sections/models/payload/model_groups/{$clientIndex}/table" => 'clients',
        "/sections/models/payload/model_groups/{$clientIndex}/change_operations/0/event" => 'updated',
        "/sections/models/payload/model_groups/{$clientIndex}/change_operations/0/key" => 4,
        "/sections/models/payload/model_groups/{$clientIndex}/change_operations/0/lifecycle_events/updating" => 1,
        "/sections/models/payload/model_groups/{$clientIndex}/change_operations/0/changes/status" => 'approved',
        "/sections/models/payload/model_groups/{$clientIndex}/change_operations/0/changes/api_token" => '[redacted]',
        "/sections/models/payload/model_groups/{$clientIndex}/change_operations/0/at_ms" => $groups[$clientIndex]['change_operations'][0]['at_ms'],
        "/sections/models/payload/model_groups/{$clientIndex}/sources/0/callsite/file" => $groups[$clientIndex]['sources'][0]['callsite']['file'],
        "/sections/models/payload/model_groups/{$jobIndex}/records/0/key" => $groups[$jobIndex]['records'][0]['key'],
        "/sections/models/payload/model_groups/{$proofIndex}/records/0/sources/1/callsite/file" => $groups[$proofIndex]['records'][0]['sources'][1]['callsite']['file'],
        "/sections/models/payload/model_groups/{$proofIndex}/records/0/sources/1/callsite/line" => $groups[$proofIndex]['records'][0]['sources'][1]['callsite']['line'],
        "/sections/models/payload/model_groups/{$jobIndex}/sources/{$jobSourceIndex}/query_count" => 1,
        "/sections/models/payload/model_groups/{$jobIndex}/sources/{$jobSourceIndex}/query_read_count" => 1,
        "/sections/models/payload/model_groups/{$jobIndex}/guidance/{$queryGuidanceIndex}/type" => 'query_correlation',
        "/sections/models/payload/model_groups/{$jobIndex}/guidance/{$queryGuidanceIndex}/why" => $groups[$jobIndex]['guidance'][$queryGuidanceIndex]['why'],
    ];

    foreach ($paths as $path => $expected) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profileId,
            'path' => $path,
            'limit' => 2,
        ])->assertOk());

        expect($content)
            ->status->toBe('ok')
            ->data->path->toBe($path)
            ->data->value->toBe($expected);
    }

    $focused = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'models',
        'limit' => 50,
    ])->assertOk());

    expect($focused['data']['payload'])
        ->not->toHaveKeys(['groups', 'model_groups', 'model_group_previews'])
        ->and(collect($focused['data']['payload']['items'])->firstWhere('key', '[identifier]'))
        ->not->toBeNull();
});

it('keeps full log context values reachable beyond their compact previews', function () {
    $response = $this->get('/profiled-logs', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($profileId));
    $groups = $profile['sections']['logs']['payload']['groups'];
    $groupIndex = array_search('critical', array_column($groups, 'level'), true);
    $expected = str_repeat('Retained diagnostic context. ', 12)."\nFinal retained line.";

    expect($groupIndex)->not->toBeFalse();

    $fieldIndex = array_search('detail', array_column($groups[$groupIndex]['context_fields'], 'key'), true);

    expect($fieldIndex)->not->toBeFalse()
        ->and($groups[$groupIndex]['context_fields'][$fieldIndex]['preview'])->not->toBe($expected);

    foreach ([
        "/sections/logs/payload/groups/{$groupIndex}/context/detail",
        "/sections/logs/payload/groups/{$groupIndex}/context_fields/{$fieldIndex}/value",
    ] as $path) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profileId,
            'path' => $path,
            'limit' => 2,
        ])->assertOk());

        expect($content)
            ->status->toBe('ok')
            ->data->path->toBe($path)
            ->data->value->toBe($expected);
    }
});

it('keeps Redis client call sites reachable through focused and generic MCP responses', function () {
    $response = $this->get('/profiled-redis-client', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($profileId));
    $items = $profile['sections']['redis']['payload']['items'];

    expect($items)->not->toBeEmpty();

    foreach ($items as $index => $item) {
        expect($item['callsite'])
            ->file->toBe('tests/Fixtures/Redis/ProfiledRedisCaller.php')
            ->line->toBeGreaterThan(0);

        foreach (['file', 'line'] as $field) {
            $path = "/sections/redis/payload/items/{$index}/callsite/{$field}";
            $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
                'profile_id' => $profileId,
                'path' => $path,
            ])->assertOk());

            expect($content)
                ->status->toBe('ok')
                ->data->path->toBe($path)
                ->data->value->toBe($item['callsite'][$field]);
        }
    }

    $focused = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'redis',
        'limit' => 10,
    ])->assertOk());

    expect(array_column($focused['data']['payload']['items'], 'callsite'))->toBe(array_column($items, 'callsite'))
        ->and(json_encode($focused))->not->toContain('private Redis result', 'private-client-field');

    foreach ($focused['data']['payload']['items'] as $item) {
        expect($item)->not->toHaveKeys(['parameters', 'result', 'value']);
    }
});

it('discovers and pages nested profile data with JSON Pointer paths', function () {
    $response = $this->get('/profiled-livewire', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');

    $sections = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/sections',
        'limit' => 2,
    ])->assertOk());
    $components = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/sections/livewire/payload/components',
        'limit' => 1,
    ])->assertOk());

    expect($sections['data'])
        ->type->toBe('object')
        ->count->toBeGreaterThan(2)
        ->and($sections['data']['entries'])->toHaveCount(2)
        ->and($sections['data']['pagination'])
        ->returned->toBe(2)
        ->truncated->toBeTrue()
        ->next_cursor->toBe(2)
        ->and($components['data'])
        ->type->toBe('list')
        ->count->toBe(1)
        ->and($components['data']['entries'][0])
        ->path->toBe('/sections/livewire/payload/components/0')
        ->type->toBe('object');
});

it('resolves escaped JSON Pointer keys without changing retained values', function () {
    $profileId = (string) Str::uuid();
    $absoluteFile = '/private/project/app/Exact.php';
    app(ProfileStore::class)->put([
        'schema_version' => 1,
        'id' => $profileId,
        'metrics' => ['duration_ms' => 1],
        'custom/key~name' => [
            'exact' => 'reachable-value',
            'file' => $absoluteFile,
        ],
        'sections' => [],
    ]);

    $exact = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/custom~1key~0name/exact',
    ])->assertOk());
    $missing = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/custom~1key~0name/missing',
    ])->assertOk());
    $file = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/custom~1key~0name/file',
    ])->assertOk());

    expect($exact['data']['value'])->toBe('reachable-value')
        ->and($file['data']['value'])->toBe($absoluteFile)
        ->and($missing)->toBe([
            'version' => 1,
            'status' => 'not_found',
            'data' => [
                'profile_id' => $profileId,
                'path' => '/custom~1key~0name/missing',
            ],
        ]);
});

it('keeps oversized retained strings reachable in bounded chunks', function () {
    config()->set('newdebugbar.mcp.max_bytes', 10_000);
    app()->forgetInstance(McpProfilePresenter::class);
    $profileId = (string) Str::uuid();
    $value = str_repeat('chunk-', 2_000);
    app(ProfileStore::class)->put([
        'schema_version' => 1,
        'id' => $profileId,
        'metrics' => ['duration_ms' => 1],
        'large_value' => $value,
        'sections' => [],
    ]);

    $responses = [];
    $chunks = [];
    $cursor = 0;

    do {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profileId,
            'path' => '/large_value',
            'cursor' => $cursor,
            'limit' => 2,
        ])->assertOk());
        $responses[] = $content;
        $chunks = [...$chunks, ...$content['data']['chunks']];
        $cursor = $content['data']['pagination']['next_cursor'];

        expect(strlen(json_encode($content, JSON_UNESCAPED_UNICODE)))->toBeLessThanOrEqual(10_000);
    } while ($cursor !== null);

    expect($responses[0]['data'])
        ->type->toBe('string')
        ->chunked->toBeTrue()
        ->length_bytes->toBe(strlen($value))
        ->and($responses[0]['data']['pagination']['truncated'])->toBeTrue()
        ->and($responses[array_key_last($responses)]['data']['pagination']['truncated'])->toBeFalse()
        ->and(implode('', $chunks))->toBe($value);
});

it('keeps string chunks reachable under the smallest supported response budget', function () {
    config()->set('newdebugbar.mcp.max_bytes', 700);
    app()->forgetInstance(McpProfilePresenter::class);
    $profileId = (string) Str::uuid();
    $value = str_repeat('é漢🙂-', 80);
    app(ProfileStore::class)->put([
        'schema_version' => 1,
        'id' => $profileId,
        'metrics' => ['duration_ms' => 1],
        'large_value' => $value,
        'sections' => [],
    ]);

    $chunks = [];
    $cursor = 0;

    do {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profileId,
            'path' => '/large_value',
            'cursor' => $cursor,
            'limit' => 10,
        ])->assertOk());
        $chunks = [...$chunks, ...$content['data']['chunks']];
        $cursor = $content['data']['pagination']['next_cursor'];

        expect(strlen(json_encode($content, JSON_UNESCAPED_UNICODE)))->toBeLessThanOrEqual(700);
    } while ($cursor !== null);

    expect(implode('', $chunks))->toBe($value);
});

it('exposes queued communication facts and correlated worker outcomes through MCP', function () {
    $originId = $this->get('/profiled-queued-communications', ['Accept' => 'text/html'])
        ->assertOk()
        ->headers->get('X-NewDebugBar-Profile');
    $stored = app(ProfileStore::class)->get($originId);
    $presented = app(ProfilePresenter::class)->present($stored);
    $mailItem = $presented['sections']['mail']['payload']['items'][0];
    $notificationItem = $presented['sections']['notifications']['payload']['items'][0];

    $profiles = McpResponse::structuredContent(NewDebugBarServer::tool(ListDebugProfiles::class, [
        'limit' => 10,
    ])->assertOk());
    $originSummary = collect($profiles['data']['profiles'])->firstWhere('id', $originId);
    $mail = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $originId,
        'section' => 'mail',
    ])->assertOk());
    $notifications = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $originId,
        'section' => 'notifications',
    ])->assertOk());

    expect($originSummary)
        ->background_pending->toBeTrue()
        ->background_activity_count->toBe(2)
        ->related_profile_ids->toBe([])
        ->and($mail['data']['payload']['items'][0])
        ->status->toBe('delayed')
        ->source->toBe($mailItem['source'])
        ->connection->toBe('redis')
        ->queue->toBe('mail-delayed')
        ->delay_seconds->toBe(30)
        ->correlation_key->toBe($mailItem['correlation_key'])
        ->and($notifications['data']['payload']['items'][0])
        ->status->toBe('queued')
        ->notification->toBe($notificationItem['notification'])
        ->channel->toBe('mail')
        ->correlation_key->toBe($notificationItem['correlation_key']);

    $workerId = (string) Str::uuid();
    app(BackgroundActivityStore::class)->recordOutcome($mailItem['correlation_key'], 'sent', $workerId, 1);
    app(BackgroundActivityStore::class)->recordOutcome($notificationItem['correlation_key'], 'failed', $workerId, 1, RuntimeException::class);

    $refreshed = app(McpProfilePresenter::class)->list([], 10);
    $refreshedOrigin = collect($refreshed['data']['profiles'])->firstWhere('id', $originId);

    expect($refreshedOrigin)
        ->background_pending->toBeFalse()
        ->related_profile_ids->toBe([$workerId]);
});

it('exposes every recorded context section through the bounded section tool', function () {
    $response = $this->get('/profiled-context', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');

    foreach (['authorization', 'validation'] as $section) {
        $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
            'profile_id' => $profileId,
            'section' => $section,
        ])->assertOk());

        expect($content['status'])->toBe('ok')
            ->and($content['data']['section'])->toBe($section);

        if ($section === 'authorization') {
            $decision = $content['data']['payload']['items'][0];

            expect($decision)
                ->toHaveKey('user')
                ->not->toHaveKeys(['actor', 'user_type']);
        }
    }

    $missing = app(McpProfilePresenter::class)->section($profileId, 'messages', 0, 50);

    expect($missing['status'])->toBe('not_found')
        ->and($missing['data']['available_sections'])->not->toContain('messages')
        ->and(app(McpProfilePresenter::class)->sectionNames())->not->toContain('messages');
});

it('keeps captured mail content out of MCP responses', function () {
    $response = $this->get('/profiled-messages', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');

    $mail = NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'mail',
    ])->assertOk()
        ->assertDontSee([
            'private body',
            'private subject',
            'private-sender@example.test',
            'private-recipient@example.test',
            'private-copy@example.test',
            base64_encode('private attachment'),
        ]);
    $content = McpResponse::structuredContent($mail);

    expect($content['data']['payload']['items'][0]['preview'])->toMatchArray([
        'available' => true,
        'html_available' => false,
        'text_available' => true,
        'eml_available' => true,
        'truncated' => false,
        'attachments_omitted' => 0,
        'addresses_omitted' => 0,
    ]);
});

it('masks full query bindings and log labels again at the MCP boundary', function () {
    $response = $this->get('/profiled-private-query', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');

    expect(json_encode(app(ProfileStore::class)->get($profileId)['sections']['queries']))
        ->toContain('private-alpha', 'private-beta', 'private-gamma');

    $queries = McpResponse::structuredContent(NewDebugBarServer::tool(InspectDebugQueries::class, [
        'profile_id' => $profileId,
        'filter' => 'repeated',
    ])->assertOk()
        ->assertDontSee(['private-alpha', 'private-beta', 'private-gamma']));
    $timeline = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'timeline',
        'limit' => 50,
    ])->assertOk()
        ->assertDontSee('private timeline log message'));

    expect($queries['data']['repeated_groups'][0]['executions'][0])
        ->bindings->toBe(['[string]'])
        ->binding_policy->toBe('safe')
        ->bindings_complete->toBeFalse()
        ->runnable_available->toBeFalse()
        ->not->toHaveKey('runnable_sql')
        ->and(collect($timeline['data']['payload']['items'])->firstWhere('section', 'logs')['label'])
        ->toBe('[log message hidden]');
});

it('masks captured view values at the MCP boundary', function () {
    $response = $this->get('/profiled-context', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');

    expect(json_encode(app(ProfileStore::class)->get($profileId)['sections']['views']))
        ->toContain('view-data-value');

    $views = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'views',
    ])->assertOk()
        ->assertDontSee('view-data-value'));

    expect($views['data']['payload']['items'][0]['data'])
        ->label->toBe('[string]')
        ->private_value->toBe('[string]')
        ->rows->toBe('[array]');
});

it('paginates one section and hides private request values', function () {
    $response = $this->post('/profiled-input?name=query-secret', [
        'clinic' => ['name' => 'patient-secret'],
        'token' => 'token-secret',
    ], ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');

    $request = NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'request',
        'cursor' => 0,
        'limit' => 1,
    ])->assertOk()
        ->assertDontSee(['query-secret', 'patient-secret', 'token-secret', 'authorization']);
    $content = McpResponse::structuredContent($request);

    expect($content['data']['payload'])
        ->not->toHaveKeys(['input', 'query', 'headers', 'response_headers', 'url'])
        ->and($content['data']['payload']['input_keys'])->toBe(['clinic', 'token', 'name'])
        ->and($content['data']['payload']['query_keys'])->toBe(['name'])
        ->and($content['data']['payload']['request_size_bytes'])->toBeGreaterThan(0)
        ->and($content['data']['payload']['response_size_bytes'])->toBeGreaterThan(0)
        ->and($content['data']['payload']['session_present'])->toBeFalse()
        ->and($content['data']['payload']['authenticated'])->toBeFalse()
        ->and($content['data']['pagination'])->toMatchArray([
            'cursor' => 0,
            'returned' => 0,
            'total' => 0,
            'truncated' => false,
            'next_cursor' => null,
        ]);
});

it('summarizes exception causes while keeping full retained evidence reachable', function () {
    $response = $this->get('/profiled-reported-exception', ['Accept' => 'text/html'])
        ->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($profileId));
    $cause = $profile['sections']['exceptions']['payload']['items'][0]['causes'][0];

    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'exceptions',
    ])->assertOk());
    $item = $content['data']['payload']['items'][0];

    expect($item)
        ->message->toBe('[message hidden]')
        ->file->toBe('tests/Support/DefinesTestApplication.php')
        ->not->toHaveKeys(['source', 'frames'])
        ->and($item['application_frames'])->not->toBeEmpty()
        ->and($item['cause_count'])->toBe(1)
        ->and($item['causes'][0])
        ->toMatchArray([
            'class' => LogicException::class,
            'file' => 'tests/Support/DefinesTestApplication.php',
            'line' => $cause['line'],
        ])
        ->not->toHaveKeys(['message', 'source', 'frames'])
        ->and($item['chain_truncated'])->toBeFalse()
        ->and(json_encode($content))
        ->not->toContain(base_path().'/', 'Earlier itinerary failure.');

    $causeObjectPath = '/sections/exceptions/payload/items/0/causes/0';
    $causeObject = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => $causeObjectPath,
        'limit' => 10,
    ])->assertOk());

    expect($causeObject['data'])
        ->type->toBe('object')
        ->and(array_column($causeObject['data']['entries'], 'key'))
        ->toContain('class', 'message', 'file', 'line', 'frames', 'source');

    foreach ([
        "{$causeObjectPath}/message" => $cause['message'],
        "{$causeObjectPath}/frames/application/0/file" => $cause['frames']['application'][0]['file'],
        "{$causeObjectPath}/source/focus_line" => $cause['source']['focus_line'],
        "{$causeObjectPath}/source/lines/0/code" => $cause['source']['lines'][0]['code'],
        '/sections/exceptions/payload/items/0/chain_truncated' => false,
    ] as $path => $expected) {
        $evidence = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
            'profile_id' => $profileId,
            'path' => $path,
        ])->assertOk());

        expect($evidence)
            ->status->toBe('ok')
            ->data->path->toBe($path)
            ->data->value->toBe($expected);
    }
});

it('returns stable not found results and validation errors', function () {
    $missing = (string) Str::uuid();
    $wrongVersion = '550e8400-e29b-11d4-a716-446655440000';
    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugFindings::class, [
        'profile_id' => $missing,
    ])->assertOk());

    expect($content)->toBe([
        'version' => 1,
        'status' => 'not_found',
        'data' => ['profile_id' => $missing],
    ]);

    NewDebugBarServer::tool(GetDebugFindings::class, ['profile_id' => '../bad'])
        ->assertHasErrors(['profile id']);
    NewDebugBarServer::tool(GetDebugFindings::class, ['profile_id' => $wrongVersion])
        ->assertHasErrors(['profile id']);
    NewDebugBarServer::tool(InspectDebugQueries::class, [
        'profile_id' => $missing,
        'filter' => 'unsafe',
    ])->assertHasErrors(['filter']);

    expect(app(McpProfilePresenter::class)->section($wrongVersion, 'overview', 0, 1))->toBe([
        'version' => 1,
        'status' => 'not_found',
        'data' => ['profile_id' => $wrongVersion],
    ]);
});

it('enforces byte depth and item limits without exposing corrupt profiles', function () {
    config()->set('newdebugbar.mcp.max_items', 2);
    config()->set('newdebugbar.mcp.max_bytes', 700);
    app()->forgetInstance(McpProfilePresenter::class);

    $response = $this->get('/profiled', ['Accept' => 'text/html'])->assertOk();
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $this->get('/failed-html', ['Accept' => 'text/html'])->assertUnprocessable();
    $corruptId = (string) Str::uuid();
    File::put(config('newdebugbar.storage.path').'/'.$corruptId.'.json', '{broken');

    $events = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'events',
        'limit' => 2,
    ])->assertOk());
    $profiles = McpResponse::structuredContent(NewDebugBarServer::tool(ListDebugProfiles::class)->assertOk());
    $models = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'models',
        'limit' => 2,
    ])->assertOk());

    expect(strlen(json_encode($events)))->toBeLessThanOrEqual(700)
        ->and(strlen(json_encode($profiles)))->toBeLessThanOrEqual(700)
        ->and(strlen(json_encode($models)))->toBeLessThanOrEqual(700)
        ->and($events['data']['pagination']['returned'])->toBeLessThanOrEqual(2)
        ->and($events['data']['pagination']['truncated'])->toBeTrue()
        ->and($profiles['data']['truncated'])->toBeTrue()
        ->and($models['data']['payload'])->not->toHaveKeys(['groups', 'model_groups', 'repeated_groups', 'repeated_misses'])
        ->and(array_column($profiles['data']['profiles'], 'id'))->not->toContain($corruptId);

    NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $corruptId,
        'path' => '/sections/models',
    ])->assertHasErrors(['The debug profile could not be processed.']);
});

it('advances past an item that cannot fit within the MCP byte limit', function () {
    config()->set('newdebugbar.mcp.max_bytes', 700);
    app()->forgetInstance(McpProfilePresenter::class);
    $profileId = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'schema_version' => 1,
        'id' => $profileId,
        'metrics' => ['duration_ms' => 1],
        'sections' => [
            'events' => [
                'label' => 'Events',
                'summary' => ['count' => 1],
                'payload' => ['items' => [[
                    'name' => str_repeat('oversized-event-', 180),
                ]]],
            ],
        ],
    ]);

    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'events',
        'limit' => 1,
    ])->assertOk());

    expect(strlen(json_encode($content)))->toBeLessThanOrEqual(700)
        ->and($content['data']['pagination'])
        ->returned->toBe(0)
        ->omitted_due_to_bytes->toBe(1)
        ->next_cursor->toBeNull();
});

it('falls back to bounded identity metadata when section metadata is oversized', function () {
    config()->set('newdebugbar.mcp.max_bytes', 700);
    app()->forgetInstance(McpProfilePresenter::class);
    $profileId = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'schema_version' => 1,
        'id' => $profileId,
        'metrics' => ['duration_ms' => 1],
        'sections' => [
            'request' => [
                'label' => 'Request',
                'summary' => ['method' => 'GET', 'status' => 200],
                'payload' => [
                    'path' => '/oversized',
                    'middleware' => array_fill(0, 100, str_repeat('LongMiddlewareName', 120)),
                ],
            ],
        ],
    ]);

    $content = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'request',
    ])->assertOk());

    expect(strlen(json_encode($content)))->toBeLessThanOrEqual(700)
        ->and($content['data'])
        ->profile_id->toBe($profileId)
        ->section->toBe('request')
        ->content_omitted->toBeTrue()
        ->and($content['data']['pagination']['truncated'])->toBeTrue();
});

it('bounds deeply nested focused values and surfaces malformed profile processing errors', function () {
    $profileId = (string) Str::uuid();
    app(ProfileStore::class)->put([
        'schema_version' => 1,
        'id' => $profileId,
        'metrics' => ['duration_ms' => 1],
        'sections' => [
            'events' => [
                'label' => 'Events',
                'summary' => ['count' => 1],
                'payload' => ['items' => [[
                    'name' => 'nested.event',
                    'nested' => ['one' => ['two' => ['three' => ['four' => ['five' => 'private-deep-value']]]]],
                ]]],
            ],
        ],
    ]);

    NewDebugBarServer::tool(GetDebugProfileSection::class, [
        'profile_id' => $profileId,
        'section' => 'events',
    ])->assertOk()
        ->assertSee('[maximum depth reached]')
        ->assertDontSee('private-deep-value');

    $malformedId = (string) Str::uuid();
    File::put(config('newdebugbar.storage.path').'/'.$malformedId.'.json', json_encode([
        'id' => $malformedId,
        'sections' => ['queries' => ['payload' => ['items' => ['not-an-item']]]],
    ]));
    NewDebugBarServer::tool(GetDebugFindings::class, [
        'profile_id' => $malformedId,
    ])->assertHasErrors(['The debug profile could not be processed.']);
    NewDebugBarServer::tool(ListDebugProfiles::class)
        ->assertHasErrors(['The debug profile could not be processed.']);
});
