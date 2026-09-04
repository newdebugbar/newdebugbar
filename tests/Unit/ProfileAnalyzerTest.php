<?php

use NewDebugBar\Analysis\ProfileAnalyzer;
use NewDebugBar\Analysis\QueryAnalyzer;

it('reports one profile storage limit with its omission evidence', function () {
    $storage = ['truncated' => true, 'max_bytes' => 10_000_000, 'omitted_item_count' => 15];
    $findings = (new ProfileAnalyzer(new QueryAnalyzer))->analyze(['storage' => $storage]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['rule_id'])->toBe('profile.truncated')
        ->and($findings[0]['evidence'])->toBe($storage)
        ->and($findings[0]['next'])->toContain('/storage');
});

it('produces stable bounded findings with supporting evidence', function () {
    $analyzer = new ProfileAnalyzer(
        queries: new QueryAnalyzer(slowQueryMs: 50),
        slowRequestMs: 100,
        minimumCacheOperations: 5,
        highCacheMissRate: 0.8,
        maxFindings: 20,
    );

    $profile = [
        'metrics' => ['duration_ms' => 150],
        'sections' => [
            'request' => ['summary' => ['status' => 500]],
            'queries' => [
                'label' => 'Queries',
                'summary' => ['count' => 5, 'retained_count' => 3, 'dropped_count' => 2],
                'payload' => [
                    'items' => [
                        ['sql' => 'select ?', 'bindings' => [1], 'duration_ms' => 60, 'callsite' => ['file' => 'app/A.php', 'line' => 1]],
                        ['sql' => 'select ?', 'bindings' => [2], 'duration_ms' => 10, 'callsite' => ['file' => 'app/A.php', 'line' => 1]],
                        ['sql' => 'select ?', 'bindings' => [3], 'duration_ms' => 10, 'callsite' => ['file' => 'app/A.php', 'line' => 1]],
                    ],
                ],
            ],
            'exceptions' => ['summary' => ['count' => 1], 'payload' => ['items' => []]],
            'cache' => ['summary' => ['hits' => 1, 'misses' => 4], 'payload' => ['items' => []]],
        ],
    ];

    $findings = $analyzer->analyze($profile);
    $ruleIds = array_column($findings, 'rule_id');

    expect($ruleIds)->toBe([
        'request.error',
        'request.slow',
        'query.slow',
        'query.n_plus_one',
        'cache.high_miss_rate',
        'collector.truncated',
    ])->and($findings[1])->toMatchArray([
        'section' => 'timeline',
        'action' => [
            'label' => 'Review request timing',
            'section' => 'timeline',
        ],
    ])->and($findings[3])->toMatchArray([
        'severity' => 'warning',
        'section' => 'queries',
    ])->and($findings[3]['evidence']['count'])->toBe(3)
        ->and($findings[3]['evidence']['shared_callsite'])->toBe([
            'file' => 'app/A.php',
            'line' => 1,
        ])
        ->and($findings[5])->toMatchArray([
            'summary' => 'Showing 3 of 5 queries.',
            'evidence' => ['collector' => 'queries', 'retained' => 3, 'total' => 5, 'dropped' => 2],
        ]);
});

it('limits the number of findings', function () {
    $analyzer = new ProfileAnalyzer(new QueryAnalyzer, maxFindings: 1);

    $findings = $analyzer->analyze([
        'metrics' => ['duration_ms' => 2_000],
        'sections' => [
            'request' => ['summary' => ['status' => 500]],
            'exceptions' => ['summary' => ['count' => 1]],
        ],
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['rule_id'])->toBe('request.error');
});

it('keeps diagnostic query findings ahead of collector-limit notes', function () {
    $analyzer = new ProfileAnalyzer(new QueryAnalyzer, maxFindings: 2);

    $findings = $analyzer->analyze([
        'metrics' => ['duration_ms' => 10],
        'sections' => [
            'request' => ['summary' => ['status' => 200]],
            'queries' => [
                'label' => 'Queries',
                'summary' => ['count' => 5, 'retained_count' => 3, 'dropped_count' => 2],
                'payload' => [
                    'items' => [
                        ['sql' => 'select ?', 'bindings' => [1], 'callsite' => ['file' => 'app/A.php', 'line' => 1]],
                        ['sql' => 'select ?', 'bindings' => [2], 'callsite' => ['file' => 'app/A.php', 'line' => 1]],
                        ['sql' => 'select ?', 'bindings' => [3], 'callsite' => ['file' => 'app/A.php', 'line' => 1]],
                    ],
                ],
            ],
        ],
    ]);

    expect(array_column($findings, 'rule_id'))->toBe([
        'query.n_plus_one',
        'collector.truncated',
    ]);
});

it('suppresses expected infrastructure and tiny repeated queries while keeping useful groups', function () {
    $findings = (new ProfileAnalyzer(new QueryAnalyzer))->analyze([
        'metrics' => ['duration_ms' => 20],
        'sections' => [
            'request' => ['summary' => ['status' => 200]],
            'queries' => ['payload' => ['items' => [
                ['sql' => 'select * from "sessions" where "id" = ?', 'bindings' => [1]],
                ['sql' => 'select * from "sessions" where "id" = ?', 'bindings' => [1]],
                ['sql' => 'select * from "cache" where "key" in (?)', 'bindings' => ['a']],
                ['sql' => 'select * from "cache" where "key" in (?)', 'bindings' => ['b']],
                ['sql' => 'select * from "work_orders" where "status" = ?', 'bindings' => ['active']],
                ['sql' => 'select * from "work_orders" where "status" = ?', 'bindings' => ['active']],
                ['sql' => 'select * from "facilities" where "active" = ?', 'bindings' => [1], 'duration_ms' => 2],
                ['sql' => 'select * from "facilities" where "active" = ?', 'bindings' => [1], 'duration_ms' => 2],
                ['sql' => 'select * from "facilities" where "active" = ?', 'bindings' => [1], 'duration_ms' => 2],
            ]]],
        ],
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0])->toMatchArray([
            'rule_id' => 'query.repeated',
            'summary' => '3 identical query executions added 6 ms.',
            'action' => ['label' => 'Review grouped queries', 'section' => 'queries', 'filter' => 'repeated'],
        ]);
});

it('promotes explicit HTTP authorization and validation failures before heuristics', function () {
    $findings = (new ProfileAnalyzer(new QueryAnalyzer))->analyze([
        'metrics' => ['duration_ms' => 20],
        'sections' => [
            'request' => ['summary' => ['status' => 200]],
            'http_client' => ['payload' => ['items' => [[
                'method' => 'GET',
                'url' => 'https://api.example.test/orders',
                'failed' => true,
                'exception_message' => 'Connection timed out',
                'callsite' => ['file' => 'app/Orders/Client.php', 'line' => 20],
            ]]]],
            'authorization' => ['payload' => ['items' => [[
                'ability' => 'delete',
                'result' => 'denied',
                'handler' => 'App\\Policies\\WorkOrderPolicy@delete',
            ]]]],
            'validation' => ['payload' => ['items' => [[
                'fields' => ['title', 'facility_id'],
                'rules' => ['title' => ['Required']],
                'callsite' => ['file' => 'app/Http/Requests/StoreWorkOrderRequest.php', 'line' => 24],
            ]]]],
        ],
    ]);

    expect(array_column($findings, 'rule_id'))->toBe([
        'http.failed',
        'authorization.denied',
        'validation.failed',
    ])->and($findings[0]['summary'])->toBe('GET request to api.example.test failed.')
        ->and($findings[1]['why'])->toBe('WorkOrderPolicy@delete returned a denied result.')
        ->and($findings[1]['next'])->toBe('Check the user, arguments, policy or Gate, and expected permission for this action.')
        ->and($findings[1]['action']['filter'])->toBe('denied')
        ->and($findings[2]['summary'])->toBe('Validation failed for title, facility_id.')
        ->and($findings[2]['location'])->toBe([
            'file' => 'app/Http/Requests/StoreWorkOrderRequest.php',
            'line' => 24,
        ]);
});

it('explains validation messages carried from a previous request', function () {
    $findings = (new ProfileAnalyzer(new QueryAnalyzer))->analyze([
        'metrics' => ['duration_ms' => 20],
        'sections' => [
            'request' => ['summary' => ['status' => 200]],
            'validation' => ['payload' => ['items' => [[
                'fields' => ['email'],
                'from_previous_request' => true,
            ]]]],
        ],
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0])->toMatchArray([
            'rule_id' => 'validation.failed',
            'why' => 'Laravel carried these errors into this page from the previous request.',
            'next' => 'Inspect the messages, then reproduce the failed request to capture its rules and source.',
        ]);
});

it('reports omitted query transaction events as collector evidence', function () {
    $findings = (new ProfileAnalyzer(new QueryAnalyzer))->analyze([
        'metrics' => ['duration_ms' => 10],
        'sections' => [
            'request' => ['summary' => ['status' => 200]],
            'queries' => [
                'label' => 'Queries',
                'summary' => [
                    'count' => 0,
                    'retained_count' => 0,
                    'dropped_count' => 0,
                    'transaction_count' => 3,
                    'transaction_retained_count' => 1,
                    'transaction_dropped_count' => 2,
                ],
                'payload' => [
                    'items' => [],
                    'transactions' => [['kind' => 'begin']],
                ],
            ],
        ],
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0])->toMatchArray([
            'rule_id' => 'collector.truncated',
            'section' => 'queries',
            'summary' => 'Showing 1 of 3 query transaction events.',
            'evidence' => [
                'collector' => 'query_transactions',
                'retained' => 1,
                'total' => 3,
                'dropped' => 2,
            ],
        ]);
});
