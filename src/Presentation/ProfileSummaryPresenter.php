<?php

namespace NewDebugBar\Presentation;

use Carbon\CarbonImmutable;
use NewDebugBar\Support\DurationFormatter;
use NewDebugBar\Support\Redactor;

/** Produces one stable request summary for the UI and MCP. */
final class ProfileSummaryPresenter
{
    public function __construct(private readonly Redactor $redactor) {}

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    public function present(array $profile): array
    {
        $request = $profile['sections']['request'] ?? [];
        $queries = $profile['sections']['queries']['summary'] ?? [];
        $cache = $profile['sections']['cache']['summary'] ?? [];
        $status = (int) ($request['summary']['status'] ?? 0);
        $exceptionCount = (int) ($profile['sections']['exceptions']['summary']['count'] ?? 0);
        $exitCode = $request['summary']['exit_code'] ?? null;
        $cacheReads = (int) ($cache['hits'] ?? 0) + (int) ($cache['misses'] ?? 0);
        $requestType = $this->requestType($request);
        $duration = $profile['metrics']['duration_ms'] ?? 0;
        $queryTime = $queries['total_time_ms'] ?? $queries['duration_ms'] ?? 0;

        /** @var array<string, mixed> $summary */
        $summary = $this->redactor->clean([
            'id' => $profile['id'] ?? null,
            'short_id' => is_string($profile['id'] ?? null) ? substr($profile['id'], 0, 8) : null,
            'recorded_at' => $profile['recorded_at'] ?? null,
            'recorded_time' => $this->recordedTime($profile['recorded_at'] ?? null),
            'environment' => $profile['environment'] ?? null,
            'profile_type' => $profile['profile_type'] ?? 'http',
            'completion_state' => $profile['completion_state'] ?? 'complete',
            'background_pending' => isset($profile['background_activity']['error'])
                ? null
                : (bool) ($profile['background_activity']['pending'] ?? false),
            'background_error' => $profile['background_activity']['error'] ?? null,
            'background_activity_count' => (int) ($profile['background_activity']['count'] ?? 0),
            'related_profile_ids' => $profile['background_activity']['related_profile_ids'] ?? [],
            'origin_profile_id' => $profile['background_activity']['origin_profile_id'] ?? null,
            'request_type' => $requestType,
            'request_type_label' => match ($requestType) {
                'ajax' => 'Ajax',
                'artisan' => 'Command',
                'cli' => 'CLI',
                'download' => 'Download',
                'full_page' => 'Page',
                'json' => 'JSON',
                'queue' => 'Worker',
                'redirect' => 'Redirect',
                'stream' => 'Stream',
                'test' => 'Test',
                default => 'Request',
            },
            'title' => $this->activity($request, $requestType) ?: (($request['payload']['path'] ?? null) ?: 'Request'),
            'activity' => $this->activity($request, $requestType),
            'method' => $request['summary']['method'] ?? null,
            'path' => $request['payload']['path'] ?? null,
            'status' => $status,
            'status_meaning' => $this->statusMeaning($status),
            'response_size' => $this->formatBytes($request['payload']['response_size_bytes'] ?? null),
            'duration_ms' => $duration,
            'duration_label' => DurationFormatter::format($duration),
            'peak_memory_mb' => $profile['metrics']['peak_memory_mb'] ?? 0,
            'query_count' => $queries['total_count'] ?? $queries['count'] ?? 0,
            'query_time_ms' => $queryTime,
            'query_time_label' => DurationFormatter::format($queryTime),
            'repeated_pattern_count' => $queries['repeated_pattern_count'] ?? 0,
            'slow_query_count' => $queries['slow_count'] ?? 0,
            'cache_hits' => $cache['hits'] ?? 0,
            'cache_misses' => $cache['misses'] ?? 0,
            'cache_hit_rate' => $cacheReads > 0 ? round(((int) ($cache['hits'] ?? 0) / $cacheReads) * 100, 1) : 0.0,
            'exception_count' => $exceptionCount,
            'finding_count' => count($profile['findings'] ?? []),
            'warning' => $status >= 400 || (is_int($exitCode) && $exitCode !== 0) || $exceptionCount > 0 || ($profile['findings'] ?? []) !== [] || isset($profile['background_activity']['error']),
        ]);

        return $summary;
    }

    private function statusMeaning(int $status): ?string
    {
        return match (true) {
            $status >= 100 && $status < 200 => 'Informational',
            $status >= 200 && $status < 300 => 'Success',
            $status >= 300 && $status < 400 => 'Redirect',
            $status >= 400 && $status < 500 => 'Client error',
            $status >= 500 && $status < 600 => 'Server error',
            default => null,
        };
    }

    private function formatBytes(mixed $bytes): ?string
    {
        if (! is_numeric($bytes) || (float) $bytes < 0) {
            return null;
        }

        $bytes = (float) $bytes;

        return match (true) {
            $bytes >= 1024 * 1024 => number_format($bytes / (1024 * 1024), 2).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 2).' KB',
            default => number_format($bytes).' B',
        };
    }

    /** @param array<string, mixed> $request */
    private function requestType(array $request): string
    {
        $runtimeType = $request['payload']['runtime_type'] ?? null;

        if (is_string($runtimeType) && $runtimeType !== '') {
            return $runtimeType;
        }

        $status = (int) ($request['summary']['status'] ?? 0);
        $capturedType = $request['payload']['request_type'] ?? null;

        if (is_string($capturedType) && $capturedType !== '') {
            return $capturedType;
        }

        if ($status >= 300 && $status < 400) {
            return 'redirect';
        }

        return 'full_page';
    }

    /** @param array<string, mixed> $request */
    private function activity(array $request, string $requestType): ?string
    {
        if ($requestType === 'redirect') {
            $location = $this->header($request, 'location', 'response_headers');

            return $location === null ? 'Redirect response' : 'Redirected to '.$location;
        }

        if ($requestType === 'download') {
            $path = $request['payload']['path'] ?? null;

            return is_string($path) && $path !== '' ? 'Downloaded '.basename($path) : 'File download';
        }

        if ($requestType === 'queue') {
            $context = is_array($request['payload']['context'] ?? null) ? $request['payload']['context'] : [];
            $subject = $context['communication_class'] ?? $request['payload']['name'] ?? null;

            return is_string($subject) && $subject !== '' ? class_basename($subject) : 'Queue worker';
        }

        return null;
    }

    /** @param array<string, mixed> $request */
    private function header(array $request, string $name, string $group = 'headers'): ?string
    {
        $value = $request['payload'][$group][$name] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function recordedTime(mixed $recordedAt): ?string
    {
        if (! is_string($recordedAt) || $recordedAt === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($recordedAt)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
