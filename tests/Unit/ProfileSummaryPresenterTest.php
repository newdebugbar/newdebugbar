<?php

use NewDebugBar\Presentation\ProfileSummaryPresenter;
use NewDebugBar\Support\Redactor;

$summaryProfile = function (string $requestType, array $request = [], array $sections = []): array {
    return [
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'environment' => 'testing',
        'metrics' => ['duration_ms' => 10, 'peak_memory_mb' => 8],
        'findings' => [],
        'sections' => [
            'request' => [
                'summary' => ['method' => 'POST', 'status' => 200],
                'payload' => [
                    'path' => '/example',
                    'request_type' => $requestType,
                    'response_size_bytes' => 13_752,
                    ...$request,
                ],
            ],
            'queries' => ['summary' => []],
            'cache' => ['summary' => []],
            'exceptions' => ['summary' => ['count' => 0]],
            ...$sections,
        ],
    ];
};

it('summarizes status families and response sizes for the request header', function (int $status, string $meaning) use ($summaryProfile) {
    $profile = $summaryProfile('full_page', [
        'response_size_bytes' => 2_621_440,
    ]);
    $profile['sections']['request']['summary']['status'] = $status;

    $summary = (new ProfileSummaryPresenter(new Redactor))->present($profile);

    expect($summary['status_meaning'])->toBe($meaning)
        ->and($summary['response_size'])->toBe('2.50 MB');
})->with([
    'informational' => [101, 'Informational'],
    'success' => [204, 'Success'],
    'redirect' => [302, 'Redirect'],
    'client error' => [404, 'Client error'],
    'server error' => [503, 'Server error'],
]);

it('summarizes redirect destinations', function () use ($summaryProfile) {
    $summary = (new ProfileSummaryPresenter(new Redactor))->present($summaryProfile('redirect', [
        'response_headers' => ['location' => ['/work-orders']],
    ]));

    expect($summary['activity'])->toBe('Redirected to /work-orders')
        ->and($summary['title'])->toBe('Redirected to /work-orders')
        ->and($summary['request_type_label'])->toBe('Redirect');
});

it('prepares request titles and type labels for the request picker', function (string $type, string $label) use ($summaryProfile) {
    $summary = (new ProfileSummaryPresenter(new Redactor))->present($summaryProfile($type, ['path' => '/patients']));

    expect($summary['request_type_label'])->toBe($label)
        ->and($summary['title'])->not->toBeEmpty();
})->with([
    ['ajax', 'Ajax'], ['artisan', 'Command'], ['cli', 'CLI'], ['download', 'Download'],
    ['full_page', 'Page'], ['json', 'JSON'], ['queue', 'Worker'], ['stream', 'Stream'],
    ['test', 'Test'], ['unknown', 'Request'],
]);

it('formats request and query durations for every toolbar placement', function () use ($summaryProfile) {
    $profile = $summaryProfile('full_page');
    $profile['metrics']['duration_ms'] = 1_453.51;
    $profile['sections']['queries']['summary']['total_time_ms'] = 0.19;

    $summary = (new ProfileSummaryPresenter(new Redactor))->present($profile);

    expect($summary)
        ->duration_ms->toBe(1_453.51)
        ->duration_label->toBe('1.45 s')
        ->query_time_ms->toBe(0.19)
        ->query_time_label->toBe('190 µs');
});
