<?php

use NewDebugBar\Analysis\TimelineBuilder;

it('marks the timeline incomplete when the storage budget removes records or a payload', function () {
    $profile = [
        'sections' => [
            'logs' => ['summary' => ['storage_omitted_items' => 118, 'dropped_count' => 2]],
            'events' => ['summary' => ['retained_count' => 3]],
        ],
        'storage' => ['truncated' => true, 'omitted_sections' => ['events']],
    ];
    expect((new TimelineBuilder)->omittedSources($profile))->toBe(['logs' => 120, 'events' => 3]);
});

it('keeps point events distinct and visualizes recorded durations', function () {
    $timeline = (new TimelineBuilder)->build([
        'metrics' => ['duration_ms' => 50],
        'sections' => [
            'request' => ['payload' => []],
            'queries' => ['payload' => ['items' => [[
                'normalized_sql' => 'select * from users',
                'duration_ms' => 4,
                'at_ms' => 10,
            ]]]],
            'cache' => ['payload' => ['items' => [[
                'operation' => 'hit',
                'key_hash' => 'abc',
                'at_ms' => 5,
                'callsite' => ['file' => 'app/Support/TripCache.php', 'line' => 18],
            ]]]],
            'logs' => ['payload' => ['items' => [[
                'level' => 'info',
                'message' => str_repeat('a', 200),
                'at_ms' => 20,
            ]]]],
            'http_client' => ['payload' => ['items' => [[
                'method' => 'GET',
                'url' => 'https://example.test/status',
                'duration_ms' => 10,
                'at_ms' => 30,
            ]]]],
        ],
    ]);

    expect(array_column($timeline, 'id'))->toBe([
        'request-start',
        'cache-0',
        'queries-0',
        'logs-0',
        'http_client-0',
        'request-end',
    ])->and($timeline[1])
        ->kind->toBe('point')
        ->start_ms->toBeNull()
        ->duration_ms->toBeNull()
        ->at_percent->toBe(10.0)
        ->section_label->toBe('Cache')
        ->source->toBe(['file' => 'app/Support/TripCache.php', 'line' => 18])
        ->and($timeline[2])
        ->kind->toBe('span')
        ->start_ms->toBe(6.0)
        ->duration_ms->toBe(4.0)
        ->start_percent->toBe(12.0)
        ->at_percent->toBe(20.0)
        ->duration_percent->toBe(8.0)
        ->and($timeline[4])
        ->kind->toBe('span')
        ->start_ms->toBe(20.0)
        ->duration_ms->toBe(10.0)
        ->start_percent->toBe(40.0)
        ->at_percent->toBe(60.0)
        ->duration_percent->toBe(20.0)
        ->and($timeline[5])
        ->at_percent->toBe(100.0)
        ->and(mb_strlen($timeline[3]['label']))->toBeLessThanOrEqual(140);
});

it('names authorization and validation activity with useful evidence', function () {
    $timeline = collect((new TimelineBuilder)->build([
        'metrics' => ['duration_ms' => 25],
        'sections' => [
            'request' => ['payload' => []],
            'authorization' => ['payload' => ['items' => [[
                'ability' => 'publish-itinerary',
                'result' => 'denied',
                'at_ms' => 5,
            ]]]],
            'validation' => ['payload' => ['items' => [[
                'fields' => ['email', 'name', 'dates', 'guests'],
                'at_ms' => 10,
            ]]]],
        ],
    ]));

    expect($timeline->firstWhere('section', 'authorization')['label'])->toBe('Denied publish-itinerary')
        ->and($timeline->firstWhere('section', 'validation')['label'])->toBe('Validation failed: email, name, dates and 1 more');
});

it('keeps timeline geometry bounded when events exceed the reported duration', function () {
    $timeline = (new TimelineBuilder)->build([
        'metrics' => ['duration_ms' => 0],
        'sections' => [
            'request' => ['payload' => []],
            'queries' => ['payload' => ['items' => [[
                'normalized_sql' => 'select 1',
                'duration_ms' => -5,
                'at_ms' => 20,
            ]]]],
        ],
    ]);

    $query = collect($timeline)->firstWhere('id', 'queries-0');
    $requestEnd = collect($timeline)->firstWhere('id', 'request-end');

    expect($query)
        ->kind->toBe('point')
        ->start_ms->toBeNull()
        ->duration_ms->toBeNull()
        ->start_percent->toBeNull()
        ->at_percent->toBe(100.0)
        ->duration_percent->toBeNull()
        ->and($requestEnd)
        ->at_percent->toBe(0.0);
});

it('reports every collector source omitted from the timeline', function () {
    $omitted = (new TimelineBuilder)->omittedSources([
        'sections' => [
            'queries' => ['summary' => ['dropped_count' => 2]],
            'views' => ['summary' => ['dropped_count' => 17]],
            'logs' => ['summary' => ['dropped_count' => 0]],
        ],
    ]);

    expect($omitted)->toBe(['queries' => 2, 'views' => 17]);
});
