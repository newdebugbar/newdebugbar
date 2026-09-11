<?php

use Illuminate\Support\Str;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Testing\ProfileAssertions;
use PHPUnit\Framework\AssertionFailedError;

$assertionProfile = function (array $queries = [], int $status = 200): array {
    return [
        'id' => (string) Str::uuid(),
        'metrics' => ['duration_ms' => 20, 'peak_memory_mb' => 8],
        'inspectors' => [
            'request' => ['summary' => ['method' => 'GET', 'status' => $status], 'payload' => ['path' => '/']],
            'queries' => ['summary' => ['count' => count($queries), 'duration_ms' => array_sum(array_column($queries, 'duration_ms'))], 'payload' => ['items' => $queries]],
            'exceptions' => ['summary' => ['count' => 0], 'payload' => ['items' => []]],
        ],
    ];
};

it('asserts profile budgets through the production analyzers', function () use ($assertionProfile) {
    $profile = $assertionProfile([
        ['sql' => 'select 1', 'duration_ms' => 2],
        ['sql' => 'select 2', 'duration_ms' => 3],
    ]);
    app(ProfileStore::class)->put($profile);

    ProfileAssertions::stored($profile['id'])
        ->assertNoRepeatedQueries()
        ->assertNoLikelyNPlusOneQueries()
        ->assertQueryCountAtMost(2)
        ->assertQueryTimeAtMost(5)
        ->assertDurationAtMost(20)
        ->assertPeakMemoryAtMost(8)
        ->assertNoErrors();
});

it('fails when shared findings or budgets exceed expectations', function (Closure $assertion, string $message) {
    expect(fn () => $assertion())->toThrow(AssertionFailedError::class, $message);
})->with([
    'repeated queries' => [
        fn () => ProfileAssertions::for($assertionProfile([
            ['sql' => 'select ?', 'bindings' => [1], 'duration_ms' => 1],
            ['sql' => 'select ?', 'bindings' => [2], 'duration_ms' => 1],
        ]))->assertNoRepeatedQueries(),
        'repeated query patterns',
    ],
    'query count' => [
        fn () => ProfileAssertions::for($assertionProfile([
            ['sql' => 'select 1', 'duration_ms' => 1],
        ]))->assertQueryCountAtMost(0),
        'query count exceeded',
    ],
    'errors' => [
        fn () => ProfileAssertions::for($assertionProfile(status: 500))->assertNoErrors(),
        'error response or exception',
    ],
]);

it('fails clearly when a stored profile is missing', function () {
    ProfileAssertions::stored((string) Str::uuid());
})->throws(AssertionFailedError::class, 'was not found');
