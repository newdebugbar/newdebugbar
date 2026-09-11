<?php

namespace NewDebugBar\Analysis;

/** Adds deterministic summaries and groups to existing Laravel inspectors. */
final class InspectorAnalyzer
{
    private const EVENT_DISPATCH_SOURCE_LIMIT = 10;

    private const EVENT_OCCURRENCE_EVIDENCE_LIMIT = 25;

    /** @var list<string> */
    private const MODEL_CHANGE_EVENTS = ['created', 'updated', 'deleted', 'restored', 'forceDeleted', 'trashed'];

    private const MAX_MODEL_CHANGE_OPERATIONS = 20;

    private const MAX_MODEL_RECORDS = 25;

    private const MAX_MODEL_SOURCES = 8;

    private const MAX_RECORD_SOURCES = 3;

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    public function analyze(array $profile): array
    {
        $profile = $this->models($profile);
        $profile = $this->views($profile);

        return $this->events($profile);
    }

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    private function models(array $profile): array
    {
        $items = $this->items($profile, 'models');
        $groups = [];
        $events = [];
        /** @var array<string, array<string, mixed>> $modelGroups */
        $modelGroups = [];
        $querySources = $this->querySources($profile);

        foreach ($items as $index => $item) {
            $key = ($item['model'] ?? 'Unknown').'::'.($item['event'] ?? 'unknown');
            $groups[$key] ??= [
                'model' => $item['model'] ?? 'Unknown',
                'event' => $item['event'] ?? 'unknown',
                'count' => 0,
                'items' => [],
            ];
            $groups[$key]['count']++;
            $groups[$key]['items'][] = $item;
            $event = (string) ($item['event'] ?? 'unknown');
            $events[$event] = ($events[$event] ?? 0) + 1;

            $model = (string) ($item['model'] ?? 'Unknown');
            $connection = $item['connection'] ?? null;
            $table = $item['table'] ?? null;
            $modelGroupKey = $model."\0".(string) $connection."\0".(string) $table;
            $modelGroups[$modelGroupKey] ??= [
                'model' => $model,
                'connection' => $connection,
                'table' => $table,
                'load_count' => 0,
                'record_count' => 0,
                'unidentified_load_count' => 0,
                'repeated_load_count' => 0,
                'change_count' => 0,
                'change_events' => [],
                'change_operations' => [],
                'change_candidates' => [],
                'total_count' => 0,
                'first_seen_ms' => null,
                'last_seen_ms' => null,
                'records' => [],
                'lifecycle_events' => [],
                'sources' => [],
                'unknown_source_activity_count' => 0,
            ];
            $modelGroup = &$modelGroups[$modelGroupKey];
            $modelGroup['total_count']++;
            $modelGroup['lifecycle_events'][$event] = ($modelGroup['lifecycle_events'][$event] ?? 0) + 1;
            $this->addModelTiming($modelGroup, $item);

            if ($event === 'retrieved') {
                $modelGroup['load_count']++;

                if (! $this->addModelSource($modelGroup['sources'], $item, 'retrieved', $event)) {
                    $modelGroup['unknown_source_activity_count']++;
                }

                if (($item['key'] ?? null) === null) {
                    $modelGroup['unidentified_load_count']++;

                    unset($modelGroup);

                    continue;
                }

                $recordKey = get_debug_type($item['key']).':'.(string) $item['key'];
                $modelGroup['records'][$recordKey] ??= [
                    'key_name' => $item['key_name'] ?? 'id',
                    'key' => $item['key'],
                    'loads' => 0,
                    'first_seen_ms' => null,
                    'last_seen_ms' => null,
                    'sources' => [],
                    'unknown_source_count' => 0,
                ];
                $record = &$modelGroup['records'][$recordKey];
                $record['loads']++;
                $this->addModelTiming($record, $item);
                if (! $this->addModelSource($record['sources'], $item, 'retrieved', $event)) {
                    $record['unknown_source_count']++;
                }
                unset($record);
            }

            if (in_array($event, self::MODEL_CHANGE_EVENTS, true) || (isset($item['operation_id']) && is_numeric($item['operation_id']))) {
                $operationKey = isset($item['operation_id']) && is_numeric($item['operation_id'])
                    ? 'operation:'.(string) $item['operation_id']
                    : 'event:'.$index;
                $modelGroup['change_candidates'][$operationKey][] = $item;
            }

            unset($modelGroup);
        }

        $groups = array_values($groups);
        usort($groups, fn (array $left, array $right): int => $right['count'] <=> $left['count']
            ?: strcasecmp((string) $left['model'], (string) $right['model'])
            ?: strcasecmp((string) $left['event'], (string) $right['event']));

        foreach ($modelGroups as &$modelGroup) {
            foreach ($modelGroup['change_candidates'] as $candidates) {
                if (! $this->hasCompletedModelChange($candidates)) {
                    continue;
                }

                $operation = $this->modelChangeOperation($candidates);
                $modelGroup['change_operations'][] = $operation;
                $modelGroup['change_count']++;
                $event = $operation['event'];
                $modelGroup['change_events'][$event] = ($modelGroup['change_events'][$event] ?? 0) + 1;

                if (! $this->addModelSource($modelGroup['sources'], $operation, 'changed', $event)) {
                    $modelGroup['unknown_source_activity_count']++;
                }
            }
            unset($modelGroup['change_candidates']);

            usort($modelGroup['change_operations'], fn (array $left, array $right): int => ($left['at_ms'] ?? PHP_FLOAT_MAX) <=> ($right['at_ms'] ?? PHP_FLOAT_MAX));
            $modelGroup['hidden_change_operation_count'] = max(0, count($modelGroup['change_operations']) - self::MAX_MODEL_CHANGE_OPERATIONS);

            foreach ($modelGroup['records'] as &$record) {
                [$record['sources'], $record['source_count'], $record['hidden_source_count']] = $this->finalizeModelSources(
                    $record['sources'],
                    $querySources,
                    self::MAX_RECORD_SOURCES,
                );
            }
            unset($record);

            $modelGroup['records'] = array_values($modelGroup['records']);
            $modelGroup['record_count'] = count($modelGroup['records']);
            $modelGroup['repeated_load_count'] = array_sum(array_map(
                fn (array $record): int => max(0, $record['loads'] - 1),
                $modelGroup['records'],
            ));
            usort($modelGroup['records'], fn (array $left, array $right): int => $right['loads'] <=> $left['loads']
                ?: ($left['first_seen_ms'] ?? PHP_FLOAT_MAX) <=> ($right['first_seen_ms'] ?? PHP_FLOAT_MAX)
                ?: strnatcasecmp((string) $left['key'], (string) $right['key']));

            $modelGroup['hidden_record_count'] = max(0, $modelGroup['record_count'] - self::MAX_MODEL_RECORDS);
            [$modelGroup['sources'], $modelGroup['source_count'], $modelGroup['hidden_source_count'], $modelGroup['related_query_count']] = $this->finalizeModelSources(
                $modelGroup['sources'],
                $querySources,
                self::MAX_MODEL_SOURCES,
            );
            $modelGroup['activity_count'] = $modelGroup['load_count'] + $modelGroup['change_count'];
            $modelGroup['guidance'] = $this->modelGuidance($modelGroup);
        }
        unset($modelGroup);

        $modelGroups = array_values($modelGroups);
        usort($modelGroups, fn (array $left, array $right): int => ($right['change_count'] > 0) <=> ($left['change_count'] > 0)
            ?: $right['change_count'] <=> $left['change_count']
            ?: $right['repeated_load_count'] <=> $left['repeated_load_count']
            ?: $right['load_count'] <=> $left['load_count']
            ?: $right['total_count'] <=> $left['total_count']
            ?: strcasecmp((string) $left['model'], (string) $right['model'])
            ?: strcasecmp((string) ($left['connection'] ?? ''), (string) ($right['connection'] ?? '')));
        $modelGroupPreviews = array_map($this->modelGroupPreview(...), $modelGroups);

        if (isset($profile['inspectors']['models'])) {
            $profile['inspectors']['models']['summary']['model_classes'] = count(array_unique(array_column($items, 'model')));
            $profile['inspectors']['models']['summary']['model_contexts'] = count($modelGroups);
            $profile['inspectors']['models']['summary']['lifecycle_events'] = $events;
            $profile['inspectors']['models']['summary']['retained_lifecycle_event_count'] = count($items);
            $profile['inspectors']['models']['summary']['retrieval_count'] = array_sum(array_column($modelGroups, 'load_count'));
            $profile['inspectors']['models']['summary']['distinct_record_count'] = array_sum(array_column($modelGroups, 'record_count'));
            $profile['inspectors']['models']['summary']['unidentified_load_count'] = array_sum(array_column($modelGroups, 'unidentified_load_count'));
            $profile['inspectors']['models']['summary']['repeated_load_count'] = array_sum(array_column($modelGroups, 'repeated_load_count'));
            $profile['inspectors']['models']['summary']['model_change_count'] = array_sum(array_column($modelGroups, 'change_count'));
            $profile['inspectors']['models']['summary']['activity_count'] = $profile['inspectors']['models']['summary']['retrieval_count']
                + $profile['inspectors']['models']['summary']['model_change_count'];
            $profile['inspectors']['models']['summary']['intermediate_lifecycle_event_count'] = max(
                0,
                count($items) - $profile['inspectors']['models']['summary']['activity_count'],
            );
            $profile['inspectors']['models']['summary']['unknown_source_activity_count'] = array_sum(array_column($modelGroups, 'unknown_source_activity_count'));
            $profile['inspectors']['models']['summary']['model_change_events'] = array_reduce(
                $modelGroups,
                function (array $events, array $group): array {
                    foreach ($group['change_events'] as $event => $count) {
                        $events[$event] = ($events[$event] ?? 0) + $count;
                    }

                    return $events;
                },
                [],
            );
            $profile['inspectors']['models']['summary']['guidance_count'] = array_sum(array_map(
                fn (array $group): int => count($group['guidance']),
                $modelGroups,
            ));
            $profile['inspectors']['models']['payload']['groups'] = $groups;
            $profile['inspectors']['models']['payload']['model_groups'] = $modelGroups;
            $profile['inspectors']['models']['payload']['model_group_previews'] = $modelGroupPreviews;
        }

        return $profile;
    }

    /** @param array<string, array<string, mixed>> $sources @param array<string, mixed> $item */
    private function addModelSource(array &$sources, array $item, string $activity, string $event): bool
    {
        $callsite = $this->modelCallsite($item['callsite'] ?? null);

        if ($callsite === null) {
            return false;
        }

        $key = $this->modelCallsiteKey($callsite);
        $sources[$key] ??= [
            'callsite' => $callsite,
            'activity_count' => 0,
            'retrieval_count' => 0,
            'change_count' => 0,
            'change_events' => [],
            'first_seen_ms' => null,
            'last_seen_ms' => null,
        ];
        $source = &$sources[$key];
        $source['activity_count']++;

        if ($activity === 'retrieved') {
            $source['retrieval_count']++;
        } else {
            $source['change_count']++;
            $source['change_events'][$event] = ($source['change_events'][$event] ?? 0) + 1;
        }

        $this->addModelTiming($source, $item);
        unset($source);

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @param  array<string, array<string, int|float>>  $querySources
     * @return array{list<array<string, mixed>>, int, int, int}
     */
    private function finalizeModelSources(array $sources, array $querySources, int $limit): array
    {
        foreach ($sources as $key => &$source) {
            $queries = $querySources[$key] ?? [];
            $source['query_count'] = (int) ($queries['count'] ?? 0);
            $source['query_duration_ms'] = round((float) ($queries['duration_ms'] ?? 0), 2);
            $source['query_read_count'] = (int) ($queries['read_count'] ?? 0);
            $source['query_write_count'] = (int) ($queries['write_count'] ?? 0);
        }
        unset($source);

        $sources = array_values($sources);
        usort($sources, fn (array $left, array $right): int => $right['activity_count'] <=> $left['activity_count']
            ?: $right['query_count'] <=> $left['query_count']
            ?: strcasecmp($this->modelCallsiteKey($left['callsite']), $this->modelCallsiteKey($right['callsite'])));
        $total = count($sources);
        $relatedQueryCount = array_sum(array_column($sources, 'query_count'));

        return [$sources, $total, max(0, $total - $limit), $relatedQueryCount];
    }

    /** @param list<array<string, mixed>> $candidates @return array<string, mixed> */
    private function modelChangeOperation(array $candidates): array
    {
        $event = null;

        foreach ($candidates as $candidate) {
            $operation = $candidate['operation'] ?? null;

            if (is_string($operation) && in_array($operation, self::MODEL_CHANGE_EVENTS, true)) {
                $event = $operation;
            }
        }

        if ($event === null) {
            $priority = ['created' => 10, 'updated' => 10, 'deleted' => 10, 'trashed' => 20, 'restored' => 20, 'forceDeleted' => 30];
            $event = (string) ($candidates[0]['event'] ?? 'changed');

            foreach ($candidates as $candidate) {
                $candidateEvent = (string) ($candidate['event'] ?? 'changed');

                if (($priority[$candidateEvent] ?? 0) > ($priority[$event] ?? 0)) {
                    $event = $candidateEvent;
                }
            }
        }

        $selected = $candidates[array_key_last($candidates)];
        $changes = $selected;

        foreach ($candidates as $candidate) {
            if (($candidate['event'] ?? null) === $event) {
                $selected = $candidate;
            }

            if ((int) ($candidate['change_attribute_count'] ?? 0) > (int) ($changes['change_attribute_count'] ?? 0)) {
                $changes = $candidate;
            }
        }

        $callsite = $this->modelCallsite($selected['callsite'] ?? null);

        if ($callsite === null) {
            foreach ($candidates as $candidate) {
                $callsite = $this->modelCallsite($candidate['callsite'] ?? null);

                if ($callsite !== null) {
                    break;
                }
            }
        }

        return [
            'event' => $event,
            'key_name' => $selected['key_name'] ?? 'id',
            'key' => $selected['key'] ?? null,
            'at_ms' => $selected['at_ms'] ?? null,
            'callsite' => $callsite,
            'change_attribute_count' => (int) ($changes['change_attribute_count'] ?? 0),
            'changes' => is_array($changes['changes'] ?? null) ? $changes['changes'] : [],
            'changes_truncated' => (bool) ($changes['changes_truncated'] ?? false),
            'lifecycle_events' => array_count_values(array_map(
                fn (array $candidate): string => (string) ($candidate['event'] ?? 'unknown'),
                $candidates,
            )),
        ];
    }

    /** @param list<array<string, mixed>> $candidates */
    private function hasCompletedModelChange(array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate['event'] ?? null, self::MODEL_CHANGE_EVENTS, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, int|float>> */
    private function querySources(array $profile): array
    {
        $sources = [];

        foreach ($this->items($profile, 'queries') as $query) {
            $callsite = $this->modelCallsite($query['callsite'] ?? null);

            if ($callsite === null) {
                continue;
            }

            $key = $this->modelCallsiteKey($callsite);
            $sources[$key] ??= ['count' => 0, 'duration_ms' => 0.0, 'read_count' => 0, 'write_count' => 0];
            $sources[$key]['count']++;
            $sources[$key]['duration_ms'] += (float) ($query['duration_ms'] ?? 0);
            $queryType = ($query['query_type'] ?? null) === 'read' ? 'read_count' : 'write_count';
            $sources[$key][$queryType]++;
        }

        return $sources;
    }

    /** @return array{file: string, line: int}|null */
    private function modelCallsite(mixed $callsite): ?array
    {
        if (! is_array($callsite) || ! isset($callsite['file'], $callsite['line'])) {
            return null;
        }

        $normalized = [
            'file' => (string) $callsite['file'],
            'line' => max(1, (int) $callsite['line']),
        ];

        if (($callsite['kind'] ?? null) === 'compiled_view') {
            $normalized['kind'] = 'compiled_view';
        }

        if (is_string($callsite['template_file'] ?? null) && $callsite['template_file'] !== '') {
            $normalized['template_file'] = $callsite['template_file'];
        }

        return $normalized;
    }

    /** @param array{file: string, line: int} $callsite */
    private function modelCallsiteKey(array $callsite): string
    {
        return $callsite['file'].':'.$callsite['line'];
    }

    /** @param array<string, mixed> $target @param array<string, mixed> $item */
    private function addModelTiming(array &$target, array $item): void
    {
        if (! isset($item['at_ms']) || ! is_numeric($item['at_ms'])) {
            return;
        }

        $at = round((float) $item['at_ms'], 3);
        $target['first_seen_ms'] = $target['first_seen_ms'] === null ? $at : min($target['first_seen_ms'], $at);
        $target['last_seen_ms'] = $target['last_seen_ms'] === null ? $at : max($target['last_seen_ms'], $at);
    }

    /** @param array<string, mixed> $group @return array<string, mixed> */
    private function modelGroupPreview(array $group): array
    {
        $group['change_operations'] = array_slice(
            is_array($group['change_operations'] ?? null) ? $group['change_operations'] : [],
            0,
            self::MAX_MODEL_CHANGE_OPERATIONS,
        );
        $group['sources'] = array_slice(
            is_array($group['sources'] ?? null) ? $group['sources'] : [],
            0,
            self::MAX_MODEL_SOURCES,
        );
        $group['records'] = array_slice(
            is_array($group['records'] ?? null) ? $group['records'] : [],
            0,
            self::MAX_MODEL_RECORDS,
        );

        foreach ($group['records'] as &$record) {
            $record['sources'] = array_slice(
                is_array($record['sources'] ?? null) ? $record['sources'] : [],
                0,
                self::MAX_RECORD_SOURCES,
            );
        }
        unset($record);

        return $group;
    }

    /** @param array<string, mixed> $group @return list<array<string, string>> */
    private function modelGuidance(array $group): array
    {
        $guidance = [];
        $extraRetrievals = (int) ($group['repeated_load_count'] ?? 0);
        $writes = (int) ($group['change_count'] ?? 0);
        $unknownSources = (int) ($group['unknown_source_activity_count'] ?? 0);
        $relatedQueries = (int) ($group['related_query_count'] ?? 0);
        $changedAttributes = array_sum(array_map(
            fn (array $operation): int => (int) ($operation['change_attribute_count'] ?? 0),
            is_array($group['change_operations'] ?? null) ? $group['change_operations'] : [],
        ));
        $compiledSources = array_values(array_filter(
            is_array($group['sources'] ?? null) ? $group['sources'] : [],
            fn (array $source): bool => ($source['callsite']['kind'] ?? null) === 'compiled_view',
        ));

        if ($extraRetrievals > 0) {
            $guidance[] = [
                'type' => 'extra_retrievals',
                'summary' => sprintf('%d extra retrieval %s were observed for already identified records.', $extraRetrievals, $extraRetrievals === 1 ? 'event' : 'events'),
                'why' => 'The same model class and record identifier emitted more than one retrieved event during this request.',
                'next' => 'Check repeated relationship access, loops, and missing eager loading before deciding whether the work is avoidable.',
            ];
        }

        if ($writes > 0) {
            $guidance[] = [
                'type' => 'write_evidence',
                'summary' => sprintf('%d logical write %s were folded from completed Eloquent lifecycle callbacks.', $writes, $writes === 1 ? 'operation' : 'operations'),
                'why' => $changedAttributes > 0
                    ? sprintf('%d changed attribute %s were retained with capture-time redaction.', $changedAttributes, $changedAttributes === 1 ? 'value' : 'values')
                    : 'Laravel reported completed write activity without retained changed attributes.',
                'next' => $changedAttributes > 0
                    ? 'Review the changed attributes and source when the write was unexpected.'
                    : 'Review the write source and lifecycle event when the operation was unexpected.',
            ];
        }

        if ($unknownSources > 0) {
            $guidance[] = [
                'type' => 'missing_source',
                'summary' => sprintf('Application source was unavailable for %d model %s.', $unknownSources, $unknownSources === 1 ? 'activity' : 'activities'),
                'why' => 'The activity count is complete, but the captured stack had no application file New Debug Bar could retain.',
                'next' => 'Use the model identity, timing, and nearby request activity to narrow the source.',
            ];
        }

        if ($compiledSources !== []) {
            $templateFiles = array_values(array_unique(array_filter(array_map(
                fn (array $source): ?string => is_string($source['callsite']['template_file'] ?? null)
                    ? $source['callsite']['template_file']
                    : null,
                $compiledSources,
            ))));
            $guidance[] = [
                'type' => 'compiled_blade_source',
                'summary' => 'Model activity was observed while Laravel executed a compiled Blade view.',
                'why' => $templateFiles === []
                    ? 'The exact compiled PHP location was retained, but its template path was unavailable.'
                    : 'The compiled PHP location and original Blade template path were both retained.',
                'next' => $templateFiles === []
                    ? 'Inspect the compiled view location and the Blade template that produced it.'
                    : 'Inspect '.implode(', ', $templateFiles).' for model access inside rendering.',
            ];
        }

        if ($relatedQueries > 0) {
            $guidance[] = [
                'type' => 'query_correlation',
                'summary' => sprintf('%d captured %s shared an exact source location with this model activity.', $relatedQueries, $relatedQueries === 1 ? 'query' : 'queries'),
                'why' => 'An exact source match is useful correlation evidence, but it does not prove which query hydrated or changed the model.',
                'next' => 'Inspect the related queries together with the model timing and operation evidence.',
            ];
        }

        return $guidance;
    }

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    private function views(array $profile): array
    {
        $items = $this->items($profile, 'views');
        $groups = [];
        $sourceCounts = ['application' => 0, 'framework' => 0];
        $uniqueNames = [];

        foreach ($items as $index => $item) {
            $item['render_order'] = $index + 1;
            $item['origin'] = $this->viewOrigin($item);
            $item['source_label'] = $this->viewSourceLabel($item['source'] ?? null, includeLine: false);
            $item['source_kind'] = $this->viewSourceKind($item);
            $item['composers'] = array_values(array_map(function (mixed $composer): array {
                $composer = is_array($composer) ? $composer : [];

                return [
                    ...$composer,
                    'source_label' => $this->viewSourceLabel($composer['source'] ?? null),
                ];
            }, is_array($item['composers'] ?? null) ? $item['composers'] : []));
            $item['composer_count'] = count(is_array($item['composers'] ?? null) ? $item['composers'] : []);
            $item['data_key_count'] = count(is_array($item['data'] ?? null) ? $item['data'] : []);

            if (isset($profile['inspectors']['views']['payload']['items'][$index])) {
                $profile['inspectors']['views']['payload']['items'][$index] = $item;
            }

            $name = trim((string) ($item['name'] ?? '')) ?: 'Unnamed view';
            $signature = $item['origin']."\0".$name;
            $sourceCounts[$item['origin']]++;
            $uniqueNames[$name] = true;

            $groups[$signature] ??= [
                'id' => 'view-'.(count($groups) + 1),
                'name' => $name,
                'display_name' => $this->viewDisplayName($name, $item),
                'origin' => $item['origin'],
                'count' => 0,
                'items' => [],
            ];
            $groups[$signature]['count']++;
            $groups[$signature]['items'][] = $item;
        }

        foreach ($groups as &$group) {
            $searchParts = [$group['name'], $group['origin']];

            foreach ($group['items'] as $item) {
                $searchParts[] = $item['source_label'];

                foreach ((array) ($item['composers'] ?? []) as $composer) {
                    $searchParts[] = is_array($composer) ? ($composer['name'] ?? '') : '';
                }
            }

            $group['search'] = mb_strtolower(implode(' ', array_filter(array_map('strval', $searchParts))));
        }
        unset($group);

        $groups = array_values($groups);
        usort($groups, static fn (array $left, array $right): int => ($left['origin'] === 'application' ? 0 : 1) <=> ($right['origin'] === 'application' ? 0 : 1)
            ?: ($left['items'][0]['render_order'] ?? 0) <=> ($right['items'][0]['render_order'] ?? 0));

        if (isset($profile['inspectors']['views'])) {
            $profile['inspectors']['views']['summary']['unique_views'] = count($uniqueNames);
            $profile['inspectors']['views']['summary']['application_count'] = $sourceCounts['application'];
            $profile['inspectors']['views']['summary']['framework_count'] = $sourceCounts['framework'];
            $profile['inspectors']['views']['summary']['application_views'] = count(array_filter(
                $groups,
                static fn (array $group): bool => $group['origin'] === 'application',
            ));
            $profile['inspectors']['views']['summary']['framework_views'] = count(array_filter(
                $groups,
                static fn (array $group): bool => $group['origin'] === 'framework',
            ));
            $profile['inspectors']['views']['payload']['groups'] = $groups;
        }

        return $profile;
    }

    /** @param array<string, mixed> $item */
    private function viewOrigin(array $item): string
    {
        $file = str_replace('\\', '/', (string) ($item['source']['file'] ?? ''));

        if (
            str_starts_with($file, '/')
            || preg_match('/^[A-Za-z]:\//', $file) === 1
            || str_starts_with($file, 'phar://')
            || str_starts_with($file, 'vendor/')
            || str_starts_with($file, 'storage/framework/views/')
            || str_contains($file, '/vendor/')
            || str_contains($file, '/storage/framework/views/')
        ) {
            return 'framework';
        }

        return 'application';
    }

    /** @param array<string, mixed> $item */
    private function viewSourceKind(array $item): string
    {
        $file = str_replace('\\', '/', (string) ($item['source']['file'] ?? ''));

        if ($file === '') {
            return 'unavailable';
        }

        if (str_starts_with($file, 'storage/framework/views/') || str_contains($file, '/storage/framework/views/')) {
            return 'compiled';
        }

        return $item['origin'] === 'framework' ? 'framework' : 'template';
    }

    /** @param array<string, mixed> $item */
    private function viewDisplayName(string $name, array $item): string
    {
        if ($item['origin'] === 'application') {
            return $name;
        }

        if ($item['source_kind'] === 'compiled' && (str_contains($name, '/') || str_ends_with($name, '.php'))) {
            return 'Compiled Blade view';
        }

        if (str_contains($name, '/') || str_contains($name, '\\')) {
            $basename = pathinfo(str_replace('\\', '/', $name), PATHINFO_FILENAME);

            return str_ends_with($basename, '.blade') ? substr($basename, 0, -6) : $basename;
        }

        return $name;
    }

    private function viewSourceLabel(mixed $source, bool $includeLine = true): ?string
    {
        if (! is_array($source) || ! is_string($source['file'] ?? null) || $source['file'] === '') {
            return null;
        }

        $line = is_numeric($source['line'] ?? null) && (int) $source['line'] > 0
            ? (int) $source['line']
            : null;

        return $includeLine && $line !== null ? $source['file'].':'.$line : $source['file'];
    }

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    private function events(array $profile): array
    {
        if (! isset($profile['inspectors']['events']['payload']['items'])) {
            return $profile;
        }

        $items = $this->items($profile, 'events');
        $groups = [];
        $sourceCounts = ['application' => 0, 'framework' => 0];

        foreach ($items as $index => $item) {
            $item = $this->normalizeEvent($item, $index + 1);
            $profile['inspectors']['events']['payload']['items'][$index] = $item;
            $sourceCounts[$item['source']]++;
            $signature = $this->eventSignature($item);

            if (! isset($groups[$signature])) {
                $groups[$signature] = [
                    ...$item,
                    'id' => $item['sequence'],
                    'occurrence_count' => 0,
                    'first_sequence' => $item['sequence'],
                    'last_sequence' => $item['sequence'],
                    'first_at_ms' => null,
                    'last_at_ms' => null,
                    'span_ms' => 0.0,
                    'occurrences' => [],
                    'dispatch_sources' => [],
                ];
            }

            $group = &$groups[$signature];
            $group['occurrence_count']++;
            $group['last_sequence'] = $item['sequence'];
            $group['occurrences'][] = [
                'sequence' => $item['sequence'],
                'at_ms' => $item['at_ms'],
                'lifecycle' => $item['lifecycle'] ?? null,
                'after_response_ms' => isset($item['after_response_ms']) && is_numeric($item['after_response_ms'])
                    ? round((float) $item['after_response_ms'], 3)
                    : null,
                'callsite' => $item['callsite'],
            ];
            $this->addEventTiming($group, $item);
            $this->addEventDispatchSource($group, $item);
            unset($group);
        }

        foreach ($groups as &$group) {
            $group['dispatch_sources'] = array_values($group['dispatch_sources']);
            usort($group['dispatch_sources'], fn (array $left, array $right): int => $right['count'] <=> $left['count']
                ?: ($left['sequences'][0] ?? 0) <=> ($right['sequences'][0] ?? 0));
            $group['span_ms'] = $group['first_at_ms'] !== null && $group['last_at_ms'] !== null
                ? round($group['last_at_ms'] - $group['first_at_ms'], 3)
                : 0.0;
            $group['search'] = $this->eventSearchText($group);
            $group['next_step'] = $this->eventNextStep($group);
            $group['related_inspector'] = $this->relatedEventInspector($group['name']);
            $group['dispatch_source_count'] = count($group['dispatch_sources']);
            $group['dispatch_source_omitted_count'] = max(
                0,
                $group['dispatch_source_count'] - self::EVENT_DISPATCH_SOURCE_LIMIT,
            );
            $group['dispatch_sources'] = array_slice(
                $group['dispatch_sources'],
                0,
                self::EVENT_DISPATCH_SOURCE_LIMIT,
            );
            $group['occurrence_omitted_count'] = max(
                0,
                $group['occurrence_count'] - self::EVENT_OCCURRENCE_EVIDENCE_LIMIT,
            );
            $group['occurrences'] = $this->boundedEventOccurrences($group['occurrences']);
        }
        unset($group);

        $groups = array_values($groups);

        $profile['inspectors']['events']['summary'] = [
            ...($profile['inspectors']['events']['summary'] ?? []),
            'application_count' => $sourceCounts['application'],
            'framework_count' => $sourceCounts['framework'],
            'group_count' => count($groups),
            'application_group_count' => count(array_filter(
                $groups,
                fn (array $group): bool => $group['source'] === 'application',
            )),
            'framework_group_count' => count(array_filter(
                $groups,
                fn (array $group): bool => $group['source'] === 'framework',
            )),
        ];
        $profile['inspectors']['events']['payload']['groups'] = $groups;

        return $profile;
    }

    /** @param list<array<string, mixed>> $occurrences @return list<array<string, mixed>> */
    private function boundedEventOccurrences(array $occurrences): array
    {
        if (count($occurrences) <= self::EVENT_OCCURRENCE_EVIDENCE_LIMIT) {
            return $occurrences;
        }

        $firstCount = intdiv(self::EVENT_OCCURRENCE_EVIDENCE_LIMIT, 2);

        return [
            ...array_slice($occurrences, 0, $firstCount),
            ...array_slice($occurrences, -(self::EVENT_OCCURRENCE_EVIDENCE_LIMIT - $firstCount)),
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function normalizeEvent(array $item, int $sequence): array
    {
        $name = trim((string) ($item['name'] ?? ''));
        $name = $name === '' ? 'Unknown event' : $name;
        $separator = strrpos($name, '\\');
        $listeners = array_values(array_filter(
            is_array($item['listeners'] ?? null) ? $item['listeners'] : [],
            'is_array',
        ));

        foreach ($listeners as &$listener) {
            $listener['name'] = trim((string) ($listener['name'] ?? 'Listener')) ?: 'Listener';
            $listener['registrations'] = max(1, (int) ($listener['registrations'] ?? 1));
            $listener['queued'] = (bool) ($listener['queued'] ?? false);
            $listener['outcome'] = $listener['queued'] ? 'queued' : 'completed';
            $listener['source'] = is_array($listener['source'] ?? null) ? $listener['source'] : null;
        }
        unset($listener);

        $listenerCount = array_sum(array_column($listeners, 'registrations'));
        $queuedCount = array_sum(array_map(
            fn (array $listener): int => $listener['queued'] ? $listener['registrations'] : 0,
            $listeners,
        ));
        $completedCount = $listenerCount - $queuedCount;
        $payloadShape = $this->eventPayloadShape($item);
        $callsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;

        return [
            ...$item,
            'name' => $name,
            'display_name' => $separator === false ? $name : substr($name, $separator + 1),
            'namespace' => $separator === false ? null : substr($name, 0, $separator),
            'sequence' => $sequence,
            'source' => preg_match('/^(Illuminate|Laravel|Livewire|Symfony)\\\\/', $name) === 1
                ? 'framework'
                : 'application',
            'listeners' => $listeners,
            'listener_count' => $listenerCount,
            'listener_group_count' => count($listeners),
            'queued_listener_count' => $queuedCount,
            'completed_listener_count' => $completedCount,
            'duplicate_registration_count' => array_sum(array_map(
                fn (array $listener): int => max(0, $listener['registrations'] - 1),
                $listeners,
            )),
            'listener_outcome' => match (true) {
                $listenerCount === 0 => 'observed',
                $queuedCount === $listenerCount => 'queued',
                $queuedCount > 0 => 'mixed',
                default => 'completed',
            },
            'listener_outcome_label' => match (true) {
                $listenerCount === 0 => 'No listeners',
                $queuedCount === $listenerCount => 'Queued',
                $queuedCount > 0 => 'Completed and queued',
                default => 'Completed',
            },
            'listener_summary' => $this->eventListenerSummary($completedCount, $queuedCount),
            'payload_shape' => $payloadShape,
            'payload_field_count' => array_sum(array_column($payloadShape, 'field_count')),
            'callsite' => $callsite,
            'stack' => array_values(array_filter(
                is_array($item['stack'] ?? null) ? $item['stack'] : [],
                'is_array',
            )),
            'at_ms' => isset($item['at_ms']) && is_numeric($item['at_ms'])
                ? round((float) $item['at_ms'], 3)
                : null,
        ];
    }

    /** @param array<string, mixed> $item @return list<array<string, mixed>> */
    private function eventPayloadShape(array $item): array
    {
        $shape = array_values(array_filter(
            is_array($item['payload_shape'] ?? null) ? $item['payload_shape'] : [],
            'is_array',
        ));

        if ($shape === []) {
            $types = array_values(array_filter(
                is_array($item['payload_types'] ?? null) ? $item['payload_types'] : [],
                'is_string',
            ));
            $shape = array_map(
                fn (string $type, int $index): array => [
                    'position' => $index + 1,
                    'type' => $type,
                    'fields' => [],
                    'field_count' => 0,
                    'truncated' => false,
                ],
                $types,
                array_keys($types),
            );
        }

        return array_map(function (array $entry, int $index): array {
            $fields = array_values(array_map(
                'strval',
                array_slice(is_array($entry['fields'] ?? null) ? $entry['fields'] : [], 0, 25),
            ));

            return [
                'position' => max(1, (int) ($entry['position'] ?? $index + 1)),
                'type' => trim((string) ($entry['type'] ?? 'mixed')) ?: 'mixed',
                'fields' => $fields,
                'field_count' => max(count($fields), (int) ($entry['field_count'] ?? count($fields))),
                'truncated' => (bool) ($entry['truncated'] ?? false),
            ];
        }, array_slice($shape, 0, 10), array_keys(array_slice($shape, 0, 10)));
    }

    private function eventListenerSummary(int $completed, int $queued): string
    {
        if ($completed === 0 && $queued === 0) {
            return 'No listeners registered.';
        }

        if ($queued === 0) {
            return $completed.' '.($completed === 1 ? 'registration completed.' : 'registrations completed.');
        }

        if ($completed === 0) {
            return $queued.' queued '.($queued === 1 ? 'registration handed off.' : 'registrations handed off.');
        }

        return $completed.' completed, '.$queued.' queued.';
    }

    /** @param array<string, mixed> $item */
    private function eventSignature(array $item): string
    {
        $signature = json_encode([
            $item['name'],
            $item['source'],
            $item['broadcast'] ?? false,
            array_map(fn (array $listener): array => [
                $listener['name'],
                $listener['registrations'],
                $listener['queued'],
            ], $item['listeners']),
            array_map(fn (array $entry): array => [
                $entry['type'],
                $entry['fields'],
                $entry['field_count'],
            ], $item['payload_shape']),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', is_string($signature) ? $signature : $item['name']);
    }

    /** @param array<string, mixed> $group @param array<string, mixed> $item */
    private function addEventTiming(array &$group, array $item): void
    {
        if ($item['at_ms'] === null) {
            return;
        }

        $group['first_at_ms'] = $group['first_at_ms'] === null
            ? $item['at_ms']
            : min($group['first_at_ms'], $item['at_ms']);
        $group['last_at_ms'] = $group['last_at_ms'] === null
            ? $item['at_ms']
            : max($group['last_at_ms'], $item['at_ms']);
    }

    /** @param array<string, mixed> $group @param array<string, mixed> $item */
    private function addEventDispatchSource(array &$group, array $item): void
    {
        if ($item['callsite'] === null) {
            return;
        }

        $file = (string) ($item['callsite']['file'] ?? '');
        $line = (int) ($item['callsite']['line'] ?? 0);

        if ($file === '' || $line < 1) {
            return;
        }

        $key = $file.':'.$line;
        $group['dispatch_sources'][$key] ??= [
            'file' => $file,
            'line' => $line,
            'count' => 0,
            'sequences' => [],
        ];
        $group['dispatch_sources'][$key]['count']++;
        $group['dispatch_sources'][$key]['sequences'][] = $item['sequence'];
    }

    /** @param array<string, mixed> $group */
    private function eventSearchText(array $group): string
    {
        $parts = [
            $group['name'],
            $group['display_name'],
            $group['namespace'],
            $group['source'],
            $group['listener_outcome_label'],
            ...array_column($group['listeners'], 'name'),
            ...array_column($group['dispatch_sources'], 'file'),
        ];

        foreach ($group['payload_shape'] as $entry) {
            $parts[] = $entry['type'];
            array_push($parts, ...$entry['fields']);
        }

        return mb_strtolower(implode(' ', array_filter($parts, fn (mixed $part): bool => is_scalar($part))));
    }

    /** @param array<string, mixed> $group */
    private function eventNextStep(array $group): string
    {
        if ($group['duplicate_registration_count'] > 0) {
            return 'The same listener is registered more than once. Check explicit registration and event discovery.';
        }

        if ($group['queued_listener_count'] > 0) {
            return $group['queued_listener_count'] === 1
                ? 'Open Queue to confirm the queued listener ran.'
                : 'Open Queue to confirm the queued listeners ran.';
        }

        if ($group['listener_count'] === 0) {
            if ($group['source'] === 'framework') {
                return 'Use the related collector when this framework event looks unexpected.';
            }

            return $group['dispatch_sources'] !== []
                ? 'Start at the dispatch source, then check listener registration and event discovery.'
                : 'Check listener registration and event discovery to confirm whether this event is observation-only.';
        }

        if (($group['broadcast'] ?? false) === true) {
            return 'Check the broadcast channel and frontend subscription if connected clients did not update.';
        }

        if (count($group['dispatch_sources']) > 1) {
            return $group['listener_count'] === 1
                ? 'Compare the dispatch sources, then inspect the registered listener.'
                : 'Compare the dispatch sources, then inspect the registered listeners.';
        }

        if ($group['source'] === 'application' && $group['dispatch_sources'] !== []) {
            return $group['listener_count'] === 1
                ? 'Start at the dispatch source, then inspect the registered listener.'
                : 'Start at the dispatch source, then inspect the registered listeners.';
        }

        return $group['listener_count'] === 1
            ? 'Inspect the listener source when the observed result does not match the event.'
            : 'Inspect the listener sources when the observed result does not match the event.';
    }

    /** @return array{key: string, label: string}|null */
    private function relatedEventInspector(string $name): ?array
    {
        foreach ([
            'Illuminate\\Database\\' => ['queries', 'Queries'],
            'Illuminate\\Cache\\' => ['cache', 'Cache'],
            'Illuminate\\Queue\\' => ['queue', 'Queue'],
            'Illuminate\\Mail\\' => ['mail', 'Mail'],
            'Illuminate\\Notifications\\' => ['notifications', 'Notifications'],
            'Illuminate\\Http\\Client\\' => ['http_client', 'HTTP Client'],
            'Illuminate\\Auth\\Access\\' => ['authorization', 'Authorization'],
        ] as $prefix => [$key, $label]) {
            if (str_starts_with($name, $prefix)) {
                return ['key' => $key, 'label' => $label];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $profile @return list<array<string, mixed>> */
    private function items(array $profile, string $inspector): array
    {
        $items = $profile['inspectors'][$inspector]['payload']['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
