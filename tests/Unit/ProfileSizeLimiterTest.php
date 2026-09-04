<?php

use NewDebugBar\Analysis\ProfileAnalyzer;
use NewDebugBar\Analysis\QueryAnalyzer;
use NewDebugBar\Support\ProfileSanitizer;
use NewDebugBar\Support\ProfileSizeLimiter;
use NewDebugBar\Support\Redactor;
use Symfony\Component\Mime\Email;

it('preserves ordinary profiles and their serialized bytes exactly', function () {
    $profile = ['id' => 'example', 'sections' => ['request' => ['summary' => ['count' => 1], 'payload' => ['path' => '/example']]]];

    expect((new ProfileSizeLimiter)->encode($profile))
        ->toBe(json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
});

it('omits whole large MIME values while preserving valid retained downloads', function () {
    $eml = (new Email)->from('sender@example.test')->to('recipient@example.test')->text('Still valid')->toString();
    $profile = ['sections' => ['mail' => [
        'summary' => ['count' => 2, 'retained_count' => 2],
        'payload' => ['items' => [
            ['preview' => ['eml' => (new Email)->from('sender@example.test')->to('recipient@example.test')->text(str_repeat('a', 10_100_000))->toString(), 'html' => '<b>Keep me</b>', 'text' => 'Keep me', 'attachments' => [], 'attachments_omitted' => 0]],
            ['preview' => ['eml' => $eml, 'attachments' => [['name' => 'keep.txt', 'body_base64' => base64_encode('Keep this attachment')]], 'attachments_omitted' => 0]],
        ]],
    ]]];
    $encoded = (new ProfileSizeLimiter)->encode($profile);
    $stored = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

    expect(strlen($encoded))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES)
        ->and($stored['sections']['mail']['payload']['items'][0]['preview']['eml'])->toBeNull()
        ->and($stored['sections']['mail']['payload']['items'][0]['preview']['eml_omitted_reason'])->toBe('profile_budget')
        ->and($stored['sections']['mail']['payload']['items'][0]['preview']['html'])->toBe('<b>Keep me</b>')
        ->and($stored['sections']['mail']['payload']['items'][1]['preview']['eml'])->toBe($eml)
        ->and(base64_decode($stored['sections']['mail']['payload']['items'][1]['preview']['attachments'][0]['body_base64'], true))->toBe('Keep this attachment')
        ->and($stored['sections']['mail']['summary'])->toMatchArray(['count' => 2, 'retained_count' => 2, 'storage_truncated' => true, 'storage_omitted_items' => 0])
        ->and($stored['storage'])->toMatchArray(['truncated' => true, 'max_bytes' => 10_000_000, 'omitted_value_count' => 1, 'omitted_item_count' => 0, 'omitted_section_count' => 0, 'omitted_paths_truncated' => 0])
        ->and($stored['storage']['original_bytes'])->toBe(strlen(json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))
        ->and($stored['storage']['omitted_values'])->toBe(['/sections/mail/payload/items/0/preview/eml']);
});

it('omits attachment bodies before previews and reports existing omissions honestly', function () {
    $profile = ['sections' => ['mail' => ['summary' => ['count' => 1], 'payload' => ['items' => [['preview' => [
        'eml' => null,
        'eml_omitted_reason' => 'capture_disabled',
        'html' => '<b>Retained</b>',
        'attachments' => [
            ['name' => 'large.bin', 'body_base64' => base64_encode(str_repeat('a', 8_000_000)), 'body_omitted_reason' => null],
            ['name' => 'omitted.bin', 'body_base64' => null, 'body_omitted_reason' => 'attachment_budget'],
        ],
        'attachments_omitted' => 1,
    ]]]]]]];
    $encoded = (new ProfileSizeLimiter)->encode($profile);
    $stored = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
    $preview = $stored['sections']['mail']['payload']['items'][0]['preview'];

    expect(strlen($encoded))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES)
        ->and($preview['eml_omitted_reason'])->toBe('capture_disabled')
        ->and($preview['html'])->toBe('<b>Retained</b>')
        ->and($preview['attachments'][0])->toMatchArray(['name' => 'large.bin', 'body_base64' => null, 'body_omitted_reason' => 'profile_budget'])
        ->and($preview['attachments'][1]['body_omitted_reason'])->toBe('attachment_budget')
        ->and($preview['attachments_omitted'])->toBe(2);
});

it('omits complete html and text fields only after bulk downloads are gone', function () {
    $profile = ['sections' => ['mail' => ['summary' => ['count' => 1], 'payload' => ['items' => [['preview' => [
        'html' => str_repeat('<b>é</b>', 1_000_000),
        'text' => 'Retained text',
        'attachments' => [],
    ]]]]]]];
    $encoded = (new ProfileSizeLimiter)->encode($profile);
    $stored = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

    expect(strlen($encoded))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES)
        ->and($stored['sections']['mail']['payload']['items'][0]['preview'])->toMatchArray([
            'html' => null,
            'html_omitted_reason' => 'profile_budget',
            'text' => 'Retained text',
            'truncated' => true,
        ]);
});

it('keeps retained counts exact after storage loss without rewriting capture totals or drops', function (int $dropped) {
    $rows = array_map(fn (int $index): array => ['index' => $index, 'value' => str_repeat('é', 2_000)], range(0, 999));
    $profile = ['sections' => [
        'logs' => ['label' => 'Logs', 'summary' => ['count' => 1_000 + $dropped, 'retained_count' => 1_000, 'dropped_count' => $dropped, 'duration_ms' => 456.7], 'payload' => ['items' => $rows]],
        'queries' => ['summary' => ['count' => 1], 'payload' => ['items' => [['sql' => 'select 1']]]],
    ]];
    $encoded = (new ProfileSizeLimiter)->encode($profile);
    $stored = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
    $retained = count($stored['sections']['logs']['payload']['items']);

    expect(strlen($encoded))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES)
        ->and($retained)->toBeGreaterThan(0)->toBeLessThan(1_000)
        ->and($stored['sections']['logs']['payload']['items'])->toBe(array_slice($rows, 0, $retained))
        ->and($stored['sections']['queries'])->toBe($profile['sections']['queries'])
        ->and($stored['sections']['logs']['summary'])->toMatchArray([
            'count' => 1_000 + $dropped, 'retained_count' => $retained, 'dropped_count' => $dropped, 'duration_ms' => 456.7,
            'storage_truncated' => true, 'storage_omitted_items' => 1_000 - $retained,
        ])
        ->and($stored['storage']['omitted_items'])->toBe(['/sections/logs/payload/items' => 1_000 - $retained])
        ->and($stored['storage']['omitted_item_count'])->toBe(1_000 - $retained);

    $findings = array_values(array_filter(
        (new ProfileAnalyzer(new QueryAnalyzer))->analyze($stored),
        fn (array $finding): bool => $finding['rule_id'] === 'collector.truncated',
    ));
    expect($findings)->toHaveCount($dropped > 0 ? 1 : 0);

    if ($dropped > 0) {
        expect($findings[0]['evidence'])->toBe([
            'collector' => 'logs', 'retained' => $retained, 'total' => 1_000 + $dropped, 'dropped' => $dropped,
        ]);
    }
})->with(['storage only' => 0, 'capture and storage' => 100]);

it('preserves explicit summary count masks when storage removes records', function (string $section, string $counter, string $list) {
    $rows = array_fill(0, 1_000, ['value' => str_repeat('é', 2_000)]);
    $profile = ['sections' => [$section => [
        'summary' => [$counter => 1_000],
        'payload' => [$list => $rows],
    ]]];
    $sanitizer = new ProfileSanitizer(new Redactor(maskedPaths: ['sections.'.$section.'.summary.'.$counter]));
    $sanitized = $sanitizer->clean($profile);
    expect($sanitized['sections'][$section]['summary'][$counter])->toBe('[redacted]');

    $stored = json_decode((new ProfileSizeLimiter)->encode($sanitized), true, flags: JSON_THROW_ON_ERROR);
    $retained = count($stored['sections'][$section]['payload'][$list]);

    expect($retained)->toBeGreaterThan(0)->toBeLessThan(1_000)
        ->and($stored['sections'][$section]['summary'][$counter])->toBe('[redacted]')
        ->and($stored['sections'][$section]['summary']['storage_omitted_items'])->toBe(1_000 - $retained);
})->with([
    'log count' => ['logs', 'retained_count', 'items'],
    'query count' => ['queries', 'retained_count', 'items'],
    'transaction count' => ['queries', 'transaction_retained_count', 'transactions'],
]);

it('trims Livewire activity aliases together and counts their records once', function () {
    $rows = array_fill(0, 500, ['value' => str_repeat('a', 12_000)]);
    $profile = ['sections' => ['livewire' => [
        'summary' => ['count' => 1, 'component_count' => 1, 'activity_count' => 500, 'retained_count' => 501, 'dropped_count' => 7],
        'payload' => ['items' => $rows, 'activity' => $rows, 'components' => [['id' => 'kept']]],
    ]]];
    $encoded = (new ProfileSizeLimiter)->encode($profile);
    $stored = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
    $payload = $stored['sections']['livewire']['payload'];
    $omitted = 500 - count($payload['activity']);

    expect(strlen($encoded))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES)
        ->and($payload['items'])->toBe($payload['activity'])
        ->and($payload['components'])->toBe([['id' => 'kept']])
        ->and($omitted)->toBeGreaterThan(0)
        ->and($stored['sections']['livewire']['summary'])->toMatchArray([
            'count' => 1, 'component_count' => 1, 'activity_count' => 500, 'retained_count' => 501 - $omitted,
            'dropped_count' => 7, 'storage_omitted_items' => $omitted,
        ])
        ->and($stored['storage']['omitted_items'])->toBe(['/sections/livewire/payload/activity' => $omitted])
        ->and($stored['storage']['omitted_item_count'])->toBe($omitted);
});

it('counts retained Livewire components separately from their activity aliases', function () {
    $components = array_fill(0, 200, ['value' => str_repeat('a', 60_000)]);
    $activity = [['name' => 'save']];
    $profile = ['sections' => ['livewire' => [
        'summary' => ['count' => 200, 'component_count' => 200, 'activity_count' => 1, 'retained_count' => 201, 'dropped_count' => 3],
        'payload' => ['items' => $activity, 'activity' => $activity, 'components' => $components],
    ]]];
    $stored = json_decode((new ProfileSizeLimiter)->encode($profile), true, flags: JSON_THROW_ON_ERROR);
    $payload = $stored['sections']['livewire']['payload'];
    $retainedComponents = count($payload['components']);

    expect($retainedComponents)->toBeGreaterThan(0)->toBeLessThan(200)
        ->and($payload['activity'])->toBe($activity)
        ->and($payload['items'])->toBe($activity)
        ->and($stored['sections']['livewire']['summary'])->toMatchArray([
            'count' => 200, 'component_count' => 200, 'activity_count' => 1, 'retained_count' => $retainedComponents + 1,
            'dropped_count' => 3, 'storage_omitted_items' => 200 - $retainedComponents,
        ])
        ->and($stored['storage']['omitted_items'])->toBe(['/sections/livewire/payload/components' => 200 - $retainedComponents])
        ->and($stored['storage']['omitted_item_count'])->toBe(200 - $retainedComponents);
});

it('updates transaction retention without subtracting transaction omissions from query rows', function () {
    $transactions = array_fill(0, 500, ['kind' => 'begin', 'details' => str_repeat('a', 24_000)]);
    $profile = ['sections' => ['queries' => [
        'label' => 'Queries',
        'summary' => [
            'count' => 1, 'retained_count' => 1, 'dropped_count' => 0, 'duration_ms' => 12.5,
            'transaction_count' => 510, 'transaction_retained_count' => 500, 'transaction_dropped_count' => 10,
        ],
        'payload' => ['items' => [['sql' => 'select 1']], 'transactions' => $transactions],
    ]]];
    $stored = json_decode((new ProfileSizeLimiter)->encode($profile), true, flags: JSON_THROW_ON_ERROR);
    $retained = count($stored['sections']['queries']['payload']['transactions']);

    expect($retained)->toBeGreaterThan(0)->toBeLessThan(500)
        ->and($stored['sections']['queries']['payload']['items'])->toBe([['sql' => 'select 1']])
        ->and($stored['sections']['queries']['summary'])->toMatchArray([
            'count' => 1, 'retained_count' => 1, 'dropped_count' => 0, 'duration_ms' => 12.5,
            'transaction_count' => 510, 'transaction_retained_count' => $retained, 'transaction_dropped_count' => 10,
            'storage_omitted_items' => 500 - $retained,
        ])
        ->and($stored['storage']['omitted_item_count'])->toBe(500 - $retained);

    $findings = array_values(array_filter(
        (new ProfileAnalyzer(new QueryAnalyzer))->analyze($stored),
        fn (array $finding): bool => $finding['rule_id'] === 'collector.truncated',
    ));
    expect($findings)->toHaveCount(1)
        ->and($findings[0]['evidence'])->toBe([
            'collector' => 'query_transactions', 'retained' => $retained, 'total' => 510, 'dropped' => 10,
        ]);
});

it('zeros retained counts after a full payload omission without counting removed records twice', function () {
    $profile = ['sections' => ['queries' => [
        'summary' => [
            'count' => 3, 'retained_count' => 2, 'dropped_count' => 1, 'duration_ms' => 12.5,
            'transaction_count' => 2, 'transaction_retained_count' => 1, 'transaction_dropped_count' => 1,
        ],
        'payload' => [
            'items' => [['sql' => 'select 1'], ['sql' => 'select 2']],
            'transactions' => [['kind' => 'begin']],
            'details' => str_repeat('a', 10_100_000),
        ],
    ]]];
    $stored = json_decode((new ProfileSizeLimiter)->encode($profile), true, flags: JSON_THROW_ON_ERROR);

    expect($stored['sections']['queries']['payload'])->toBe([])
        ->and($stored['sections']['queries']['summary'])->toMatchArray([
            'count' => 3, 'retained_count' => 0, 'dropped_count' => 1, 'duration_ms' => 12.5,
            'transaction_count' => 2, 'transaction_retained_count' => 0, 'transaction_dropped_count' => 1,
            'storage_omitted_items' => 3,
        ])
        ->and($stored['storage']['omitted_item_count'])->toBe(3)
        ->and($stored['storage']['omitted_sections'])->toBe(['queries']);
});

it('omits a remaining large payload while preserving its summary', function () {
    $profile = ['sections' => ['request' => ['summary' => ['method' => 'POST'], 'payload' => ['body' => str_repeat('a', 10_100_000)]]]];
    $stored = json_decode((new ProfileSizeLimiter)->encode($profile), true, flags: JSON_THROW_ON_ERROR);

    expect($stored['sections']['request']['payload'])->toBe([])
        ->and($stored['sections']['request']['summary'])->toMatchArray(['method' => 'POST', 'storage_truncated' => true, 'storage_omitted_items' => 0])
        ->and($stored['storage']['omitted_sections'])->toBe(['request'])
        ->and($stored['storage']['omitted_section_count'])->toBe(1);
});

it('caps omission detail paths while retaining complete omission totals', function () {
    $attachments = array_fill(0, 1_100, ['body_base64' => str_repeat('a', 10_000)]);
    $profile = ['metadata' => str_repeat('a', 9_800_000), 'sections' => ['mail' => ['summary' => [], 'payload' => ['items' => [['preview' => ['attachments' => $attachments]]]]]]];
    $encoded = (new ProfileSizeLimiter)->encode($profile);
    $stored = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

    expect(strlen($encoded))->toBeLessThanOrEqual(ProfileSizeLimiter::MAX_BYTES)
        ->and(count($stored['storage']['omitted_values']))->toBe(1_000)
        ->and($stored['storage']['omitted_value_count'])->toBe(1_100)
        ->and($stored['storage']['omitted_item_count'])->toBe(1)
        ->and($stored['storage']['omitted_paths_truncated'])->toBe(101);
});

it('rejects irreducible metadata above the byte limit', function () {
    expect(fn () => (new ProfileSizeLimiter)->encode(['metadata' => str_repeat('a', 10_100_000)]))
        ->toThrow(RuntimeException::class, 'The debug profile metadata exceeds the storage byte limit.');
});

it('does not hide JSON serialization failures', function () {
    $resource = fopen('php://memory', 'rb');

    try {
        expect(fn () => (new ProfileSizeLimiter)->encode(['resource' => $resource]))->toThrow(JsonException::class);
    } finally {
        fclose($resource);
    }
});
