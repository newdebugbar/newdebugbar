<?php

function authorizationInspectorDocument(array $items): array
{
    $inspector = [
        'summary' => ['count' => count($items)],
        'payload' => ['items' => $items],
    ];
    $html = view('newdebugbar::livewire.inspectors.authorization', compact('inspector'))->render();
    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);

    $document->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>'.$html.'</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);

    return [$html, new DOMXPath($document)];
}

it('renders decisions for scanning and keeps structured evidence in the inspector', function () {
    [$html, $xpath] = authorizationInspectorDocument([
        [
            'execution' => 7,
            'result' => 'allowed',
            'ability' => 'revise-itinerary',
            'result_message' => 'The planner owns this trip.',
            'result_code' => 'trip_owner',
            'handler' => 'App\\Policies\\TripPolicy@reviseItinerary',
            'handler_kind' => 'policy',
            'handler_name' => 'App\\Policies\\TripPolicy@reviseItinerary',
            'handler_source' => ['file' => 'app/Policies/TripPolicy.php', 'line' => 27],
            'user' => [
                'type' => 'App\\Models\\User',
                'identifier_name' => 'id',
                'identifier' => 42,
                'name' => 'Mara Voss',
            ],
            'arguments' => [
                [
                    'position' => 1,
                    'kind' => 'model',
                    'type' => 'App\\Models\\Trip',
                    'identifier' => 9,
                    'route_key_name' => 'slug',
                    'route_key' => 'kyoto-autumn',
                    'name' => 'Kyoto in autumn',
                ],
                ['position' => 2, 'kind' => 'value', 'type' => 'string', 'value' => 'lodging'],
                ['position' => 3, 'kind' => 'value', 'type' => 'int', 'value' => 3],
            ],
            'callsite' => ['file' => 'app/Actions/Trips/RefreshTripWorkspace.php', 'line' => 41],
            'stack' => [[
                'file' => 'app/Actions/Trips/RefreshTripWorkspace.php',
                'line' => 41,
                'function' => 'Gate::allows',
            ]],
        ],
        [
            'execution' => 8,
            'result' => 'denied',
            'ability' => 'access-private-planning-notes',
            'result_message' => 'Guests cannot open private notes.',
            'handler' => 'callback',
            'handler_kind' => 'callback',
            'handler_name' => 'Gate callback',
            'user' => null,
            'arguments' => [],
            'callsite' => ['file' => 'app/Providers/AuthServiceProvider.php', 'line' => 31],
        ],
    ]);
    $items = $xpath->query('//*[@data-ndb-authorization-item]');
    $first = $items->item(0);
    $second = $items->item(1);
    $text = static fn (DOMNode $context, string $attribute): string => trim((string) $xpath->evaluate(
        "string(.//*[@{$attribute}])",
        $context,
    ));
    $filters = $xpath->query('//*[@data-ndb-authorization-filter]');
    $payload = trim((string) $xpath->evaluate('string(//*[@data-ndb-authorization-payload])'));
    $decoded = json_decode(base64_decode($payload, true), true, flags: JSON_THROW_ON_ERROR);

    expect($items->length)->toBe(2)
        ->and($filters->length)->toBe(3)
        ->and($filters->item(0)?->attributes?->getNamedItem('data-ndb-authorization-filter')?->nodeValue)->toBe('all')
        ->and($filters->item(1)?->attributes?->getNamedItem('data-ndb-authorization-filter')?->nodeValue)->toBe('denied')
        ->and($first)->toBeInstanceOf(DOMElement::class)
        ->and($first?->getAttribute('data-ndb-authorization-result'))->toBe('allowed')
        ->and($first?->hasAttribute('data-result'))->toBeFalse()
        ->and($text($first, 'data-ndb-authorization-ability'))->toBe('revise-itinerary')
        ->and($text($first, 'data-ndb-authorization-result-label'))->toBe('Allowed')
        ->and($text($first, 'data-ndb-authorization-user'))->toContain('Mara Voss')
        ->and($text($first, 'data-ndb-authorization-arguments'))->toContain('Kyoto in autumn and 2 more')
        ->and($second)->toBeInstanceOf(DOMElement::class)
        ->and($second?->getAttribute('data-ndb-authorization-result'))->toBe('denied')
        ->and($text($second, 'data-ndb-authorization-user'))->toContain('Guest')
        ->and($text($second, 'data-ndb-authorization-arguments'))->toBe('—')
        ->and($xpath->query('//*[@data-ndb-authorization-detail]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ndb-authorization-detail-tab]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-authorization-detail-panel="combined"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ndb-authorization-copy-evidence]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ndb-authorization-copy-handler]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-authorization-copy-handler-source]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ndb-authorization-copy-callsite]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ndb-authorization-connector]')->length)->toBe(0)
        ->and($decoded[0]['user_label'])->toBe('Mara Voss')
        ->and($decoded[0]['argument_summary'])->toBe('Kyoto in autumn and 2 more')
        ->and(array_column($decoded[0]['arguments'], 'role_label'))->toBe(['Resource', 'Additional context 1', 'Additional context 2'])
        ->and($decoded[0]['handler_label'])->toBe('Matched policy method')
        ->and($decoded[0]['result_message'])->toBe('The planner owns this trip.')
        ->and($decoded[0])->not->toHaveKey('check_next')
        ->and($decoded[1]['user_label'])->toBe('Guest')
        ->and($decoded[1]['arguments'])->toBe([])
        ->and($decoded[1]['argument_summary'])->toBe('—')
        ->and($decoded[1])->not->toHaveKey('check_next')
        ->and(json_decode($decoded[0]['copy_evidence'], true, flags: JSON_THROW_ON_ERROR))->toHaveKey('user')
        ->and(json_decode($decoded[0]['copy_evidence'], true, flags: JSON_THROW_ON_ERROR))->not->toHaveKeys(['actor', 'checked_from'])
        ->and($html)->not->toContain('What should I inspect if this result looks wrong?')
        ->and($html)->not->toContain('Laravel allowed this ability')
        ->and($html)->not->toContain('No target or additional arguments were supplied.')
        ->and($html)->not->toContain('>Actor<')
        ->and($html)->not->toContain('→');
});

it('keeps named callback evidence specific without inventing optional evidence', function () {
    [, $xpath] = authorizationInspectorDocument([[
        'execution' => 9,
        'result' => 'allowed',
        'ability' => 'view-public-trip-outline',
        'handler' => 'callback',
        'handler_kind' => 'callback',
        'handler_name' => 'App\\Gates\\PublicTripGate@view',
        'user' => null,
        'arguments' => [],
    ]]);
    $payload = trim((string) $xpath->evaluate('string(//*[@data-ndb-authorization-payload])'));
    $decision = json_decode(base64_decode($payload, true), true, flags: JSON_THROW_ON_ERROR)[0];

    expect($decision)
        ->user_label->toBe('Guest')
        ->argument_summary->toBe('—')
        ->callsite_label->toBeNull()
        ->handler_label->toBe('Matched Gate callback')
        ->result_message->toBeNull()
        ->result_code->toBeNull()
        ->result_status->toBeNull()
        ->not->toHaveKey('check_next');
});

it('renders a clear empty authorization state', function () {
    [$html, $xpath] = authorizationInspectorDocument([]);

    expect($xpath->query('//*[@data-ndb-authorization-workspace]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ndb-authorization-item]')->length)->toBe(0)
        ->and($html)->toContain('No authorization decisions were captured.');
});
