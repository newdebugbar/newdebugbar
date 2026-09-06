<?php

use Illuminate\Support\Str;
use NewDebugBar\Presentation\QueueActivityPresenter;
use NewDebugBar\Presentation\RedisCommandPresenter;

function inspectorPayload(string $html, string $attribute): array
{
    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);

    $document->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>'.$html.'</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $xpath = new DOMXPath($document);
    $payload = trim((string) $xpath->evaluate("string(//*[@{$attribute}])"));

    return json_decode(base64_decode($payload, true), true, flags: JSON_THROW_ON_ERROR);
}

it('normalizes queue lifecycle and related worker evidence for one active detail', function () {
    $profileId = (string) Str::uuid();
    $workerId = (string) Str::uuid();
    $section = [
        'summary' => [
            'count' => 2,
            'queued_count' => 1,
            'executed_count' => 1,
            'failed_count' => 1,
            'duration_ms' => 4.25,
        ],
        'payload' => ['items' => [
            [
                'kind' => 'queued',
                'status' => 'sent',
                'job' => 'App\\Jobs\\SendTripReceipt',
                'connection' => 'redis',
                'queue' => 'mail',
                'job_id' => 'job-41',
                'delay_seconds' => 30,
                'duration_ms' => 0.0,
                'at_ms' => 12.5,
                'is_origin' => true,
                'worker_profile_id' => $workerId,
                'communication_type' => 'mail',
                'communication_class' => 'App\\Mail\\TripReceipt',
                'channels' => ['mail'],
                'recipient_count' => 1,
                'attempts' => [[
                    'attempt' => 1,
                    'status' => 'sent',
                    'profile_id' => $workerId,
                    'recorded_at' => '2026-08-26T10:00:00+02:00',
                ]],
            ],
            [
                'kind' => 'failed',
                'status' => 'failed',
                'job' => 'App\\Jobs\\RefreshTrip',
                'connection' => 'sync',
                'queue' => 'sync',
                'duration_ms' => 4.25,
                'at_ms' => 18.75,
                'attempt' => 1,
                'exception_class' => RuntimeException::class,
            ],
        ]],
    ];
    $profile = ['background_activity' => ['pending' => false]];

    $section['payload']['records'] = app(QueueActivityPresenter::class)->present($section['payload']['items'], $profileId);
    $html = view('newdebugbar::livewire.sections.queue', compact('profileId', 'profile', 'section'))->render();
    $items = inspectorPayload($html, 'data-ndb-queue-payload');

    expect($html)
        ->toContain('data-ndb-queue-workspace', 'data-ndb-queue-profile-link')
        ->not->toContain('data-ndb-queue-sort')
        ->and($items)->toHaveCount(2)
        ->and($items[0])
        ->status_group->toBe('completed')
        ->related_profile_id->toBe($workerId)
        ->related_section->toBe('mail')
        ->at_label->toBe('12.5 ms')
        ->display_channels->toBe([])
        ->attempts->toHaveCount(1)
        ->and($items[0]['attempts'][0]['sequence'])->toBe(1)
        ->and($items[0]['attempts'][0]['attempt'])->toBe(1)
        ->and($items[0]['attempts'][0]['status'])->toBe('sent')
        ->and($items[0]['attempts'][0]['profile_id'])->toBe($workerId)
        ->and($items[1])
        ->status_group->toBe('failed')
        ->duration_label->toBe('4.25 ms')
        ->exception_class->toBe(RuntimeException::class)
        ->attempts->toBe([]);
});

it('keeps protected Redis identifiers out of rows and failure timing truthful', function () {
    $protected = '18b0b12c34d56e78';
    $section = [
        'summary' => ['count' => 2, 'failed_count' => 1, 'duration_ms' => 1.25],
        'payload' => ['items' => [
            [
                'command' => 'GET',
                'connection' => 'default',
                'duration_ms' => 1.25,
                'at_ms' => 8.5,
                'failed' => false,
                'key_count' => 1,
                'key_retained' => 1,
                'key_dropped' => 0,
                'key_policy' => 'full',
                'keys' => ['trip:kyoto'],
                'key_hashes' => [$protected],
                'callsite' => [
                    'file' => 'app/Services/TripCache.php',
                    'line' => 42,
                ],
            ],
            [
                'command' => 'HGET',
                'connection' => 'sessions',
                'duration_ms' => 0.0,
                'at_ms' => 9.0,
                'failed' => true,
                'exception_class' => RuntimeException::class,
                'key_count' => 1,
                'key_retained' => 1,
                'key_dropped' => 0,
                'key_policy' => 'hash',
                'keys' => [],
                'key_hashes' => [$protected],
                'callsite' => ['file' => 'app/Services/SessionStore.php'],
            ],
        ]],
    ];

    $section['payload']['records'] = app(RedisCommandPresenter::class)->present($section['payload']['items']);
    $html = view('newdebugbar::livewire.sections.redis', compact('section'))->render();
    $items = inspectorPayload($html, 'data-ndb-redis-payload');
    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>'.$html.'</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//*[@data-ndb-redis-item]');
    $failedRow = trim((string) $xpath->evaluate('string(//*[@data-ndb-redis-item="2"])'));

    expect($html)
        ->toContain('data-ndb-redis-workspace')
        ->toContain(
            'data-ndb-redis-detail-body',
            'data-ndb-redis-key-evidence',
            'data-ndb-inspector-source-link',
        )
        ->not->toContain('data-ndb-redis-detail-tab', 'data-ndb-redis-sort', 'Succeeded')
        ->and($rows->length)->toBe(2)
        ->and(trim((string) $rows->item(0)?->textContent))->toContain('trip:kyoto')
        ->and(trim((string) $rows->item(0)?->textContent))->not->toContain($protected)
        ->and($failedRow)->toContain('1 protected key', '—')
        ->and($items[0])
        ->callsite->toBe(['file' => 'app/Services/TripCache.php', 'line' => 42])
        ->source_label->toBe('app/Services/TripCache.php:42')
        ->and($items[1])
        ->duration_label->toBe('—')
        ->key_count->toBe(1)
        ->key_hashes->toBe([$protected])
        ->callsite->toBe(['file' => 'app/Services/SessionStore.php', 'line' => null])
        ->source_label->toBe('app/Services/SessionStore.php');
});

it('renders truthful Queue and Redis empty states', function () {
    $profileId = (string) Str::uuid();
    $profile = ['background_activity' => ['pending' => false]];
    $queue = ['summary' => ['duration_ms' => 0, 'failed_count' => 0], 'payload' => ['items' => []]];
    $redis = ['summary' => ['duration_ms' => 0, 'failed_count' => 0], 'payload' => ['items' => []]];

    expect(view('newdebugbar::livewire.sections.queue', [
        'profileId' => $profileId,
        'profile' => $profile,
        'section' => $queue,
    ])->render())->toContain('No queue activity was captured.')
        ->and(view('newdebugbar::livewire.sections.redis', ['section' => $redis])->render())
        ->toContain('No direct Redis commands were captured.');
});
