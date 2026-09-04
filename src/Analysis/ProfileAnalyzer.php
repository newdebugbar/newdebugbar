<?php

namespace NewDebugBar\Analysis;

use NewDebugBar\Support\DurationFormatter;

/** Produces bounded, deterministic findings from one captured profile. */
final class ProfileAnalyzer
{
    public function __construct(
        private readonly QueryAnalyzer $queries,
        private readonly float $slowRequestMs = 1_000,
        private readonly int $minimumCacheOperations = 5,
        private readonly float $highCacheMissRate = 0.8,
        private readonly int $minimumRepeatedExecutions = 4,
        private readonly float $minimumRepeatedDurationMs = 5,
        private readonly int $maxFindings = 50,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    public function analyze(array $profile): array
    {
        $sections = is_array($profile['sections'] ?? null) ? $profile['sections'] : [];
        $requestDuration = (float) ($profile['metrics']['duration_ms'] ?? 0);
        $queryItems = $sections['queries']['payload']['items'] ?? [];
        $queryAnalysis = $this->queries->analyze(is_array($queryItems) ? $queryItems : [], $requestDuration);
        $status = (int) ($sections['request']['summary']['status'] ?? 0);
        $runtimeType = $sections['request']['payload']['runtime_type'] ?? null;
        $exitCode = $sections['request']['summary']['exit_code'] ?? null;
        $exceptionCount = (int) ($sections['exceptions']['summary']['count'] ?? 0);
        $findings = [];

        $exception = collect($sections['exceptions']['payload']['items'] ?? [])->first(fn (mixed $item): bool => is_array($item));

        if (is_array($exception)) {
            $class = class_basename((string) ($exception['class'] ?? 'Exception'));
            $message = trim((string) ($exception['message'] ?? ''));
            $findings[] = $this->finding(
                'exception.captured',
                'error',
                'exceptions',
                $message !== '' ? sprintf('%s: %s', $class, $message) : sprintf('%s was thrown.', $class),
                [
                    'class' => $exception['class'] ?? null,
                    'message' => $exception['message'] ?? null,
                    'file' => $exception['file'] ?? null,
                    'line' => $exception['line'] ?? null,
                ],
                [
                    'why' => 'The request stopped because the application threw this exception.',
                    'location' => $exception['location'] ?? $this->location($exception),
                    'next' => 'Open the application frame and inspect the values that reached it.',
                    'action' => ['label' => 'Inspect exception', 'section' => 'exceptions'],
                ],
            );
        } elseif ($status >= 400 || (is_int($exitCode) && $exitCode !== 0) || $exceptionCount > 0) {
            $findings[] = $this->finding(
                is_string($runtimeType) ? 'runtime.error' : 'request.error',
                'error',
                'request',
                is_string($runtimeType)
                    ? sprintf('The runtime operation exited with code %s.', (string) $exitCode)
                    : sprintf('The request returned HTTP %d.', $status),
                ['status' => $status, 'exit_code' => $exitCode, 'exception_count' => $exceptionCount],
                [
                    'why' => is_string($runtimeType)
                        ? 'A non-zero exit code means the operation did not complete successfully.'
                        : 'The response status says the application could not complete the request normally.',
                    'next' => 'Inspect the request, response, logs, and related failure collector.',
                    'action' => ['label' => 'Inspect request', 'section' => 'request'],
                ],
            );
        }

        $failedHttp = collect($sections['http_client']['payload']['items'] ?? [])->first(
            fn (mixed $item): bool => is_array($item)
                && (($item['failed'] ?? false) || (int) ($item['status'] ?? 0) >= 400),
        );

        if (is_array($failedHttp)) {
            $method = strtoupper((string) ($failedHttp['method'] ?? 'HTTP'));
            $host = parse_url((string) ($failedHttp['url'] ?? ''), PHP_URL_HOST);
            $target = is_string($host) && $host !== '' ? $host : 'the remote service';
            $cause = (string) ($failedHttp['exception_message'] ?? $failedHttp['exception_class'] ?? 'HTTP '.($failedHttp['status'] ?? 'failure'));
            $findings[] = $this->finding(
                'http.failed',
                'error',
                'http_client',
                sprintf('%s request to %s failed.', $method, $target),
                $failedHttp,
                [
                    'why' => $cause,
                    'location' => $failedHttp['callsite'] ?? null,
                    'next' => 'Check the endpoint, connection settings, timeout, and retry behavior.',
                    'action' => ['label' => 'Inspect HTTP failure', 'section' => 'http_client'],
                ],
            );
        }

        $denied = collect($sections['authorization']['payload']['items'] ?? [])->first(
            fn (mixed $item): bool => is_array($item) && ($item['result'] ?? null) === 'denied',
        );

        if (is_array($denied)) {
            $ability = (string) ($denied['ability'] ?? 'this action');
            $handler = $this->shortName((string) ($denied['handler'] ?? 'the configured gate'));
            $findings[] = $this->finding(
                'authorization.denied',
                'error',
                'authorization',
                sprintf('Authorization denied “%s”.', $ability),
                $denied,
                [
                    'why' => sprintf('%s returned a denied result.', $handler),
                    'location' => $denied['callsite'] ?? null,
                    'next' => 'Check the user, arguments, policy or Gate, and expected permission for this action.',
                    'action' => ['label' => 'Inspect authorization', 'section' => 'authorization', 'filter' => 'denied'],
                ],
            );
        }

        $validation = collect($sections['validation']['payload']['items'] ?? [])->first(
            fn (mixed $item): bool => is_array($item),
        );

        if (is_array($validation)) {
            $fields = array_values(array_filter(array_map('strval', (array) ($validation['fields'] ?? []))));
            $fieldSummary = $fields === [] ? 'the submitted data' : implode(', ', array_slice($fields, 0, 3));
            $fromPreviousRequest = (bool) ($validation['from_previous_request'] ?? false);
            $findings[] = $this->finding(
                'validation.failed',
                'error',
                'validation',
                sprintf('Validation failed for %s.', $fieldSummary),
                $validation,
                [
                    'why' => $fromPreviousRequest
                        ? 'Laravel carried these errors into this page from the previous request.'
                        : 'Laravel rejected one or more fields before the requested action could finish.',
                    'location' => $validation['callsite'] ?? null,
                    'next' => $fromPreviousRequest
                        ? 'Inspect the messages, then reproduce the failed request to capture its rules and source.'
                        : 'Inspect the field messages and rules, then compare them with the submitted form.',
                    'action' => ['label' => 'Inspect validation', 'section' => 'validation'],
                ],
            );
        }

        if ($requestDuration >= $this->slowRequestMs) {
            $findings[] = $this->finding(
                is_string($runtimeType) ? 'runtime.slow' : 'request.slow',
                'warning',
                'timeline',
                is_string($runtimeType)
                    ? sprintf('The runtime operation took %s.', DurationFormatter::format($requestDuration))
                    : sprintf('The request took %s.', DurationFormatter::format($requestDuration)),
                ['duration_ms' => $requestDuration, 'threshold_ms' => $this->slowRequestMs],
                [
                    'why' => sprintf('This is above the configured %s threshold.', DurationFormatter::format($this->slowRequestMs)),
                    'next' => 'Inspect Timeline to find where the request spent time.',
                    'action' => ['label' => 'Review request timing', 'section' => 'timeline'],
                ],
            );
        }

        $slowQueries = array_values(array_filter(
            $queryAnalysis['items'],
            fn (array $item): bool => ($item['slow'] ?? false) && ! $this->isInfrastructureSql((string) ($item['normalized_sql'] ?? '')),
        ));

        if ($slowQueries !== []) {
            usort($slowQueries, fn (array $left, array $right): int => ($right['duration_ms'] ?? 0) <=> ($left['duration_ms'] ?? 0));
            $slowest = $slowQueries[0];
            $findings[] = $this->finding(
                'query.slow',
                'warning',
                'queries',
                sprintf('%d %s exceeded the %s query threshold.', count($slowQueries), str('query')->plural(count($slowQueries)), DurationFormatter::format($this->queries->slowThreshold())),
                [
                    'count' => count($slowQueries),
                    'threshold_ms' => $this->queries->slowThreshold(),
                    'fingerprints' => array_values(array_map(
                        fn (array $item): string => $item['fingerprint'],
                        $slowQueries,
                    )),
                ],
                [
                    'why' => sprintf('The slowest query took %s.', DurationFormatter::format($slowest['duration_ms'] ?? 0)),
                    'location' => $slowest['callsite'] ?? null,
                    'next' => 'Review the SQL, bindings, call site, and database plan.',
                    'action' => ['label' => 'Review slow queries', 'section' => 'queries', 'filter' => 'slow'],
                ],
            );
        }

        foreach ($queryAnalysis['repeated_groups'] as $group) {
            if ($this->isInfrastructureSql((string) ($group['sql'] ?? ''))) {
                continue;
            }

            if ($group['likely_n_plus_one']) {
                $findings[] = $this->finding(
                    'query.n_plus_one',
                    'warning',
                    'queries',
                    sprintf('%d similar queries ran with different values.', $group['count']),
                    [
                        'fingerprint' => $group['fingerprint'],
                        'count' => $group['count'],
                        'bindings_vary' => true,
                        'shared_callsite' => $group['shared_callsite'],
                    ],
                    [
                        'why' => 'The same application call site ran one query for several different records. This is likely an N+1 query.',
                        'location' => $group['shared_callsite'],
                        'next' => 'Check whether the related data can be eager loaded or fetched in one query.',
                        'action' => ['label' => 'Review grouped queries', 'section' => 'queries', 'filter' => 'repeated'],
                    ],
                );

                continue;
            }

            if ($group['count'] < $this->minimumRepeatedExecutions
                && (float) $group['duration_ms'] < $this->minimumRepeatedDurationMs) {
                continue;
            }

            $findings[] = $this->finding(
                'query.repeated',
                'warning',
                'queries',
                sprintf('%d identical query executions added %s.', $group['count'], DurationFormatter::format($group['duration_ms'])),
                [
                    'fingerprint' => $group['fingerprint'],
                    'count' => $group['count'],
                    'extra_executions' => $group['extra_executions'],
                    'connection' => $group['connection'],
                    'duration_ms' => $group['duration_ms'],
                ],
                [
                    'why' => sprintf('%d executions repeated work that one execution may have covered.', $group['extra_executions']),
                    'location' => $group['shared_callsite'],
                    'next' => 'Inspect the grouped executions and decide whether the result can be reused or the query can be moved.',
                    'action' => ['label' => 'Review grouped queries', 'section' => 'queries', 'filter' => 'repeated'],
                ],
            );
        }

        $cache = $sections['cache']['summary'] ?? [];
        $cacheReads = (int) ($cache['hits'] ?? 0) + (int) ($cache['misses'] ?? 0);
        $missRate = $cacheReads > 0 ? (int) ($cache['misses'] ?? 0) / $cacheReads : 0;

        if ($cacheReads >= $this->minimumCacheOperations && $missRate >= $this->highCacheMissRate) {
            $findings[] = $this->finding(
                'cache.high_miss_rate',
                'warning',
                'cache',
                sprintf('%d of %d cache reads missed.', (int) ($cache['misses'] ?? 0), $cacheReads),
                [
                    'reads' => $cacheReads,
                    'misses' => (int) ($cache['misses'] ?? 0),
                    'miss_rate_percent' => round($missRate * 100, 1),
                ],
                [
                    'why' => 'The application paid the cache lookup cost but usually still had to rebuild the value.',
                    'next' => 'Inspect the missed key groups, expiry, and the code that fills them.',
                    'action' => ['label' => 'Inspect cache misses', 'section' => 'cache'],
                ],
            );
        }

        if (($profile['storage']['truncated'] ?? false) === true) {
            $findings[] = $this->finding(
                'profile.truncated',
                'info',
                'request',
                'Some debug data was omitted to keep this profile within its storage limit.',
                $profile['storage'],
                [
                    'why' => 'The complete captured profile exceeded the fixed 10 MB file limit.',
                    'next' => 'Inspect the retained sample or reproduce a smaller operation. MCP exposes omission details at /storage.',
                ],
            );
        }

        foreach ($sections as $key => $section) {
            $dropped = (int) ($section['summary']['dropped_count'] ?? 0);

            if ($dropped > 0) {
                $retained = (int) ($section['summary']['retained_count'] ?? count($section['payload']['items'] ?? []));
                $total = (int) ($section['summary']['count'] ?? ($retained + $dropped));
                $label = strtolower((string) ($section['label'] ?? str($key)->replace('_', ' ')));
                $findings[] = $this->finding(
                    'collector.truncated',
                    'info',
                    (string) $key,
                    sprintf('Showing %d of %d %s.', $retained, $total, $label),
                    ['collector' => (string) $key, 'retained' => $retained, 'total' => $total, 'dropped' => $dropped],
                    ['why' => 'The collector reached its retention limit.', 'next' => 'Use the retained sample or raise the local limit for a focused run.'],
                );
            }

            $transactionDropped = (int) ($section['summary']['transaction_dropped_count'] ?? 0);

            if ($transactionDropped > 0) {
                $transactionRetained = (int) ($section['summary']['transaction_retained_count'] ?? count($section['payload']['transactions'] ?? []));
                $transactionTotal = (int) ($section['summary']['transaction_count'] ?? ($transactionRetained + $transactionDropped));
                $findings[] = $this->finding(
                    'collector.truncated',
                    'info',
                    (string) $key,
                    sprintf('Showing %d of %d query transaction events.', $transactionRetained, $transactionTotal),
                    ['collector' => 'query_transactions', 'retained' => $transactionRetained, 'total' => $transactionTotal, 'dropped' => $transactionDropped],
                    ['why' => 'The transaction event collector reached its retention limit.', 'next' => 'Use the retained sample or raise the local limit for a focused run.'],
                );
            }
        }

        return array_slice($findings, 0, $this->maxFindings);
    }

    /** @return array<string, mixed> */
    private function finding(
        string $ruleId,
        string $severity,
        string $section,
        string $summary,
        array $evidence,
        array $guidance = [],
    ): array {
        return [
            'rule_id' => $ruleId,
            'severity' => $severity,
            'section' => $section,
            'summary' => $summary,
            'why' => $guidance['why'] ?? null,
            'location' => $guidance['location'] ?? null,
            'next' => $guidance['next'] ?? null,
            'action' => $guidance['action'] ?? null,
            'evidence' => $evidence,
        ];
    }

    /** @param array<string, mixed> $item @return array{file: string, line: int}|null */
    private function location(array $item): ?array
    {
        return isset($item['file'], $item['line'])
            ? ['file' => (string) $item['file'], 'line' => (int) $item['line']]
            : null;
    }

    private function isInfrastructureSql(string $sql): bool
    {
        return preg_match('/\b(?:from|into|update|delete\s+from)\s+["`\[]?(?:sessions|cache)["`\]]?/i', $sql) === 1;
    }

    private function shortName(string $value): string
    {
        [$class, $method] = array_pad(explode('@', $value, 2), 2, null);
        $short = class_basename($class);

        return is_string($method) && $method !== '' ? $short.'@'.$method : $short;
    }
}
