<?php

use NewDebugBar\Collectors\CacheCollector;
use NewDebugBar\Collectors\ExceptionCollector;
use NewDebugBar\Collectors\LogCollector;
use NewDebugBar\Collectors\QueryCollector;
use NewDebugBar\Collectors\RedisCollector;
use NewDebugBar\Collectors\ValidationCollector;
use NewDebugBar\Support\Redactor;

it('keeps normalized exception cause evidence structured and redacted', function () {
    $collector = new ExceptionCollector(new Redactor(maxDepth: 5), maxItems: 1);

    $collector->record([
        'class' => RuntimeException::class,
        'causes' => [[
            'class' => LogicException::class,
            'message' => 'Earlier failure.',
            'frames' => [
                'application' => [[
                    'file' => 'app/Actions/Run.php',
                    'line' => 42,
                    'function' => 'run',
                ]],
                'vendor' => [],
            ],
            'source' => [
                'file' => 'app/Actions/Run.php',
                'lines' => [[
                    'number' => 42,
                    'code' => 'throw new LogicException;',
                    'token' => 'private-token',
                ]],
            ],
        ]],
    ]);

    $cause = $collector->payload()['items'][0]['causes'][0];

    expect($cause['frames']['application'][0])
        ->toBe([
            'file' => 'app/Actions/Run.php',
            'line' => 42,
            'function' => 'run',
        ])
        ->and($cause['source']['lines'][0]['code'])->toBe('throw new LogicException;')
        ->and($cause['source']['lines'][0]['token'])->toBe('[redacted]');
});

it('counts dropped collector items without retaining their payload', function () {
    $collector = new QueryCollector(new Redactor, maxItems: 1);

    $collector->record(['sql' => 'select 1', 'duration_ms' => 1.25]);
    $collector->record(['sql' => 'select 2', 'duration_ms' => 4.75]);

    expect($collector->summary())->toBe([
        'count' => 2,
        'retained_count' => 1,
        'dropped_count' => 1,
        'truncated' => true,
        'duration_ms' => 6.0,
        'transaction_count' => 0,
        'transaction_retained_count' => 0,
        'transaction_dropped_count' => 0,
        'rollback_count' => 0,
    ])->and($collector->payload())->toBe([
        'items' => [[
            'sql' => 'select 1',
            'duration_ms' => 1.25,
            'source_preserved' => true,
            'runnable_available' => false,
        ]],
        'transactions' => [],
    ]);

    $collector->reset();

    expect($collector->summary())->toBe([
        'count' => 0,
        'retained_count' => 0,
        'dropped_count' => 0,
        'truncated' => false,
        'duration_ms' => 0.0,
        'transaction_count' => 0,
        'transaction_retained_count' => 0,
        'transaction_dropped_count' => 0,
        'rollback_count' => 0,
    ])->and($collector->payload())->toBe([
        'items' => [],
        'transactions' => [],
    ]);
});

it('keeps query counts separate while sharing one collector retention limit', function () {
    $collector = new QueryCollector(new Redactor, maxItems: 2);
    $collector->record(['sql' => 'select 1', 'bindings' => [], 'duration_ms' => 1]);
    $collector->recordTransaction(['kind' => 'begin', 'connection' => 'testing', 'at_ms' => 1.2]);
    $collector->recordTransaction(['kind' => 'rollback', 'connection' => 'testing', 'at_ms' => 1.5]);

    expect($collector->summary())
        ->count->toBe(1)
        ->truncated->toBeTrue()
        ->transaction_count->toBe(2)
        ->transaction_retained_count->toBe(1)
        ->transaction_dropped_count->toBe(1)
        ->rollback_count->toBe(1)
        ->and($collector->payload())
        ->transactions->toHaveCount(1);
});

it('masks unnamed string query bindings by default', function () {
    $collector = new QueryCollector(new Redactor, maxItems: 2);

    $collector->record([
        'sql' => 'select * from users where email = ? and id = ?',
        'bindings' => ['patient@example.com', 42],
        'duration_ms' => 1.5,
    ]);

    expect($collector->payload()['items'][0]['bindings'])->toBe(['[string]', 42]);
});

it('removes literal values from captured sql', function () {
    $collector = new QueryCollector(new Redactor, maxItems: 1);

    $collector->record([
        'sql' => "select * from users where email = 'private@example.com'",
        'bindings' => [],
        'duration_ms' => 1,
    ]);

    expect($collector->payload()['items'][0]['sql'])
        ->toBe("select * from users where email = '[string]'");
});

it('includes dropped items in cache and log summaries', function () {
    $cache = new CacheCollector(new Redactor, maxItems: 1);
    $logs = new LogCollector(new Redactor, maxItems: 1);

    $cache->record(['operation' => 'hit']);
    $cache->record(['operation' => 'miss']);
    $logs->record(['level' => 'info']);
    $logs->record(['level' => 'error']);

    expect($cache->summary())->toBe([
        'count' => 2,
        'retained_count' => 1,
        'dropped_count' => 1,
        'truncated' => true,
        'hits' => 1,
        'misses' => 1,
        'writes' => 0,
        'forgets' => 0,
        'flushes' => 0,
        'failures' => 0,
        'timed_count' => 0,
        'duration_ms' => 0.0,
    ])->and($logs->summary())->toBe([
        'count' => 2,
        'retained_count' => 1,
        'dropped_count' => 1,
        'truncated' => true,
        'errors' => 1,
        'attention_count' => 1,
    ]);
});

it('bounds cache timing identities and counts a batch once', function () {
    $cache = new CacheCollector(new Redactor, maxItems: 2);

    $cache->record(['operation' => 'write', 'duration_ms' => 1.2, 'duration_id' => 'batch-1']);
    $cache->record(['operation' => 'write', 'duration_ms' => 1.5, 'duration_id' => 'batch-1']);
    $cache->record(['operation' => 'hit', 'duration_ms' => 0.4, 'duration_id' => 'read-1']);
    $cache->record(['operation' => 'miss', 'duration_ms' => 9.0, 'duration_id' => 'dropped-timing']);

    expect($cache->summary())
        ->count->toBe(4)
        ->retained_count->toBe(2)
        ->dropped_count->toBe(2)
        ->timed_count->toBe(2)
        ->duration_ms->toBe(1.9);
});

it('attaches the rendered response to every retained validation failure', function () {
    $validation = new ValidationCollector(new Redactor, maxItems: 2);
    $validation->record(['fields' => ['email']]);
    $validation->record(['fields' => ['name']]);

    expect($validation->hasFailures())->toBeTrue();

    $validation->attachResponseStatus(200);

    expect(array_column($validation->payload()['items'], 'response_status'))->toBe([200, 200]);

    $validation->reset();

    expect($validation->hasFailures())->toBeFalse();
});

it('collapses duplicate MIME mail logs into a link to the Mail collector', function () {
    $logs = new LogCollector(new Redactor, maxItems: 5);

    $logs->record([
        'level' => 'debug',
        'message' => "Message-ID: <local@example.test>\nMIME-Version: 1.0\nContent-Type: multipart/alternative; boundary=private\n\nprivate body",
        'context' => ['private' => 'duplicate'],
    ]);

    expect($logs->payload()['items'][0])->toMatchArray([
        'message' => 'Mail content captured. Open the Mail inspector for its preview.',
        'context' => ['linked_inspector' => 'mail', 'collapsed' => 'mime_message'],
    ])->and($logs->payload()['items'][0]['message'])->not->toContain('private body');
});

it('removes a cache command even after the Redis item limit is reached', function () {
    $redis = new RedisCollector(new Redactor, maxItems: 1);
    $redis->record(['command' => 'GET', 'duration_ms' => 1.25, 'failed' => false]);
    $redis->record(['command' => 'SETEX', 'duration_ms' => 0.5, 'failed' => false]);

    $redis->excludeCacheOperation('write');

    expect($redis->summary())->toBe([
        'count' => 1,
        'retained_count' => 1,
        'dropped_count' => 0,
        'truncated' => false,
        'duration_ms' => 1.25,
        'failed_count' => 0,
    ]);
});

it('does not remove an older direct Redis command for a dropped cache command', function () {
    $redis = new RedisCollector(new Redactor, maxItems: 1);
    $redis->record([
        'command' => 'GET',
        'key_hashes' => ['direct-key'],
        'duration_ms' => 1.25,
        'failed' => false,
    ]);
    $redis->record([
        'command' => 'GET',
        'key_hashes' => ['cache-key'],
        'duration_ms' => 0.5,
        'failed' => false,
    ]);

    $redis->excludeCacheOperation('hit');

    expect($redis->summary())->toBe([
        'count' => 1,
        'retained_count' => 1,
        'dropped_count' => 0,
        'truncated' => false,
        'duration_ms' => 1.25,
        'failed_count' => 0,
    ])->and($redis->payload()['items'][0]['key_hashes'])->toBe(['direct-key']);
});
