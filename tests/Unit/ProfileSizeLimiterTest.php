<?php

use NewDebugBar\Support\ProfileSizeLimiter;
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

it('trims the largest record list from its tail without rewriting capture counts', function () {
    $rows = array_map(fn (int $index): array => ['index' => $index, 'value' => str_repeat('é', 2_000)], range(0, 999));
    $profile = ['sections' => [
        'logs' => ['summary' => ['count' => 1_100, 'retained_count' => 1_000, 'dropped_count' => 100], 'payload' => ['items' => $rows]],
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
            'count' => 1_100, 'retained_count' => 1_000, 'dropped_count' => 100,
            'storage_truncated' => true, 'storage_omitted_items' => 1_000 - $retained,
        ])
        ->and($stored['storage']['omitted_items'])->toBe(['/sections/logs/payload/items' => 1_000 - $retained])
        ->and($stored['storage']['omitted_item_count'])->toBe(1_000 - $retained);
});

it('trims Livewire activity aliases together and counts their records once', function () {
    $rows = array_fill(0, 500, ['value' => str_repeat('a', 12_000)]);
    $profile = ['sections' => ['livewire' => [
        'summary' => ['activity_count' => 500],
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
        ->and($stored['sections']['livewire']['summary'])->toMatchArray(['activity_count' => 500, 'storage_omitted_items' => $omitted])
        ->and($stored['storage']['omitted_items'])->toBe(['/sections/livewire/payload/activity' => $omitted])
        ->and($stored['storage']['omitted_item_count'])->toBe($omitted);
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
