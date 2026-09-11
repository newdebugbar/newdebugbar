<?php

use NewDebugBar\Analysis\InspectorAnalyzer;

it('groups models views and event sources', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => 3], 'payload' => ['items' => [
                ['model' => 'App\\Models\\User', 'event' => 'retrieved'],
                ['model' => 'App\\Models\\User', 'event' => 'retrieved'],
                ['model' => 'App\\Models\\Clinic', 'event' => 'created'],
            ]]],
            'views' => ['summary' => ['count' => 2], 'payload' => ['items' => [
                ['name' => 'clinics.index'],
                ['name' => 'clinics.index'],
            ]]],
            'events' => ['summary' => ['count' => 2], 'payload' => ['items' => [
                ['name' => 'Illuminate\\Auth\\Events\\Login'],
                ['name' => 'clinic.ready'],
            ]]],
        ],
    ]);

    expect($profile['inspectors']['models']['summary'])
        ->model_classes->toBe(2)
        ->lifecycle_events->toBe(['retrieved' => 2, 'created' => 1])
        ->retrieval_count->toBe(2)
        ->distinct_record_count->toBe(0)
        ->unidentified_load_count->toBe(2)
        ->repeated_load_count->toBe(0)
        ->model_change_count->toBe(1)
        ->model_change_events->toBe(['created' => 1])
        ->and($profile['inspectors']['models']['payload']['groups'][0])
        ->model->toBe('App\\Models\\User')
        ->event->toBe('retrieved')
        ->count->toBe(2)
        ->and($profile['inspectors']['views']['summary']['unique_views'])->toBe(1)
        ->and($profile['inspectors']['views']['summary']['application_count'])->toBe(2)
        ->and($profile['inspectors']['views']['summary']['framework_count'])->toBe(0)
        ->and($profile['inspectors']['views']['payload']['groups'][0]['count'])->toBe(2)
        ->and($profile['inspectors']['views']['payload']['groups'][0]['origin'])->toBe('application')
        ->and(array_column($profile['inspectors']['events']['payload']['items'], 'source'))
        ->toBe(['framework', 'application']);
});

it('puts application views first and preserves deeper render source evidence', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'views' => ['summary' => ['count' => 4], 'payload' => ['items' => [
                [
                    'name' => 'pagination::tailwind',
                    'source' => ['file' => '/workspace/vendor/laravel/framework/views/tailwind.blade.php', 'line' => 1],
                    'data' => ['paginator' => ['type' => 'LengthAwarePaginator']],
                ],
                [
                    'name' => 'trips.show',
                    'source' => ['file' => 'resources/views/trips/show.blade.php', 'line' => 1],
                    'data' => ['trip' => ['id' => 42], 'weather' => 'clear'],
                    'composers' => [[
                        'name' => 'App\\View\\Composers\\TripComposer@compose',
                        'source' => ['file' => 'app/View/Composers/TripComposer.php', 'line' => 18],
                    ]],
                ],
                [
                    'name' => 'trips.show',
                    'source' => ['file' => 'resources/views/trips/show.blade.php', 'line' => 1],
                    'data' => ['trip' => ['id' => 84]],
                ],
                [
                    'name' => '/workspace/storage/framework/views/a1b2c3.php',
                    'source' => ['file' => 'storage/framework/views/a1b2c3.php', 'line' => 12],
                    'data' => [],
                ],
            ]]],
        ],
    ]);

    $groups = $profile['inspectors']['views']['payload']['groups'];
    $application = $groups[0];
    $framework = $groups[1];
    $compiled = $groups[2];

    expect($profile['inspectors']['views']['summary'])
        ->unique_views->toBe(3)
        ->application_count->toBe(2)
        ->framework_count->toBe(2)
        ->application_views->toBe(1)
        ->framework_views->toBe(2)
        ->and($application)
        ->id->toBe('view-2')
        ->origin->toBe('application')
        ->count->toBe(2)
        ->search->toContain('trips.show', 'tripcomposer@compose')
        ->and($application['items'][0])
        ->render_order->toBe(2)
        ->source_label->toBe('resources/views/trips/show.blade.php')
        ->source_kind->toBe('template')
        ->data_key_count->toBe(2)
        ->composer_count->toBe(1)
        ->and($application['items'][0]['composers'][0]['source_label'])
        ->toBe('app/View/Composers/TripComposer.php:18')
        ->and($framework['origin'])->toBe('framework')
        ->and($framework['items'][0]['source_kind'])->toBe('framework')
        ->and($compiled['origin'])->toBe('framework')
        ->and($compiled['display_name'])->toBe('Compiled Blade view')
        ->and($compiled['items'][0]['source_kind'])->toBe('compiled');
});

it('groups repeated event signatures while preserving timing sources listeners and payload shape', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'events' => ['summary' => ['count' => 5, 'retained_count' => 5], 'payload' => ['items' => [
                [
                    'name' => 'App\\Events\\TripWorkspaceRefreshed',
                    'broadcast' => true,
                    'at_ms' => 10.125,
                    'callsite' => ['file' => 'app/Actions/RefreshTrip.php', 'line' => 41],
                    'listeners' => [[
                        'name' => 'App\\Listeners\\RecordWorkspaceRefresh@handle',
                        'source' => ['file' => 'app/Listeners/RecordWorkspaceRefresh.php', 'line' => 12],
                        'queued' => false,
                        'registrations' => 2,
                    ]],
                    'payload_shape' => [[
                        'position' => 1,
                        'type' => 'App\\Events\\TripWorkspaceRefreshed',
                        'fields' => ['tripId'],
                        'field_count' => 1,
                        'truncated' => false,
                    ]],
                ],
                [
                    'name' => 'App\\Events\\TripWorkspaceRefreshed',
                    'broadcast' => true,
                    'at_ms' => 18.875,
                    'callsite' => ['file' => 'app/Jobs/RefreshTrip.php', 'line' => 24],
                    'listeners' => [[
                        'name' => 'App\\Listeners\\RecordWorkspaceRefresh@handle',
                        'source' => ['file' => 'app/Listeners/RecordWorkspaceRefresh.php', 'line' => 12],
                        'queued' => false,
                        'registrations' => 2,
                    ]],
                    'payload_shape' => [[
                        'position' => 1,
                        'type' => 'App\\Events\\TripWorkspaceRefreshed',
                        'fields' => ['tripId'],
                        'field_count' => 1,
                        'truncated' => false,
                    ]],
                ],
                ['name' => 'Illuminate\\Database\\Events\\StatementPrepared', 'at_ms' => 2.5],
                ['name' => 'Illuminate\\Database\\Events\\StatementPrepared', 'at_ms' => 4.75],
                ['name' => 'application.ready', 'payload_types' => ['array']],
            ]]],
        ],
    ]);

    $groups = collect($profile['inspectors']['events']['payload']['groups']);
    $application = $groups->firstWhere('name', 'App\\Events\\TripWorkspaceRefreshed');
    $framework = $groups->firstWhere('name', 'Illuminate\\Database\\Events\\StatementPrepared');
    $untimed = $groups->firstWhere('name', 'application.ready');

    expect($profile['inspectors']['events']['summary'])
        ->application_count->toBe(3)
        ->framework_count->toBe(2)
        ->group_count->toBe(3)
        ->application_group_count->toBe(2)
        ->framework_group_count->toBe(1)
        ->and(array_column($profile['inspectors']['events']['payload']['items'], 'sequence'))->toBe([1, 2, 3, 4, 5])
        ->and($application)
        ->source->toBe('application')
        ->display_name->toBe('TripWorkspaceRefreshed')
        ->namespace->toBe('App\\Events')
        ->occurrence_count->toBe(2)
        ->first_sequence->toBe(1)
        ->last_sequence->toBe(2)
        ->first_at_ms->toBe(10.125)
        ->last_at_ms->toBe(18.875)
        ->span_ms->toBe(8.75)
        ->listener_count->toBe(2)
        ->duplicate_registration_count->toBe(1)
        ->listener_outcome->toBe('completed')
        ->payload_field_count->toBe(1)
        ->and($application['payload_shape'][0]['fields'])->toBe(['tripId'])
        ->and($application['dispatch_sources'])->toHaveCount(2)
        ->and($application['next_step'])->toContain('registered more than once')
        ->and($framework)
        ->source->toBe('framework')
        ->occurrence_count->toBe(2)
        ->span_ms->toBe(2.25)
        ->and(array_column($framework['occurrences'], 'callsite'))->toBe([null, null])
        ->and($framework['related_inspector'])->toBe(['key' => 'queries', 'label' => 'Queries'])
        ->and($untimed['first_at_ms'])->toBeNull()
        ->and($untimed['payload_shape'][0]['type'])->toBe('array');
});

it('tailors event guidance to the captured listener state', function () {
    $listener = static fn (string $name, bool $queued = false, int $registrations = 1): array => [
        'name' => $name,
        'queued' => $queued,
        'registrations' => $registrations,
    ];
    $source = static fn (string $file): array => ['file' => $file, 'line' => 12];
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'events' => ['summary' => ['count' => 10], 'payload' => ['items' => [
                [
                    'name' => 'App\\Events\\NoListeners',
                    'callsite' => $source('app/Actions/DispatchWithoutListener.php'),
                ],
                [
                    'name' => 'App\\Events\\OneListener',
                    'callsite' => $source('app/Actions/DispatchToOne.php'),
                    'listeners' => [$listener('App\\Listeners\\HandleOne@handle')],
                ],
                [
                    'name' => 'App\\Events\\ManyListeners',
                    'callsite' => $source('app/Actions/DispatchToMany.php'),
                    'listeners' => [
                        $listener('App\\Listeners\\HandleFirst@handle'),
                        $listener('App\\Listeners\\HandleSecond@handle'),
                    ],
                ],
                [
                    'name' => 'App\\Events\\DuplicateListener',
                    'callsite' => $source('app/Actions/DispatchDuplicate.php'),
                    'listeners' => [$listener('App\\Listeners\\HandleDuplicate@handle', registrations: 2)],
                ],
                [
                    'name' => 'App\\Events\\QueuedListener',
                    'listeners' => [$listener('App\\Listeners\\HandleQueued@handle', queued: true)],
                ],
                [
                    'name' => 'App\\Events\\OneListenerManySources',
                    'callsite' => $source('app/Actions/DispatchFirst.php'),
                    'listeners' => [$listener('App\\Listeners\\HandleManySources@handle')],
                ],
                [
                    'name' => 'App\\Events\\OneListenerManySources',
                    'callsite' => $source('app/Actions/DispatchSecond.php'),
                    'listeners' => [$listener('App\\Listeners\\HandleManySources@handle')],
                ],
                [
                    'name' => 'App\\Events\\ListenerWithoutSource',
                    'listeners' => [$listener('App\\Listeners\\HandleWithoutSource@handle')],
                ],
                [
                    'name' => 'App\\Events\\BroadcastUpdate',
                    'broadcast' => true,
                    'listeners' => [$listener('App\\Listeners\\BroadcastUpdate@handle')],
                ],
                ['name' => 'Illuminate\\Database\\Events\\StatementPrepared'],
            ]]],
        ],
    ]);
    $groups = collect($profile['inspectors']['events']['payload']['groups'])->keyBy('name');

    expect($groups['App\\Events\\NoListeners'])
        ->listener_outcome_label->toBe('No listeners')
        ->listener_summary->toBe('No listeners registered.')
        ->next_step->toBe('Start at the dispatch source, then check listener registration and event discovery.')
        ->and($groups['App\\Events\\OneListener']['next_step'])
        ->toBe('Start at the dispatch source, then inspect the registered listener.')
        ->and($groups['App\\Events\\ManyListeners']['next_step'])
        ->toBe('Start at the dispatch source, then inspect the registered listeners.')
        ->and($groups['App\\Events\\DuplicateListener']['next_step'])
        ->toBe('The same listener is registered more than once. Check explicit registration and event discovery.')
        ->and($groups['App\\Events\\QueuedListener']['next_step'])
        ->toBe('Open Queue to confirm the queued listener ran.')
        ->and($groups['App\\Events\\OneListenerManySources']['next_step'])
        ->toBe('Compare the dispatch sources, then inspect the registered listener.')
        ->and($groups['App\\Events\\ListenerWithoutSource']['next_step'])
        ->toBe('Inspect the listener source when the observed result does not match the event.')
        ->and($groups['App\\Events\\BroadcastUpdate']['next_step'])
        ->toBe('Check the broadcast channel and frontend subscription if connected clients did not update.')
        ->and($groups['Illuminate\\Database\\Events\\StatementPrepared']['next_step'])
        ->toBe('Use the related collector when this framework event looks unexpected.');
});

it('bounds grouped event detail while preserving totals and timeline endpoints', function () {
    $items = array_map(fn (int $sequence): array => [
        'name' => 'App\\Events\\RepeatedSignal',
        'at_ms' => $sequence / 10,
        'callsite' => [
            'file' => 'app/Signals/Source'.(($sequence - 1) % 15).'.php',
            'line' => $sequence,
        ],
    ], range(1, 40));
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'events' => [
                'summary' => ['count' => 40, 'retained_count' => 40],
                'payload' => ['items' => $items],
            ],
        ],
    ]);
    $group = $profile['inspectors']['events']['payload']['groups'][0];

    expect($group)
        ->occurrence_count->toBe(40)
        ->occurrence_omitted_count->toBe(15)
        ->dispatch_source_count->toBe(40)
        ->dispatch_source_omitted_count->toBe(30)
        ->and($group['occurrences'])->toHaveCount(25)
        ->and($group['occurrences'][0]['sequence'])->toBe(1)
        ->and($group['occurrences'][24]['sequence'])->toBe(40)
        ->and($group['dispatch_sources'])->toHaveCount(10)
        ->and($profile['inspectors']['events']['payload']['items'])->toHaveCount(40);
});

it('sorts model groups by count then by model name', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => 5], 'payload' => ['items' => [
                ['model' => 'App\\Models\\User', 'event' => 'retrieved'],
                ['model' => 'App\\Models\\Audit', 'event' => 'saved'],
                ['model' => 'App\\Models\\Clinic', 'event' => 'created'],
                ['model' => 'App\\Models\\User', 'event' => 'retrieved'],
                ['model' => 'App\\Models\\Clinic', 'event' => 'created'],
            ]]],
        ],
    ]);

    expect(array_map(
        fn (array $group): array => [$group['model'], $group['count']],
        $profile['inspectors']['models']['payload']['groups'],
    ))->toBe([
        ['App\\Models\\Clinic', 2],
        ['App\\Models\\User', 2],
        ['App\\Models\\Audit', 1],
    ]);
});

it('summarizes model loads by record and counts only extra identified retrievals as repeated', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => 5], 'payload' => ['items' => [
                ['model' => 'App\\Models\\User', 'event' => 'retrieved', 'key' => 2, 'connection' => 'testing', 'table' => 'users', 'at_ms' => 7.25],
                ['model' => 'App\\Models\\User', 'event' => 'retrieved', 'key' => 1, 'connection' => 'testing', 'table' => 'users', 'at_ms' => 2.5],
                ['model' => 'App\\Models\\User', 'event' => 'retrieved', 'key' => 1, 'connection' => 'testing', 'table' => 'users', 'at_ms' => 5.75],
                ['model' => 'App\\Models\\User', 'event' => 'retrieved', 'key' => 1, 'connection' => 'testing', 'table' => 'users', 'at_ms' => 8.5],
                ['model' => 'App\\Models\\User', 'event' => 'retrieved', 'key' => null, 'connection' => 'testing', 'table' => 'users', 'at_ms' => 9],
            ]]],
        ],
    ]);

    expect($profile['inspectors']['models']['summary'])
        ->retrieval_count->toBe(5)
        ->distinct_record_count->toBe(2)
        ->unidentified_load_count->toBe(1)
        ->repeated_load_count->toBe(2)
        ->and($profile['inspectors']['models']['payload']['model_groups'][0])
        ->model->toBe('App\\Models\\User')
        ->connection->toBe('testing')
        ->table->toBe('users')
        ->load_count->toBe(5)
        ->record_count->toBe(2)
        ->unidentified_load_count->toBe(1)
        ->repeated_load_count->toBe(2)
        ->and($profile['inspectors']['models']['payload']['model_groups'][0]['records'][0])
        ->key->toBe(1)
        ->loads->toBe(3)
        ->first_seen_ms->toBe(2.5)
        ->last_seen_ms->toBe(8.5)
        ->and(array_column($profile['inspectors']['models']['payload']['model_groups'][0]['guidance'], 'type'))
        ->toContain('extra_retrievals', 'missing_source');
});

it('ranks changed models before repeated retrievals and keeps write events distinct', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => 7], 'payload' => ['items' => [
                ['model' => 'App\\Models\\StudioJob', 'event' => 'retrieved', 'key' => 1],
                ['model' => 'App\\Models\\StudioJob', 'event' => 'retrieved', 'key' => 1],
                ['model' => 'App\\Models\\StudioJob', 'event' => 'retrieved', 'key' => 1],
                ['model' => 'App\\Models\\Client', 'event' => 'retrieved', 'key' => 4],
                ['model' => 'App\\Models\\Client', 'event' => 'updated', 'key' => 4],
                ['model' => 'App\\Models\\Client', 'event' => 'saved', 'key' => 4],
                ['model' => 'App\\Models\\Client', 'event' => 'updating', 'key' => 4],
            ]]],
        ],
    ]);

    expect(array_column($profile['inspectors']['models']['payload']['model_groups'], 'model'))
        ->toBe(['App\\Models\\Client', 'App\\Models\\StudioJob'])
        ->and($profile['inspectors']['models']['summary'])
        ->model_change_count->toBe(1)
        ->model_change_events->toBe(['updated' => 1])
        ->repeated_load_count->toBe(2)
        ->and($profile['inspectors']['models']['payload']['model_groups'][0])
        ->change_count->toBe(1)
        ->change_events->toBe(['updated' => 1])
        ->total_count->toBe(4);
});

it('folds duplicate lifecycle callbacks into logical operations and matches queries by exact source', function () {
    $source = ['file' => 'app/Actions/UpdateClient.php', 'line' => 42];
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'queries' => ['summary' => ['count' => 1], 'payload' => ['items' => [[
                'duration_ms' => 3.25,
                'query_type' => 'write',
                'callsite' => $source,
            ]]]],
            'models' => ['summary' => ['count' => 5], 'payload' => ['items' => [
                ['model' => 'App\\Models\\Client', 'event' => 'updating', 'operation_id' => 7, 'operation' => 'updated', 'key_name' => 'id', 'key' => 4, 'at_ms' => 5, 'callsite' => $source],
                ['model' => 'App\\Models\\Client', 'event' => 'updated', 'operation_id' => 7, 'operation' => 'updated', 'key_name' => 'id', 'key' => 4, 'at_ms' => 8, 'callsite' => $source, 'change_attribute_count' => 1, 'changes' => ['status' => 'ready']],
                ['model' => 'App\\Models\\Client', 'event' => 'saved', 'operation_id' => 7, 'operation' => 'updated', 'key_name' => 'id', 'key' => 4, 'at_ms' => 9, 'callsite' => $source],
                ['model' => 'App\\Models\\Client', 'event' => 'trashed', 'operation_id' => 8, 'operation' => 'trashed', 'key_name' => 'id', 'key' => 4, 'at_ms' => 12, 'callsite' => $source],
                ['model' => 'App\\Models\\Client', 'event' => 'deleted', 'operation_id' => 8, 'operation' => 'trashed', 'key_name' => 'id', 'key' => 4, 'at_ms' => 13, 'callsite' => $source],
            ]]],
        ],
    ]);

    $models = $profile['inspectors']['models'];
    $group = $models['payload']['model_groups'][0];

    expect($models['summary'])
        ->model_change_count->toBe(2)
        ->activity_count->toBe(2)
        ->intermediate_lifecycle_event_count->toBe(3)
        ->model_change_events->toBe(['updated' => 1, 'trashed' => 1])
        ->and($group['change_operations'])->toHaveCount(2)
        ->and(array_column($group['change_operations'], 'event'))->toBe(['updated', 'trashed'])
        ->and($group['change_operations'][0]['lifecycle_events'])->toBe([
            'updating' => 1,
            'updated' => 1,
            'saved' => 1,
        ])
        ->and($group['change_operations'][1]['lifecycle_events'])->toBe([
            'trashed' => 1,
            'deleted' => 1,
        ])
        ->and($group['sources'][0])
        ->activity_count->toBe(2)
        ->change_count->toBe(2)
        ->query_count->toBe(1)
        ->query_duration_ms->toBe(3.25)
        ->query_write_count->toBe(1)
        ->query_read_count->toBe(0)
        ->and($group['related_query_count'])->toBe(1)
        ->and(array_column($group['guidance'], 'type'))->toBe(['write_evidence', 'query_correlation'])
        ->and($group['guidance'][1]['why'])->toContain('does not prove');
});

it('does not infer a write from incomplete lifecycle callbacks', function () {
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => 2], 'payload' => ['items' => [
                ['model' => 'App\\Models\\Client', 'event' => 'updating', 'operation_id' => 9, 'operation' => 'updated'],
                ['model' => 'App\\Models\\Client', 'event' => 'saving'],
            ]]],
        ],
    ]);
    $group = $profile['inspectors']['models']['payload']['model_groups'][0];

    expect($profile['inspectors']['models']['summary'])
        ->model_change_count->toBe(0)
        ->activity_count->toBe(0)
        ->and($group['change_operations'])->toBe([]);
});

it('bounds rendered record and source evidence without changing complete counts', function () {
    $items = [];

    foreach (range(1, 30) as $key) {
        $items[] = [
            'model' => 'App\\Models\\AuditEntry',
            'event' => 'retrieved',
            'key_name' => 'uuid',
            'key' => 'record-'.$key,
            'at_ms' => $key,
            'callsite' => ['file' => 'app/Reports/AuditReport.php', 'line' => $key],
        ];
    }

    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => count($items)], 'payload' => ['items' => $items]],
        ],
    ]);
    $group = $profile['inspectors']['models']['payload']['model_groups'][0];
    $preview = $profile['inspectors']['models']['payload']['model_group_previews'][0];

    expect($group)
        ->record_count->toBe(30)
        ->hidden_record_count->toBe(5)
        ->source_count->toBe(30)
        ->hidden_source_count->toBe(22)
        ->and($group['records'])->toHaveCount(30)
        ->and($group['sources'])->toHaveCount(30)
        ->and($preview['records'])->toHaveCount(25)
        ->and($preview['sources'])->toHaveCount(8)
        ->and($preview['records'][0]['key_name'])->toBe('uuid');
});

it('keeps every folded write requestable while bounding the browser preview', function () {
    $items = array_map(fn (int $operation): array => [
        'model' => 'App\\Models\\AuditEntry',
        'event' => 'updated',
        'operation_id' => $operation,
        'operation' => 'updated',
        'key_name' => 'id',
        'key' => $operation,
        'at_ms' => $operation,
        'change_attribute_count' => 1,
        'changes' => ['sequence' => $operation],
        'callsite' => ['file' => 'app/Actions/UpdateAudit.php', 'line' => $operation],
    ], range(1, 22));
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => count($items)], 'payload' => ['items' => $items]],
        ],
    ]);
    $group = $profile['inspectors']['models']['payload']['model_groups'][0];
    $preview = $profile['inspectors']['models']['payload']['model_group_previews'][0];

    expect($group)
        ->change_count->toBe(22)
        ->hidden_change_operation_count->toBe(2)
        ->and($group['change_operations'])->toHaveCount(22)
        ->and($group['change_operations'][21]['changes'])->toBe(['sequence' => 22])
        ->and($preview['change_operations'])->toHaveCount(20);
});

it('keeps compiled Blade provenance and guidance on model sources', function () {
    $callsite = [
        'file' => 'storage/framework/views/compiled.php',
        'line' => 18,
        'kind' => 'compiled_view',
        'template_file' => 'resources/views/trips/show.blade.php',
    ];
    $profile = (new InspectorAnalyzer)->analyze([
        'inspectors' => [
            'models' => ['summary' => ['count' => 1], 'payload' => ['items' => [[
                'model' => 'App\\Models\\Trip',
                'event' => 'retrieved',
                'key_name' => 'id',
                'key' => 7,
                'callsite' => $callsite,
            ]]]],
        ],
    ]);
    $group = $profile['inspectors']['models']['payload']['model_groups'][0];

    expect($group['sources'][0]['callsite'])->toBe($callsite)
        ->and($group['records'][0]['sources'][0]['callsite'])->toBe($callsite)
        ->and(array_column($group['guidance'], 'type'))->toContain('compiled_blade_source')
        ->and(collect($group['guidance'])->firstWhere('type', 'compiled_blade_source')['next'])
        ->toContain('resources/views/trips/show.blade.php');
});
