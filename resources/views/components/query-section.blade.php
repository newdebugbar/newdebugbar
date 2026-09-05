@props([
    'section',
    'queryExplains' => [],
    'queryExplainErrors' => [],
])

@php
    $querySummary = is_array($section['summary'] ?? null) ? $section['summary'] : [];
    $queryItems = array_values(is_array($section['payload']['items'] ?? null) ? $section['payload']['items'] : []);
    $queryGroups = collect(is_array($section['payload']['repeated_groups'] ?? null) ? $section['payload']['repeated_groups'] : [])
        ->keyBy('fingerprint');

    $formatDuration = \NewDebugBar\Support\DurationFormatter::format(...);

    $enrichQuery = static function (array $query) use ($formatDuration, $queryExplains, $queryExplainErrors): array {
        $execution = max(1, (int) ($query['execution'] ?? 1));
        $bindings = array_values(is_array($query['bindings'] ?? null) ? $query['bindings'] : []);
        $bindingsComplete = ($query['bindings_complete'] ?? false) === true;
        $stack = array_values(is_array($query['stack'] ?? null) ? $query['stack'] : []);
        $callsite = is_array($query['callsite'] ?? null) ? $query['callsite'] : null;
        $file = is_string($callsite['file'] ?? null) && $callsite['file'] !== '' ? $callsite['file'] : null;
        $line = is_numeric($callsite['line'] ?? null) ? (int) $callsite['line'] : null;
        $sourceAvailable = $file !== null && $line !== null;
        $queryType = (string) ($query['query_type'] ?? 'write');
        $driver = is_string($query['driver'] ?? null) && $query['driver'] !== ''
            ? $query['driver']
            : 'unknown';
        $runnableAvailable = ($query['runnable_available'] ?? false)
            && is_string($query['runnable_sql'] ?? null)
            && $query['runnable_sql'] !== '';
        $displaySql = $runnableAvailable
            ? (string) $query['runnable_sql']
            : (string) ($query['sql'] ?? '');
        $duration = max(0, (float) ($query['duration_ms'] ?? 0));
        unset($query['bindings'], $query['runnable_sql'], $query['bindings_complete']);

        return [
            ...$query,
            'execution' => $execution,
            'sql' => (string) ($query['sql'] ?? ''),
            'stack' => $stack,
            'source_available' => $sourceAvailable,
            'source_label' => $sourceAvailable ? $file.':'.$line : 'Source unavailable',
            'driver' => $driver,
            'query_type' => $queryType,
            'duration_ms' => $duration,
            'duration_label' => $formatDuration($duration),
            'query_time_percent' => round((float) ($query['query_time_percent'] ?? 0), 1),
            'display_sql' => $displaySql,
            'display_sql_complete' => $runnableAvailable || ($bindingsComplete && $bindings === []),
            'explain_available' => $queryType === 'read' && $runnableAvailable,
            'explain_unavailable_reason' => $queryType !== 'read'
                ? 'EXPLAIN is available for read queries only.'
                : 'EXPLAIN needs preserved SQL and complete bindings.',
            'explain' => $queryExplains[$execution] ?? null,
            'explain_error' => $queryExplainErrors[$execution] ?? null,
            'search' => mb_strtolower(implode(' ', [
                (string) ($query['sql'] ?? ''),
                (string) ($query['normalized_sql'] ?? ''),
                (string) ($query['connection'] ?? ''),
                $driver,
                $sourceAvailable ? $file.':'.$line : '',
                json_encode($bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            ])),
        ];
    };

    $queryRecords = [];
    $seenGroups = [];

    foreach ($queryItems as $query) {
        $fingerprint = (string) ($query['fingerprint'] ?? '');
        $repeated = (bool) ($query['repeated'] ?? false);
        $group = $repeated && $fingerprint !== '' ? $queryGroups->get($fingerprint) : null;

        if ($repeated && is_array($group)) {
            if (isset($seenGroups[$fingerprint])) {
                continue;
            }

            $seenGroups[$fingerprint] = true;
            $executions = array_map($enrichQuery, array_values(is_array($group['executions'] ?? null) ? $group['executions'] : []));

            if ($executions === []) {
                $executions = [$enrichQuery($query)];
            }

            $first = $executions[0];
            $slow = collect($executions)->contains(fn (array $execution): bool => (bool) ($execution['slow'] ?? false));
            $count = count($executions);
            $sql = (string) ($group['sql'] ?? $first['normalized_sql'] ?? $first['sql']);
            $duration = max(0, (float) ($group['duration_ms'] ?? 0));

            $queryRecords[] = [
                'key' => 'group-'.$fingerprint,
                'execution' => (int) $first['execution'],
                'sql' => $sql,
                'connection' => (string) ($group['connection'] ?? $first['connection'] ?? 'default'),
                'driver' => (string) ($group['driver'] ?? $first['driver'] ?? 'unknown'),
                'query_type' => (string) ($group['query_type'] ?? $first['query_type'] ?? 'write'),
                'duration_ms' => $duration,
                'duration_label' => $formatDuration($duration),
                'query_time_percent' => round((float) ($group['query_time_percent'] ?? 0), 1),
                'count' => $count,
                'repeated' => true,
                'slow' => $slow,
                'attention' => true,
                'likely_n_plus_one' => (bool) ($group['likely_n_plus_one'] ?? false),
                'search' => mb_strtolower($sql.' '.implode(' ', array_column($executions, 'search'))),
                'executions' => $executions,
            ];

            continue;
        }

        $execution = $enrichQuery($query);
        $slow = (bool) ($execution['slow'] ?? false);

        $queryRecords[] = [
            'key' => 'query-'.$execution['execution'],
            'execution' => (int) $execution['execution'],
            'sql' => (string) ($execution['normalized_sql'] ?? $execution['sql']),
            'connection' => (string) ($execution['connection'] ?? 'default'),
            'driver' => (string) ($execution['driver'] ?? 'unknown'),
            'query_type' => (string) ($execution['query_type'] ?? 'write'),
            'duration_ms' => (float) ($execution['duration_ms'] ?? 0),
            'duration_label' => (string) ($execution['duration_label'] ?? $formatDuration($execution['duration_ms'] ?? 0)),
            'query_time_percent' => (float) ($execution['query_time_percent'] ?? 0),
            'count' => 1,
            'repeated' => false,
            'slow' => $slow,
            'attention' => $slow,
            'likely_n_plus_one' => false,
            'search' => $execution['search'],
            'executions' => [$execution],
        ];
    }

    usort($queryRecords, fn (array $left, array $right): int => $left['execution'] <=> $right['execution']);

    $queryRetainedCount = array_sum(array_column($queryRecords, 'count'));
    $queryAttentionCount = array_sum(array_map(
        fn (array $record): int => $record['attention'] ? $record['count'] : 0,
        $queryRecords,
    ));
    $queryReadCount = array_sum(array_map(
        fn (array $record): int => $record['query_type'] === 'read' ? $record['count'] : 0,
        $queryRecords,
    ));
    $queryWriteCount = array_sum(array_map(
        fn (array $record): int => $record['query_type'] === 'write' ? $record['count'] : 0,
        $queryRecords,
    ));
    $queryFilters = [
        'all' => ['All', $queryRetainedCount],
        'attention' => ['Needs attention', $queryAttentionCount],
        'read' => ['Reads', $queryReadCount],
        'write' => ['Writes', $queryWriteCount],
    ];
@endphp

<div
    data-ndb-queries
    x-init="initializeQueries(JSON.parse(atob($el.querySelector('[data-ndb-query-payload]').textContent.trim())))"
    @newdebugbar-query-explained.window="receiveQueryExplain($event.detail)"
    class="ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    <script type="application/json" data-ndb-query-payload>
        {{ base64_encode(json_encode($queryRecords, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)) }}
    </script>

    @if ($queryRecords !== [])
        <x-newdebugbar::inspector-workspace frame="top" data-ndb-query-workspace class="ndb:border-x-0">
            <x-newdebugbar::inspector-list-panel detail-open="queryDetailOpen" list-ref="queryList">
                <x-slot:controls>
                    <p
                        data-ndb-query-summary
                        aria-live="polite"
                        aria-atomic="true"
                        class="ndb:min-w-0 ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:dark:text-zinc-200"
                    >
                        <span data-ndb-query-summary-count>
                            {{ number_format($queryRetainedCount) }} {{ \Illuminate\Support\Str::plural('query', $queryRetainedCount) }}
                        </span>
                        <span
                            x-show.important="visibleQueryCount !== {{ $queryRetainedCount }}"
                            class="ndb:ml-1 ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
                        >
                            <span data-ndb-query-visible-count x-text="visibleQueryCount"></span>
                            shown
                        </span>
                        <span
                            data-ndb-query-total-time
                            class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:font-medium ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400"
                        >
                            {{ $formatDuration((float) ($querySummary['total_time_ms'] ?? 0)) }} total
                        </span>
                    </p>

                    <x-newdebugbar::inspector-list-controls :show-search="true">
                        <x-slot:search>
                            <x-newdebugbar::search-field
                                label="Search queries"
                                placeholder="Search SQL, bindings, or source"
                                data-ndb-query-search
                                x-model="querySearch"
                                @input.debounce.100ms="applyQueryView()"
                            />
                        </x-slot:search>
                        <x-slot:filter>
                            <x-newdebugbar::select-field
                                label="Filter queries"
                                data-ndb-query-filter
                                x-model="queryFilter"
                                @change="setQueryFilter($event.target.value)"
                            >
                                @foreach ($queryFilters as $filter => [$label, $count])
                                    <option value="{{ $filter }}">{{ $label }} ({{ $count }})</option>
                                @endforeach
                            </x-newdebugbar::select-field>
                        </x-slot:filter>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list data-ndb-query-list>
                    <div
                        data-ndb-query-list-heading
                        class="ndb:sticky ndb:top-0 ndb:z-10 ndb:grid ndb:grid-cols-[3.5rem_minmax(0,1fr)_4.75rem] ndb:items-center ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:bg-white/95 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:backdrop-blur-sm ndb:dark:border-zinc-800 ndb:dark:bg-zinc-950/95"
                    >
                        <span>Type</span>
                        <span>Query</span>
                        <span class="ndb:flex ndb:justify-end">
                            <x-newdebugbar::inspector-sort-heading
                                label="Time"
                                align="right"
                                active="querySort === 'duration'"
                                direction="querySortDirection"
                                data-ndb-query-sort-heading="duration"
                                @click="toggleQuerySort('duration')"
                            />
                        </span>
                    </div>

                    @foreach ($queryRecords as $record)
                        @php
                            $selectedClasses = match (true) {
                                $record['slow'] => 'ndb:bg-red-50/90 ndb:ring-1 ndb:ring-inset ndb:ring-red-200 ndb:dark:bg-red-950/35 ndb:dark:ring-red-900/80',
                                $record['repeated'] => 'ndb:bg-amber-50/90 ndb:ring-1 ndb:ring-inset ndb:ring-amber-200 ndb:dark:bg-amber-950/35 ndb:dark:ring-amber-900/80',
                                default => 'ndb:bg-indigo-50/90 ndb:ring-1 ndb:ring-inset ndb:ring-indigo-200 ndb:dark:bg-indigo-950/35 ndb:dark:ring-indigo-800/80',
                            };
                            $idleClasses = match (true) {
                                $record['slow'] => 'ndb:bg-red-50/70 ndb:hover:bg-red-100/75 ndb:dark:bg-red-950/25 ndb:dark:hover:bg-red-950/40',
                                $record['repeated'] => 'ndb:bg-amber-50/70 ndb:hover:bg-amber-100/75 ndb:dark:bg-amber-950/25 ndb:dark:hover:bg-amber-950/40',
                                default => 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:key="query-record-{{ $record['key'] }}"
                            data-ndb-query-item="{{ $record['key'] }}"
                            data-ndb-query-key="{{ $record['key'] }}"
                            data-ndb-execution="{{ $record['execution'] }}"
                            data-ndb-duration="{{ $record['duration_ms'] }}"
                            data-ndb-query-type="{{ $record['query_type'] }}"
                            data-ndb-attention="{{ $record['attention'] ? 'true' : 'false' }}"
                            data-ndb-slow="{{ $record['slow'] ? 'true' : 'false' }}"
                            data-ndb-repeated="{{ $record['repeated'] ? 'true' : 'false' }}"
                            data-ndb-search="{{ $record['search'] }}"
                            data-ndb-query-execution-count="{{ $record['count'] }}"
                            aria-controls="newdebugbar-query-detail"
                            @if ($record['repeated']) data-ndb-query-group="{{ $record['key'] }}" @endif
                            @click="selectQueryRecord({{ \Illuminate\Support\Js::from($record['key']) }})"
                            :aria-pressed="querySelected === {{ \Illuminate\Support\Js::from($record['key']) }}"
                            :class="querySelected === {{ \Illuminate\Support\Js::from($record['key']) }}
                                ? {{ \Illuminate\Support\Js::from($selectedClasses) }}
                                : {{ \Illuminate\Support\Js::from($idleClasses) }}"
                            class="ndb:grid ndb:h-auto ndb:w-full ndb:grid-cols-[3.5rem_minmax(0,1fr)_4.75rem] ndb:items-center ndb:gap-3 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
                        >
                            <span class="ndb:flex ndb:min-w-0 ndb:items-center">
                                <x-newdebugbar::inspector-operation-badge compact data-ndb-query-type-badge>
                                    {{ $record['query_type'] }}
                                </x-newdebugbar::inspector-operation-badge>
                                @if ($record['slow'])
                                    <span class="ndb:sr-only">{{ $record['repeated'] ? 'Slow repeated query.' : 'Slow query.' }}</span>
                                @elseif ($record['repeated'])
                                    <span class="ndb:sr-only">Repeated query.</span>
                                @endif
                            </span>

                            <code
                                data-ndb-query-list-sql
                                title="{{ $record['sql'] }}"
                                class="ndb:block ndb:max-h-10 ndb:min-w-0 ndb:overflow-hidden ndb:break-words ndb:text-xs ndb:font-semibold ndb:leading-5 ndb:text-zinc-800 ndb:dark:text-zinc-200"
                            >{{ $record['sql'] }}</code>

                            <span
                                data-ndb-query-list-outcome
                                class="ndb:flex ndb:min-w-0 ndb:flex-col ndb:items-end ndb:gap-0.5 ndb:text-right ndb:text-xs ndb:tabular-nums"
                            >
                                <span
                                    data-ndb-query-list-driver
                                    title="{{ $record['driver'] }}"
                                    class="ndb:h-auto ndb:max-w-full ndb:truncate ndb:bg-transparent ndb:[font-family:inherit] ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                >{{ $record['driver'] }}</span>
                                <strong
                                    data-ndb-query-list-duration
                                    class="ndb:font-bold ndb:text-zinc-700 ndb:dark:text-zinc-200"
                                >{{ $record['duration_label'] }}</strong>
                            </span>
                        </button>
                    @endforeach
                </x-slot:list>

                <x-slot:empty x-show.important="visibleQueryCount === 0">
                    <x-newdebugbar::empty-state label="No queries match these controls." />
                </x-slot:empty>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::query-detail />
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No database queries were captured for this request." />
    @endif
</div>
