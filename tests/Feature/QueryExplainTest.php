<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use NewDebugBar\Analysis\QueryAnalyzer;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\QueryExplainer;

it('offers runnable SQL and runs manual SQLite explain with the default bindings', function () {
    $response = $this->get('/profiled', ['Accept' => 'text/html'])->assertOk();
    $id = $response->headers->get('X-NewDebugBar-Profile');
    $stored = app(ProfileStore::class)->get($id);
    $profile = app(ProfilePresenter::class)->present($stored);
    $query = $profile['sections']['queries']['payload']['items'][0];

    expect($query)
        ->driver->toBe('sqlite')
        ->binding_policy->toBe('full')
        ->bindings_complete->toBeTrue()
        ->source_preserved->toBeTrue()
        ->runnable_available->toBeTrue()
        ->runnable_sql->toContain('select 1 as number')
        ->and($profile['sections']['queries']['summary']['count'])->toBe(3);

    $result = app(QueryExplainer::class)->explain($query);

    expect($result)
        ->driver->toBe('sqlite')
        ->mode->toBe('EXPLAIN QUERY PLAN')
        ->rows->not->toBeEmpty();

    Livewire::test(DebugBar::class, ['profileId' => $id])
        ->call('loadSection', 'queries')
        ->call('explainQuery', 1)
        ->assertSet('queryExplains.1.driver', 'sqlite')
        ->assertSet('queryExplainErrors', [])
        ->assertDispatched('newdebugbar-query-explained', function (string $name, array $params): bool {
            return $name === 'newdebugbar-query-explained'
                && $params['execution'] === 1
                && $params['explain']['driver'] === 'sqlite'
                && $params['error'] === null;
        });
});

it('rejects unsafe incomplete and mutating explain requests before touching the database', function (array $query) {
    expect(fn () => app(QueryExplainer::class)->explain($query))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'safe binding policy' => [[
        'sql' => 'select ? as number',
        'bindings' => [1],
        'source_preserved' => true,
        'binding_policy' => 'safe',
        'bindings_complete' => false,
        'connection' => 'testing',
    ]],
    'multiple statements' => [[
        'sql' => 'select 1; delete from users',
        'bindings' => [],
        'source_preserved' => true,
        'binding_policy' => 'full',
        'bindings_complete' => true,
        'connection' => 'testing',
    ]],
    'write query' => [[
        'sql' => 'delete from users',
        'bindings' => [],
        'source_preserved' => true,
        'binding_policy' => 'full',
        'bindings_complete' => true,
        'connection' => 'testing',
    ]],
]);

it('turns database failures into safe actionable guidance', function (string $sql, string $message, string $privateValue) {
    $query = [
        'sql' => $sql,
        'bindings' => [],
        'source_preserved' => true,
        'binding_policy' => 'full',
        'bindings_complete' => true,
        'connection' => 'testing',
    ];

    try {
        app(QueryExplainer::class)->explain($query);
    } catch (InvalidArgumentException $exception) {
        $failure = $exception;
    }

    expect($failure ?? null)
        ->toBeInstanceOf(InvalidArgumentException::class)
        ->and($failure->getMessage())
        ->toBe($message)
        ->not->toContain($privateValue, 'SQLSTATE', 'Database:')
        ->and($failure->getPrevious())
        ->toBeInstanceOf(QueryException::class);
})->with([
    'missing SQLite function' => [
        'select ndb_private_pause() as ready',
        'SQLite cannot find a function used by this query. Check its name or register it on the query connection, then reload.',
        'ndb_private_pause',
    ],
    'missing SQLite table' => [
        'select * from ndb_private_table',
        'SQLite cannot find a table used by this query. Check the database and confirm the table still exists.',
        'ndb_private_table',
    ],
    'unclassified database failure' => [
        'select from ndb_private_table',
        'Copy the query from Overview, then run EXPLAIN in your database client against the same database.',
        'ndb_private_table',
    ],
]);

it('explains when the query database connection is no longer available', function () {
    $query = [
        'sql' => 'select 1',
        'bindings' => [],
        'source_preserved' => true,
        'binding_policy' => 'full',
        'bindings_complete' => true,
        'connection' => 'private_missing_connection',
    ];

    expect(fn () => app(QueryExplainer::class)->explain($query))
        ->toThrow(
            InvalidArgumentException::class,
            'This query\'s database connection is unavailable. Restore it, then reload.',
        );
});

it('keeps repeated execution evidence in one bounded workspace record', function () {
    $queries = array_map(fn (int $binding): array => [
        'sql' => 'select ? as number',
        'bindings' => [$binding],
        'bindings_complete' => true,
        'binding_policy' => 'full',
        'runnable_available' => true,
        'runnable_sql' => 'select '.$binding.' as number',
        'duration_ms' => $binding,
        'connection' => 'testing',
        'driver' => 'sqlite',
        'callsite' => ['file' => '/app/Queries/NumberQuery.php', 'line' => 14],
        'stack' => [['file' => '/app/Queries/NumberQuery.php', 'line' => 14, 'function' => 'loadNumbers']],
    ], [1, 2, 3]);
    $analysis = (new QueryAnalyzer)->analyze($queries, 20);
    $section = [
        'summary' => [...$analysis['summary'], 'count' => 3],
        'payload' => $analysis,
    ];

    $html = Blade::render('<x-newdebugbar::query-section :section="$section" />', ['section' => $section]);

    expect(preg_match('/<script type="application\/json" data-ndb-query-payload>\s*(?<payload>[^<]+)\s*<\/script>/', $html, $matches))
        ->toBe(1);

    $records = json_decode(base64_decode(trim($matches['payload']), true), true, 512, JSON_THROW_ON_ERROR);

    expect($records)
        ->toHaveCount(1)
        ->and($records[0]['repeated'])->toBeTrue()
        ->and($records[0]['count'])->toBe(3)
        ->and($records[0]['driver'])->toBe('sqlite')
        ->and($records[0]['executions'])->toHaveCount(3)
        ->and(array_column($records[0]['executions'], 'driver'))->toBe(['sqlite', 'sqlite', 'sqlite'])
        ->and($records[0]['executions'][2])->not->toHaveKeys(['bindings', 'runnable_sql', 'bindings_complete'])
        ->and($records[0]['executions'][2])->not->toHaveKey('source_short_label')
        ->and($records[0]['executions'][2]['display_sql'])->toBe('select 3 as number')
        ->and($records[0]['executions'][2]['source_label'])->toBe('/app/Queries/NumberQuery.php:14')
        ->and($records[0]['executions'][2]['stack'][0]['function'])->toBe('loadNumbers')
        ->and($records[0]['executions'][2]['display_sql_complete'])->toBeTrue();

    expect($html)
        ->toContain('data-ndb-query-workspace')
        ->toContain('data-ndb-query-group="group-')
        ->toContain('data-ndb-query-detail')
        ->toContain('data-ndb-inspector-disclosure')
        ->not->toContain('data-ndb-query-group-executions');
});

it('formats query durations with an adaptive unit', function () {
    $items = collect([
        0.0,
        0.19,
        0.99,
        1.0,
        12.34,
        999.99,
        1000.0,
        1453.51,
    ])->map(fn (float $duration, int $index): array => [
        'execution' => $index + 1,
        'sql' => 'select '.$index,
        'normalized_sql' => 'select '.$index,
        'duration_ms' => $duration,
        'connection' => 'testing',
        'driver' => 'sqlite',
        'query_type' => 'read',
        'bindings' => [],
    ])->all();
    $section = [
        'summary' => ['count' => count($items), 'total_time_ms' => 1466.04],
        'payload' => ['items' => $items, 'repeated_groups' => []],
    ];
    $html = Blade::render('<x-newdebugbar::query-section :section="$section" />', ['section' => $section]);

    expect(preg_match('/<script type="application\/json" data-ndb-query-payload>\s*(?<payload>[^<]+)\s*<\/script>/', $html, $matches))
        ->toBe(1);

    $records = json_decode(base64_decode(trim($matches['payload']), true), true, 512, JSON_THROW_ON_ERROR);

    expect(array_column($records, 'duration_label'))
        ->toBe(['0 µs', '190 µs', '990 µs', '1 ms', '12.34 ms', '999.99 ms', '1 s', '1.45 s'])
        ->and($html)
        ->toContain('1.47 s total');
});

it('gives a slow repeated query the stronger row treatment', function () {
    $queries = array_map(fn (int $duration): array => [
        'sql' => 'select ? as number',
        'bindings' => [$duration],
        'duration_ms' => $duration,
        'connection' => 'testing',
        'driver' => 'sqlite',
    ], [120, 20, 10]);
    $analysis = (new QueryAnalyzer)->analyze($queries, 200);
    $section = [
        'summary' => [...$analysis['summary'], 'count' => 3],
        'payload' => $analysis,
    ];
    $html = Blade::render('<x-newdebugbar::query-section :section="$section" />', ['section' => $section]);

    expect($html)
        ->toContain('Slow repeated query.')
        ->toContain('ndb:bg-red-50\/70', 'ndb:bg-red-50\/90')
        ->not->toContain('ndb:bg-amber-50\/70', 'ndb:bg-amber-50\/90');
});
