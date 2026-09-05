<?php

use NewDebugBar\Analysis\LogAnalyzer;

it('renders structured log details without repeating the raw record', function () {
    $message = "Reservation refresh failed.\nThe cached itinerary remains available.";
    $exceptionMessage = 'Partner rejected reservation KYO-441.';
    $analysis = app(LogAnalyzer::class)->analyze([
        [
            'level' => 'error',
            'message' => $message,
            'channel' => 'morrow-audit',
            'context' => ['trip_id' => 41, 'actor' => ['type' => 'planner', 'id' => 7]],
            'callsite' => ['file' => 'app/Actions/RefreshTrip.php', 'line' => 48],
            'stack' => [['file' => 'app/Actions/RefreshTrip.php', 'line' => 48, 'function' => 'handle']],
            'related_exception' => [
                'class' => RuntimeException::class,
                'message' => $exceptionMessage,
                'file' => 'app/Partners/RailPartner.php',
                'line' => 91,
            ],
            'at_ms' => 18.432,
            'occurred_at' => '2026-08-24T16:32:10.123+02:00',
        ],
    ]);
    $section = [
        'summary' => ['count' => 1, ...$analysis['summary']],
        'payload' => ['items' => $analysis['items'], 'groups' => $analysis['groups']],
    ];

    $html = view('newdebugbar::livewire.sections.logs', compact('section'))->render();

    expect($html)
        ->toContain(
            'data-ndb-log-controls',
            'data-ndb-log-entry',
            'data-ndb-log-level="error"',
            'data-ndb-log-channel="morrow-audit"',
            'data-ndb-log-metadata',
            'data-ndb-log-channel-label',
            'data-ndb-log-request-time',
            'data-ndb-log-level-select',
            'data-ndb-log-detail',
            'data-ndb-log-detail-groups',
            'data-ndb-log-detail-group="related-exception"',
            'data-ndb-log-detail-group="context"',
            'data-ndb-log-detail-group="source"',
            'Choose a log entry to inspect its evidence.',
            'selectLogEntry(1)',
            'data-ndb-log-related-exception',
            'data-ndb-log-source',
            'data-ndb-inspector-stack',
            'x-for="(frame, index) in JSON.parse(',
            'Review in Exceptions',
        )
        ->not->toContain(
            'data-ndb-log-empty',
            'data-ndb-log-context-preview',
            'data-ndb-log-actions',
            'data-ndb-log-attention-label',
            'data-ndb-copy-log-',
            'data-ndb-log-filter=',
            'data-ndb-log-raw',
            'Raw evidence',
            '<details data-ndb-log-entry',
            'data-ndb-log-details-popover',
            'data-ndb-popover-surface',
            'View details',
            'data-ndb-log-repeat-label',
            'ndb:grid-cols-[4.75rem_minmax(0,1fr)_5.5rem]',
        );

    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>'.$html.'</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $xpath = new DOMXPath($document);

    expect(trim((string) $xpath->evaluate('string(//*[@data-ndb-log-details-title])')))->toBe($message)
        ->and(trim((string) $xpath->evaluate('string(//*[@data-ndb-log-related-exception]//p[1])')))
        ->toBe($exceptionMessage);
});

it('renders a truthful empty state when no log records were captured', function () {
    $section = [
        'summary' => [
            'count' => 0,
            'attention_count' => 0,
            'group_count' => 0,
            'repeated_count' => 0,
            'levels' => [],
            'channels' => [],
        ],
        'payload' => ['items' => [], 'groups' => []],
    ];

    $html = view('newdebugbar::livewire.sections.logs', compact('section'))->render();

    expect($html)
        ->toContain('data-ndb-log-empty', 'No log records were captured for this request.')
        ->not->toContain('data-ndb-log-controls', 'data-ndb-log-entry');
});

it('retains full scalar context and gives nested context the full evidence width', function () {
    $value = str_repeat('Retained diagnostic context. ', 12)."\nFinal retained line.";
    $analysis = app(LogAnalyzer::class)->analyze([
        [
            'level' => 'warning',
            'message' => 'Inspect retained context.',
            'context' => [
                'trip_id' => 41,
                'detail' => $value,
                'actor' => ['type' => 'planner', 'id' => 7],
            ],
            'at_ms' => 18.432,
            'occurred_at' => '2026-08-24T16:32:10.123+02:00',
        ],
    ]);
    $html = view('newdebugbar::components.log-detail', ['entry' => $analysis['groups'][0]])->render();
    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>'.$html.'</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $xpath = new DOMXPath($document);

    expect((string) $xpath->evaluate('string(//*[@data-ndb-log-context-full-value])'))->toBe($value)
        ->and($xpath->query('//*[@data-ndb-log-context-payload]/ancestor::dd')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-log-context-value]/@open')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-log-capture-details]/@open')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-log-context-full-value]/ancestor::template')->length)->toBeGreaterThan(0)
        ->and($html)->toContain('Channel', 'From request start', 'Captured at', 'Final retained line.');
});
