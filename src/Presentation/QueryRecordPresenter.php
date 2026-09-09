<?php

namespace NewDebugBar\Presentation;

use NewDebugBar\Support\DurationFormatter;

/** Prepares query list records and per-run evidence for the inspector and MCP. */
final class QueryRecordPresenter
{
    /** @param array<string, mixed> $payload @return list<array<string, mixed>> */
    public function present(array $payload): array
    {
        $queryItems = array_values(is_array($payload['items'] ?? null) ? $payload['items'] : []);
        $queryGroups = collect(is_array($payload['repeated_groups'] ?? null) ? $payload['repeated_groups'] : [])
            ->keyBy('fingerprint');

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
                $executions = array_map($this->execution(...), array_values(is_array($group['executions'] ?? null) ? $group['executions'] : []));

                if ($executions === []) {
                    $executions = [$this->execution($query)];
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
                    'duration_label' => DurationFormatter::format($duration),
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

            $execution = $this->execution($query);
            $slow = (bool) ($execution['slow'] ?? false);

            $queryRecords[] = [
                'key' => 'query-'.$execution['execution'],
                'execution' => (int) $execution['execution'],
                'sql' => (string) ($execution['normalized_sql'] ?? $execution['sql']),
                'connection' => (string) ($execution['connection'] ?? 'default'),
                'driver' => (string) ($execution['driver'] ?? 'unknown'),
                'query_type' => (string) ($execution['query_type'] ?? 'write'),
                'duration_ms' => (float) ($execution['duration_ms'] ?? 0),
                'duration_label' => (string) ($execution['duration_label'] ?? DurationFormatter::format($execution['duration_ms'] ?? 0)),
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

        return $queryRecords;
    }

    /**
     * Adds transient EXPLAIN results without changing the captured records.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $explains
     * @param  array<int, string>  $errors
     * @return list<array<string, mixed>>
     */
    public function withExplains(array $records, array $explains, array $errors): array
    {
        foreach ($records as $recordIndex => $record) {
            foreach ($record['executions'] as $index => $execution) {
                $number = $execution['execution'];
                $records[$recordIndex]['executions'][$index]['explain'] = $explains[$number] ?? null;
                $records[$recordIndex]['executions'][$index]['explain_error'] = $errors[$number] ?? null;
            }
        }

        return $records;
    }

    /** @param list<array<string, mixed>> $records @return array<string, array{string, int}> */
    public function filters(array $records): array
    {
        $count = static fn (array $records): int => array_sum(array_column($records, 'count'));

        return [
            'all' => ['All', $count($records)],
            'attention' => ['Needs attention', $count(array_filter($records, fn (array $record): bool => $record['attention']))],
            'read' => ['Reads', $count(array_filter($records, fn (array $record): bool => $record['query_type'] === 'read'))],
            'write' => ['Writes', $count(array_filter($records, fn (array $record): bool => $record['query_type'] === 'write'))],
        ];
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function execution(array $query): array
    {
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
            'duration_label' => DurationFormatter::format($duration),
            'query_time_percent' => round((float) ($query['query_time_percent'] ?? 0), 1),
            'display_sql' => $displaySql,
            'display_sql_complete' => $runnableAvailable || ($bindingsComplete && $bindings === []),
            'explain_available' => $queryType === 'read' && $runnableAvailable,
            'explain_unavailable_reason' => $queryType !== 'read'
                ? 'EXPLAIN is available for read queries only.'
                : 'EXPLAIN needs preserved SQL and complete bindings.',
            'explain' => null,
            'explain_error' => null,
            'search' => mb_strtolower(implode(' ', [
                (string) ($query['sql'] ?? ''),
                (string) ($query['normalized_sql'] ?? ''),
                (string) ($query['connection'] ?? ''),
                $driver,
                $sourceAvailable ? $file.':'.$line : '',
                json_encode($bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            ])),
        ];
    }
}
